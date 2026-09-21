<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

/**
 * Shared "package a Flow/Agent's final output for the calling channel"
 * logic, used by both FlowTriggerController and AgentTriggerController so
 * Telegram/WhatsApp/Twilio-facing responses stay identical regardless of
 * whether a plain Flow or an Agent answered the webhook.
 */
trait ChannelResponseFormatter
{
    protected function formatFinalOutput(?array $final, int $runId, string $runIdKey = 'run_id'): array
    {
        if (!$final) {
            return [
                'success' => true,
                $runIdKey => $runId,
                'message' => 'İşlem tamamlandı ancak bir "Cevap" değeri üretilmedi.',
            ];
        }

        return match ($final['mode'] ?? 'json') {
            'audio' => ['success' => true, $runIdKey => $runId, 'audio_url' => $final['value']],
            'text' => ['success' => true, $runIdKey => $runId, 'text' => $final['value']],
            default => ['success' => true, $runIdKey => $runId, 'data' => $final['value']],
        };
    }

    /**
     * Build a TwiML (Twilio Markup Language) XML response for phone-call
     * flows. `play` speaks an audio_url, `say` speaks raw text, and
     * `play_and_gather` plays audio then loops the call back into
     * `$actionUrl` with the caller's next utterance transcribed by Twilio
     * itself (as `SpeechResult`) — this is what makes a real multi-turn
     * phone conversation possible.
     */
    protected function twimlResponse(array $final, string $actionUrl): Response
    {
        $value = htmlspecialchars((string) ($final['value'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $type = $final['twiml_type'] ?? 'play';

        $inner = match ($type) {
            'say' => "<Say language=\"tr-TR\">{$value}</Say>",
            'play_and_gather' => (function () use ($value, $actionUrl) {
                $escapedAction = htmlspecialchars($actionUrl, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                return "<Gather input=\"speech\" language=\"tr-TR\" action=\"{$escapedAction}\" method=\"POST\"><Play>{$value}</Play></Gather>";
            })(),
            default => "<Play>{$value}</Play>",
        };

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><Response>{$inner}</Response>";

        return response($xml, 200)->header('Content-Type', 'text/xml');
    }
}
