<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\AgentRunJob;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Services\Agent\AgentExecutorService;
use App\Services\QueueWorkerService;
use App\Support\ChannelResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(
    name: 'Agents',
    description: 'Otonom yapay zeka ajanlarının (Autonomous Agents) tetiklenmesi, araç çağırma (tool-calling) ve durum takibi'
)]
class AgentTriggerController extends Controller
{
    use ChannelResponseFormatter;

    #[OA\Post(
        path: '/api/v1/agents/{slug}/run',
        summary: 'Otonom ajanı tetikle / etkileşime geç',
        description: 'Slug değeri ile hedeflenen otonom ajanı çalıştırır. LLM, araçlar (tools), ses işleme ve bilgi tabanı zincirini işletir. ?async=1 ile asenkron kuyruğa alınabilir.',
        security: [['ApiKeyAuth' => []]],
        tags: ['Agents'],
        parameters: [
            new OA\Parameter(
                name: 'slug',
                description: 'Tetiklenecek ajanın URL slug değeri',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'destek-asistani')
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
            description: 'Ajan girdisi (JSON mesajı veya isteğe bağlı ses dosyası)',
            required: false,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(property: 'message', description: 'Ajana iletilecek kullanıcı mesajı', type: 'string', example: 'Müşteri kayıtlarını kontrol eder misin?'),
                            new OA\Property(property: 'session_id', description: 'Oturum ID (bağlam takibi için)', type: 'string', example: 'sess_98765'),
                        ],
                        type: 'object'
                    )
                ),
                new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(property: 'audio', description: 'Kullanıcı ses girdisi (STT için)', type: 'string', format: 'binary'),
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
                description: 'Ajan yanıtı başarıyla üretildi',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'run_id', type: 'integer', example: 15),
                        new OA\Property(property: 'text', type: 'string', example: 'Kayıtlar incelendi, toplam 3 aktif kayıt bulundu.'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 202,
                description: 'Ajan çalıştırma kuyruğa alındı (Asenkron)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Ajan çalıştırma kuyruğa alındı.'),
                        new OA\Property(property: 'run_id', type: 'integer', example: 15),
                        new OA\Property(property: 'status', type: 'string', example: 'pending'),
                        new OA\Property(property: 'poll_url', type: 'string', example: 'http://127.0.0.1:8100/api/v1/agents/runs/15'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Ajan bulunamadı'
            ),
            new OA\Response(
                response: 423,
                description: 'Ajan pasif durumda'
            ),
        ]
    )]
    public function run(Request $request, string $slug, AgentExecutorService $executor): Response
    {
        $agent = Agent::where('trigger_slug', $slug)->first();

        if (!$agent) {
            return response()->json(['success' => false, 'message' => 'Ajan bulunamadı.'], 404);
        }

        if (!$agent->is_active) {
            return response()->json(['success' => false, 'message' => 'Bu ajan şu anda pasif durumda.'], 423);
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
            $filename = 'agent_trigger_' . Str::random(12) . '.' . $request->file('audio')->getClientOriginalExtension();
            $request->file('audio')->move($uploadDir, $filename);
            $triggerContext['audio_path'] = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        }

        $isAsync = filter_var($request->query('async', false), FILTER_VALIDATE_BOOLEAN);

        if ($isAsync) {
            $run = AgentRun::create([
                'agent_id' => $agent->id,
                'status' => 'pending',
                'trigger_payload' => $triggerContext,
            ]);

            AgentRunJob::dispatch($run->id)->onQueue('default');
            QueueWorkerService::ensureRunning();

            return response()->json([
                'success' => true,
                'message' => 'Ajan çalıştırma kuyruğa alındı.',
                'run_id' => $run->id,
                'status' => 'pending',
                'poll_url' => url("/api/v1/agents/runs/{$run->id}"),
            ], 202);
        }

        // Synchronous execution — allow long-running LLM/tool/TTS/STT chains.
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $run = AgentRun::create([
            'agent_id' => $agent->id,
            'status' => 'running',
            'trigger_payload' => $triggerContext,
            'started_at' => now(),
        ]);

        try {
            $result = $executor->run($agent, $triggerContext, $run);

            $run->update([
                'status' => 'completed',
                'final_reply' => $result['reply_text'] ?? null,
                'completed_at' => now(),
            ]);

            if (($result['final']['mode'] ?? null) === 'twiml') {
                return $this->twimlResponse($result['final'], url("/api/v1/agents/{$slug}/run"));
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
                'message' => 'Ajan çalıştırılamadı: ' . $e->getMessage(),
                'run_id' => $run->id,
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/v1/agents/runs/{id}',
        summary: 'Ajan çalışma durumunu sorgula',
        description: 'Asenkron tetiklenmiş ajan çalışmasının (AgentRun) durumunu ve nihai yanıtını döner.',
        security: [['ApiKeyAuth' => []]],
        tags: ['Agents'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Çalışma (Run) ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 15)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ajan çalışma durumu ve yanıtı',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'run_id', type: 'integer', example: 15),
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
        $run = AgentRun::find($id);

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
            $response = array_merge($response, $this->formatFinalOutput(
                $run->final_reply !== null ? ['mode' => 'text', 'value' => $run->final_reply] : null,
                $run->id
            ));
        }

        return response()->json($response);
    }
}
