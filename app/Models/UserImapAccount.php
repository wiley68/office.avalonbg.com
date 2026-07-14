<?php

namespace App\Models;

use App\Enums\ImapEncryption;
use Database\Factories\UserImapAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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

    public function mailboxPath(string $folder): string
    {
        $flag = $this->encryption->imapFlag();

        return sprintf(
            '{%s:%d%s}%s',
            $this->host,
            $this->port,
            $flag,
            $folder,
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
