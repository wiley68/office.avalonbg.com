<?php

namespace App\Models;

use App\Enums\ImapEncryption;
use Database\Factories\UserImapAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ImapEncryption $encryption
 * @property Carbon|null $last_verified_at
 */
#[Fillable([
    'user_id',
    'host',
    'port',
    'encryption',
    'username',
    'password',
    'default_folder',
    'last_verified_at',
])]
#[Hidden(['password'])]
class UserImapAccount extends Model
{
    /** @use HasFactory<UserImapAccountFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mailboxIdentity(): string
    {
        return hash('sha256', json_encode([
            strtolower(trim($this->host)),
            (int) $this->port,
            $this->encryption->value,
            $this->username,
        ], JSON_THROW_ON_ERROR));
    }

    public function mailboxPath(string $folder): string
    {
        return $this->mailboxReference().$folder;
    }

    public function mailboxReference(): string
    {
        return sprintf(
            '{%s:%d%s}',
            $this->host,
            $this->port,
            $this->encryption->imapFlag(),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'encryption' => ImapEncryption::class,
            'password' => 'encrypted',
            'last_verified_at' => 'datetime',
        ];
    }
}
