<?php

namespace App\Enums;

enum ImapEncryption: string
{
    case Ssl = 'ssl';
    case Tls = 'tls';
    case None = 'none';

    public function imapFlag(): string
    {
        return match ($this) {
            self::Ssl => '/imap/ssl',
            self::Tls => '/imap/tls',
            self::None => '/imap/notls',
        };
    }
}
