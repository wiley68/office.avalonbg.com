<?php

namespace App\Http\Requests;

use App\Enums\AgentContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $context = $this->expectedAgentContext();

        return [
            'message' => ['required', 'string', 'max:16000'],
            'conversation_id' => [
                'nullable',
                'uuid',
                Rule::exists('agent_conversations', 'id')
                    ->where('user_id', $this->user()?->id)
                    ->where('context', $context->value),
            ],
        ];
    }

    protected function expectedAgentContext(): AgentContext
    {
        return match (true) {
            $this->routeIs('dashboard.agent') => AgentContext::Orchestrator,
            $this->routeIs('dashboard.notes.agent') => AgentContext::Notes,
            $this->routeIs('dashboard.contacts.agent') => AgentContext::Contacts,
            default => throw new \LogicException(
                'Unexpected route for agent message: '.$this->route()?->getName()
            ),
        };
    }
}
