<?php

namespace App\Http\Controllers;

use App\Ai\OfficeAgentTools;
use App\Http\Requests\StoreAgentMessageRequest;
use App\Support\OfficeAgent;
use Illuminate\Http\JsonResponse;
use Throwable;

class DashboardAgentController extends Controller
{
    /**
     * Общ офис оркестратор: избира кои инструменти да ползва според заявката.
     */
    public function store(StoreAgentMessageRequest $request): JsonResponse
    {
        $instructions = <<<'TXT'
Ти си общият офис координатор (оркестратор). Отговаряй на български, ясно и кратко.

Анализирай заявката и използвай наличните инструменти само когато потребителят има нужда от данни или действия в системата:
- За бележки (notes) — списък, създаване, редакция, изтриване: използвай инструмента за бележки.

Ако заявката е за функционалност, за която още няма инструмент в системата, обясни накратко и предложи потребителят да ползва съответната страница от менюто, когато е налична.

За общи въпроси без нужда от данни от системата — отговори директно, без инструменти.
TXT;

        try {
            $validated = $request->validated();

            $response = OfficeAgent::promptWithMemory(
                $request->user(),
                $validated['conversation_id'] ?? null,
                $validated['message'],
                $instructions,
                OfficeAgentTools::forOrchestrator(),
            );

            return response()->json([
                'reply' => $response->text,
                'conversation_id' => $response->conversationId,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Агентът не можа да отговори в момента. Проверете AI настройките и опитайте отново.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 503);
        }
    }
}
