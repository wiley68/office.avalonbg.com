<?php

namespace App\Http\Controllers;

use App\Ai\OfficeAgentTools;
use App\Http\Requests\StoreAgentMessageRequest;
use App\Http\Responses\AgentSseResponse;
use App\Support\OfficeAgent;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class WarrantiesAgentController extends Controller
{
    /**
     * Агент за гаранционни карти (varanty + връзка към contacts).
     */
    public function store(StoreAgentMessageRequest $request): JsonResponse|StreamedResponse
    {
        $instructions = <<<'TXT'
Ти си асистент за гаранционни карти в офис системата. Отговаряй на български, кратко и ясно.
Данните са в таблица varanty (service база). Всяка карта е свързана с контакт чрез client_id (contacts.id).
Използвай инструмента за гаранционни карти за list, show, create, update, delete.
За търсене на контакт по име/id ползвай инструмента за контакти (action=list или show), за да подбереш правилен client_id при създаване или редакция.
Полета: product (продукт), sernum (сериен на картата), date_sell (дата на издаване), invoice, varanty_period, service („в сервиз“ / „при клиента“), obsluzvane („4-8“, „8-16“, „8-32“), note.
При iscomp=Yes попълвай компонентите на компютъра (motherboard, processor, …) и серийните им номера (*sn).
При заявка за конкретна карта винаги извикай инструмента с action=show за този id в текущия отговор.
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
                OfficeAgentTools::forWarranties(),
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
