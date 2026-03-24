<?php

namespace App\Http\Controllers;

use App\Ai\OfficeAgentTools;
use App\Http\Requests\StoreAgentMessageRequest;
use App\Http\Responses\AgentSseResponse;
use App\Support\OfficeAgent;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ContactsAgentController extends Controller
{
    /**
     * Агент само за контакти (фокусирани инструкции + contacts tools, SSE stream).
     */
    public function store(StoreAgentMessageRequest $request): JsonResponse|StreamedResponse
    {
        $instructions = <<<'TXT'
Ти си асистент за контакти в офис системата. Отговаряй на български, кратко и ясно.
Работиш с данни от таблица contacts и свързаните citi/dlaznosti.
Използвай инструмента за контакти, когато трябва да четеш, създаваш, редактираш или триеш контакти.
За управление на населени места ползвай инструмента за citi.
За управление на длъжности ползвай инструмента за dlaznosti.
При заявка за конкретен контакт (напр. по id/номер) винаги извикай инструмента с action=show за този id в текущия отговор, дори да имаш предишна информация в паметта на разговора.
Използвай инструмента за имейл, когато потребителят иска да изпрати assistant отговор по имейл.
Не потвърждавай „изпратено“, ако не си получил ok от имейл инструмента.
Ако липсва информация, попитай.
TXT;

        try {
            $validated = $request->validated();

            $streamable = OfficeAgent::streamWithMemory(
                $request->user(),
                $validated['conversation_id'] ?? null,
                $validated['message'],
                $instructions,
                OfficeAgentTools::forContacts(),
            );

            return AgentSseResponse::fromStreamable($streamable);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Агентът не можа да отговори в момента. Проверете AI настройките и опитайте отново.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 503);
        }
    }
}
