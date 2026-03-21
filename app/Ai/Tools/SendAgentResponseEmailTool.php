<?php

namespace App\Ai\Tools;

use App\Enums\AgentContext;
use App\Models\User;
use App\Services\AgentMessageEmailService;
use App\Support\CurrentAgentContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SendAgentResponseEmailTool implements Tool
{
    public function __construct(
        private readonly AgentMessageEmailService $emailService,
    ) {}

    public function description(): Stringable|string
    {
        return 'Изпраща последния или избран assistant отговор по имейл на потребителя.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'email' => $schema
                ->string()
                ->description('Имейл адрес за изпращане.')
                ->required(),
            'message_id' => $schema
                ->string()
                ->description('ID на assistant съобщение. Ако липсва, изпраща последния assistant отговор в текущия контекст.'),
            'content' => $schema
                ->string()
                ->description('Директен текст за изпращане по имейл (използвай при данни от друг tool, напр. описание на бележка).'),
            'subject' => $schema
                ->string()
                ->description('По избор: subject на имейла.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return json_encode(['error' => 'Няма логнат потребител.'], JSON_UNESCAPED_UNICODE);
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email:rfc,dns'],
            'message_id' => ['nullable', 'uuid'],
            'content' => ['nullable', 'string'],
            'subject' => ['nullable', 'string', 'max:160'],
        ]);

        if ($validator->fails()) {
            return json_encode(['error' => $validator->errors()->first()], JSON_UNESCAPED_UNICODE);
        }

        $context = CurrentAgentContext::get();
        if (! $context instanceof AgentContext) {
            return json_encode(['error' => 'Липсва контекст на текущия агент.'], JSON_UNESCAPED_UNICODE);
        }

        try {
            $validated = $validator->validated();
            $messageId = isset($validated['message_id']) && trim((string) $validated['message_id']) !== ''
                ? $validated['message_id']
                : null;
            $content = isset($validated['content']) && trim((string) $validated['content']) !== ''
                ? $validated['content']
                : null;

            $result = $this->emailService->send(
                user: $user,
                context: $context,
                email: $validated['email'],
                messageId: $messageId,
                subject: $validated['subject'] ?? null,
                content: $content,
            );

            return json_encode([
                'ok' => true,
                'message_id' => $result['id'],
                'sent_to' => $validated['email'],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            return json_encode([
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : 'Неуспешно изпращане на имейл.',
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
