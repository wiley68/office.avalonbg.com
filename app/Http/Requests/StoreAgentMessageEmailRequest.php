<?php

namespace App\Http\Requests;

use App\Enums\AgentContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAgentMessageEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc,dns'],
            'subject' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function expectedAgentContext(): AgentContext
    {
        return match (true) {
            $this->routeIs('dashboard.agent.message.email') => AgentContext::Orchestrator,
            $this->routeIs('dashboard.notes.agent.message.email') => AgentContext::Notes,
            $this->routeIs('dashboard.contacts.agent.message.email') => AgentContext::Contacts,
            default => throw new \LogicException(
                'Unexpected route for agent email: '.$this->route()?->getName()
            ),
        };
    }
}
