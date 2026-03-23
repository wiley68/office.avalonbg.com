<?php

namespace App\Enums;

enum AgentContext: string
{
    case Orchestrator = 'orchestrator';
    case Notes = 'notes';
    case Contacts = 'contacts';
}
