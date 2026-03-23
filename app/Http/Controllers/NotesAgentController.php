<?php

namespace App\Http\Controllers;

use App\Ai\OfficeAgentTools;
use App\Http\Requests\StoreAgentMessageRequest;
use App\Http\Responses\AgentSseResponse;
use App\Support\OfficeAgent;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class NotesAgentController extends Controller
{
    /**
     * Агент само за бележки (фокусирани инструкции + notes tools, SSE stream).
     */
    public function store(StoreAgentMessageRequest $request): JsonResponse|StreamedResponse
    {
        $instructions = <<<'TXT'
Ти си асистент за лични бележки (notes) в офис системата. Отговаряй на български, кратко и ясно.
Всяка бележка има заглавие (name), по избор кратко описание до 120 символа (description) и пълно съдържание (note). Може да има няколко бележки с еднакво заглавие, но с различни описания или текст.
Използвай инструмента за бележки, когато трябва да четеш, създаваш, редактираш или триеш бележки.
При заявка за конкретна бележка (напр. по id/номер) винаги извикай инструмента с action=show за този id в текущия отговор, дори да имаш предишна информация в паметта на разговора.
За експорт на всички бележки в Excel (.xlsx) използвай инструмента export_notes_to_xlsx и подай на потребителя download_url от резултата (отваряне в същия браузър, логната сесия).
Използвай инструмента за имейл, когато потребителят иска да изпрати assistant отговор по имейл.
Когато трябва да изпратиш по имейл конкретно описание/текст от бележка, подай го в content на имейл инструмента.
Не потвърждавай „изпратено“, ако не си получил ok от имейл инструмента.
Ако липсва информация, попитай.
Ако връщаш към потребителя дълъг текст само от латински букви, цифри и символите + / = (напр. Base64 / криптиран низ), задължително го огради в markdown блок с код (три обратни апострофа) и не променяй нито един символ — не „коригирай“, не заменяй с „подобни“ Unicode букви и не съкратявай реда; копирането трябва да е буквално идентично със записа в базата.
TXT;

        try {
            $validated = $request->validated();

            $streamable = OfficeAgent::streamWithMemory(
                $request->user(),
                $validated['conversation_id'] ?? null,
                $validated['message'],
                $instructions,
                OfficeAgentTools::forNotes(),
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
