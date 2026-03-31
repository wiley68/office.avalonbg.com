<?php

namespace App\Http\Controllers;

use App\Ai\OfficeAgentTools;
use App\Http\Requests\StoreAgentMessageRequest;
use App\Http\Responses\AgentSseResponse;
use App\Support\OfficeAgent;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ServiceCardsAgentController extends Controller
{
    public function store(StoreAgentMessageRequest $request): JsonResponse|StreamedResponse
    {
        $instructions = <<<'TXT'
Ти си асистент за сервизни карти в офис системата. Отговаряй на български, кратко и ясно.
Данните са в таблица projects (service база). Връзки: name -> contacts.id, rakovoditel_id / serviseproblemtechnik_id / saobshtilclient_id -> members.id.
Използвай инструмента за сервизни карти за list, count, show, create, update, delete.
Използвай инструмента за продадени продукти (ceni) за CRUD по project_id, когато потребителят иска работа с вложени продадени елементи към сервизна карта.
За избор на клиент по име/id използвай инструмента за контакти.
Спазвай enum стойностите:
- special: „Спешна поръчка“ или „Нормална поръчка“
- varanty: „Гаранционен“ или „Извън гаранционен“
- etap: „Приета за сервиз“, „Диагностика“, „Извършва се ремонта“, „Изпратен за гаранционен ремонт“, „Приключен ремонт“
При заявка за конкретна сервизна карта винаги извикай action=show за съответния id.
Използвай инструмента за имейл при заявка за изпращане по имейл.
TXT;

        try {
            $validated = $request->validated();

            $streamable = OfficeAgent::streamWithMemory(
                $request->user(),
                $validated['conversation_id'] ?? null,
                $validated['message'],
                $instructions,
                OfficeAgentTools::forServiceCards(),
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
