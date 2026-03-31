<?php

namespace App\Http\Requests;

use App\Enums\AgentContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAgentMessageFeedbackRequest extends FormRequest
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
            'feedback' => ['required', 'string', 'in:up,down'],
        ];
    }

    public function expectedAgentContext(): AgentContext
    {
        return match (true) {
            $this->routeIs('dashboard.agent.message.feedback') => AgentContext::Orchestrator,
            $this->routeIs('dashboard.notes.agent.message.feedback') => AgentContext::Notes,
            $this->routeIs('dashboard.contacts.agent.message.feedback') => AgentContext::Contacts,
            $this->routeIs('dashboard.warranties.agent.message.feedback') => AgentContext::Warranties,
            $this->routeIs('dashboard.service-cards.agent.message.feedback') => AgentContext::ServiceCards,
            default => throw new \LogicException(
                'Unexpected route for agent feedback: '.$this->route()?->getName()
            ),
        };
    }
}
