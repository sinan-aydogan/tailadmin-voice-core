<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\RunFlowJob;
use App\Models\Flow;
use App\Models\FlowRun;
use App\Services\Flow\FlowExecutorService;
use App\Services\QueueWorkerService;
use App\Support\ChannelResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(
    name: 'Flows',
    description: 'Görsel iş akışlarının (Visual Flows) tetiklenmesi, yürütülmesi ve asenkron durum sorgulaması'
)]
class FlowTriggerController extends Controller
{
    use ChannelResponseFormatter;

    #[OA\Post(
        path: '/api/v1/flows/{slug}/run',
        summary: 'Görsel akışı (Flow) tetikle ve çalıştır',
        description: 'Benzersiz slug değerine sahip görsel akışı tetikler. Varsayılan olarak senkron çalışır ve akışın yanıtını doğrudan döner. ?async=1 parametresi ile asenkron kuyruğa gönderilebilir.',
        security: [['ApiKeyAuth' => []]],
        tags: ['Flows'],
        parameters: [
            new OA\Parameter(
                name: 'slug',
                description: 'Tetiklenecek akışın URL slug değeri',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'musteri-destek-akisi')
            ),
            new OA\Parameter(
                name: 'async',
                description: 'İşlemin arka plan kuyruğunda asenkron çalıştırılıp çalıştırılmayacağı',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean', default: false)
            ),
        ],
        requestBody: new OA\RequestBody(
            description: 'Akış girdileri (JSON payload ve isteğe bağlı multipart audio dosyası)',
            required: false,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        description: 'Akış bağlamına aktarılacak değişkenler',
                        type: 'object',
                        example: ['user_id' => '123', 'message' => 'Merhaba, sipariş durumumu öğrenmek istiyorum']
                    )
                ),
                new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(property: 'audio', description: 'İsteğe bağlı ses dosyası (STT düğümleri için)', type: 'string', format: 'binary'),
                            new OA\Property(property: 'message', description: 'Metin mesajı', type: 'string'),
                        ],
                        type: 'object'
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Akış başarıyla tamamlandı (Senkron yanıt)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'run_id', type: 'integer', example: 42),
                        new OA\Property(property: 'text', type: 'string', example: 'Talebiniz başarıyla alındı.'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 202,
                description: 'Akış arka plan kuyruğuna alındı (Asenkron)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Akış çalıştırma kuyruğa alındı.'),
                        new OA\Property(property: 'run_id', type: 'integer', example: 42),
                        new OA\Property(property: 'status', type: 'string', example: 'pending'),
                        new OA\Property(property: 'poll_url', type: 'string', example: 'http://127.0.0.1:8100/api/v1/flows/runs/42'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Akış bulunamadı',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Akış bulunamadı.'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 423,
                description: 'Akış pasif durumda',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Bu akış şu anda pasif durumda.'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function run(Request $request, string $slug, FlowExecutorService $executor): Response
    {
        $flow = Flow::where('trigger_slug', $slug)->first();

        if (!$flow) {
            return response()->json(['success' => false, 'message' => 'Akış bulunamadı.'], 404);
        }

        if (!$flow->is_active) {
            return response()->json(['success' => false, 'message' => 'Bu akış şu anda pasif durumda.'], 423);
        }

        $triggerContext = [
            'body' => $request->except(['audio']),
            'query' => $request->query(),
        ];

        if ($request->hasFile('audio')) {
            $uploadDir = base_path('data/uploads');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = 'flow_trigger_' . Str::random(12) . '.' . $request->file('audio')->getClientOriginalExtension();
            $request->file('audio')->move($uploadDir, $filename);
            $triggerContext['audio_path'] = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        }

        $isAsync = filter_var($request->query('async', false), FILTER_VALIDATE_BOOLEAN);

        if ($isAsync) {
            $run = FlowRun::create([
                'flow_id' => $flow->id,
                'status' => 'pending',
                'trigger_payload' => $triggerContext,
            ]);

            RunFlowJob::dispatch($run->id)->onQueue('default');
            QueueWorkerService::ensureRunning();

            return response()->json([
                'success' => true,
                'message' => 'Akış çalıştırma kuyruğa alındı.',
                'run_id' => $run->id,
                'status' => 'pending',
                'poll_url' => url("/api/v1/flows/runs/{$run->id}"),
            ], 202);
        }

        // Synchronous execution — allow long-running LLM/TTS/STT chains.
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $run = FlowRun::create([
            'flow_id' => $flow->id,
            'status' => 'running',
            'trigger_payload' => $triggerContext,
            'started_at' => now(),
        ]);

        try {
            $result = $executor->run($flow, $triggerContext, $run);

            $run->update([
                'status' => 'completed',
                'context' => $result['context'],
                'completed_at' => now(),
            ]);

            if (($result['final']['mode'] ?? null) === 'twiml') {
                return $this->twimlResponse($result['final'], url("/api/v1/flows/{$slug}/run"));
            }

            return response()->json($this->formatFinalOutput($result['final'], $run->id));
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Akış çalıştırılamadı: ' . $e->getMessage(),
                'run_id' => $run->id,
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/v1/flows/runs/{id}',
        summary: 'Akış çalışma durumunu sorgula',
        description: 'Asenkron tetiklenmiş akış çalışmasının (FlowRun) durumunu, log kayıtlarını ve nihai çıktısını döner.',
        security: [['ApiKeyAuth' => []]],
        tags: ['Flows'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Çalışma (Run) ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 42)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Akış çalışma durumu ve sonucu',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'run_id', type: 'integer', example: 42),
                        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'running', 'completed', 'failed'], example: 'completed'),
                        new OA\Property(property: 'error_message', type: 'string', nullable: true),
                        new OA\Property(property: 'output', type: 'object', nullable: true),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Çalışma kaydı bulunamadı'
            ),
        ]
    )]
    public function runStatus($id): JsonResponse
    {
        $run = FlowRun::with('logs')->find($id);

        if (!$run) {
            return response()->json(['success' => false, 'message' => 'Çalışma kaydı bulunamadı.'], 404);
        }

        $response = [
            'success' => true,
            'run_id' => $run->id,
            'status' => $run->status,
            'error_message' => $run->error_message,
        ];

        if ($run->status === 'completed') {
            $finalLog = $run->logs->last();
            $response = array_merge($response, $this->formatFinalOutput(
                $finalLog && $finalLog->node_type === 'output.response' ? $finalLog->output : null,
                $run->id
            ));
        }

        return response()->json($response);
    }
}
