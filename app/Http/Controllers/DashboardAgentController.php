<?php

namespace App\Http\Controllers;

use App\Ai\OfficeAgentTools;
use App\Http\Requests\StoreAgentMessageRequest;
use App\Http\Responses\AgentSseResponse;
use App\Support\OfficeAgent;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DashboardAgentController extends Controller
{
    /**
     * Общ офис оркестратор: избира кои инструменти да ползва според заявката (SSE stream).
     */
    public function store(StoreAgentMessageRequest $request): JsonResponse|StreamedResponse
    {
        $instructions = <<<'TXT'
Ти си общият офис координатор (оркестратор). Отговаряй на български, ясно и кратко.

Анализирай заявката и използвай наличните инструменти само когато потребителят има нужда от данни или действия в системата:
- За бележки (notes) — списък, създаване, редакция, изтриване: използвай инструмента за бележки.
- За експорт на всички бележки в Excel (.xlsx): използвай инструмента export_notes_to_xlsx; подай на потребителя download_url от резултата за изтегляне в същия браузър.
- За изпращане на assistant отговор по имейл: използвай инструмента за имейл.
Когато потребителят иска да изпрати по имейл конкретни данни (напр. описание на бележка), подай тези данни в параметър content на имейл инструмента.
Преди да кажеш „изпратено“, задължително извикай имейл инструмента и потвърди успех само ако tool резултатът е ok.

Ако заявката е за функционалност, за която още няма инструмент в системата, обясни накратко и предложи потребителят да ползва съответната страница от менюто, когато е налична.

За общи въпроси без нужда от данни от системата — отговори директно, без инструменти.
TXT;

        try {
            $validated = $request->validated();

            $streamable = OfficeAgent::streamWithMemory(
                $request->user(),
                $validated['conversation_id'] ?? null,
                $validated['message'],
                $instructions,
                OfficeAgentTools::forOrchestrator(),
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
