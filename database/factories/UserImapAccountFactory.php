<?php

namespace Database\Factories;

use App\Enums\ImapEncryption;
use App\Models\User;
use App\Models\UserImapAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserImapAccount>
 */
class UserImapAccountFactory extends Factory
{
    protected $model = UserImapAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => ImapEncryption::Ssl,
            'username' => fake()->safeEmail(),
            'password' => 'secret-password',
            'default_folder' => 'INBOX',
            'last_verified_at' => null,
        ];
    }
}
