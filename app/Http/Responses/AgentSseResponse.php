<?php

namespace App\Http\Responses;

use App\Support\CurrentAgentContext;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AgentSseResponse
{
    /**
     * SSE поток: събития от Laravel AI + финален ред с conversation_id.
     */
    public static function fromStreamable(StreamableAgentResponse $streamable): StreamedResponse
    {
        $conversationId = null;

        $streamable->then(function (StreamedAgentResponse $response) use (&$conversationId): void {
            $conversationId = $response->conversationId;
        });

        return response()->stream(function () use ($streamable, &$conversationId) {
            try {
                foreach ($streamable as $event) {
                    echo 'data: '.((string) $event)."\n\n";

                    if (function_exists('ob_flush')) {
                        @ob_flush();
                    }
                    flush();
                }

                echo 'data: '.json_encode([
                    'type' => 'meta',
                    'conversation_id' => $conversationId,
                ], JSON_UNESCAPED_UNICODE)."\n\n";

                echo "data: [DONE]\n\n";
            } finally {
                CurrentAgentContext::clear();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
