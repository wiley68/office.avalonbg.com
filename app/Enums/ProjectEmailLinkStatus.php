<?php

namespace App\Enums;

enum ProjectEmailLinkStatus: string
{
    case Active = 'active';
    case MissingOnServer = 'missing_on_server';
}
