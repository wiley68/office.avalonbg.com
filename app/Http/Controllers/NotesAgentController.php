<?php

namespace App\Http\Controllers;

use App\Ai\OfficeAgentTools;
use App\Http\Requests\StoreAgentMessageRequest;
use App\Support\OfficeAgent;
use Illuminate\Http\JsonResponse;
use Throwable;

class NotesAgentController extends Controller
{
    /**
     * Агент само за бележки (фокусирани инструкции + notes tools).
     */
    public function store(StoreAgentMessageRequest $request): JsonResponse
    {
        $instructions = <<<'TXT'
Ти си асистент за лични бележки (notes) в офис системата. Отговаряй на български, кратко и ясно.
Използвай инструмента за бележки, когато трябва да четеш, създаваш, редактираш или триеш бележки.
Ако липсва информация, попитай.
TXT;

        try {
            $validated = $request->validated();

            $response = OfficeAgent::promptWithMemory(
                $request->user(),
                $validated['conversation_id'] ?? null,
                $validated['message'],
                $instructions,
                OfficeAgentTools::forNotes(),
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
