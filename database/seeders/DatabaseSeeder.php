<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
        ]);

        $password = Hash::make('1Nikola@Stefanov9');

        $users = [
            [
                'email' => 'ilko@avalonbg.com',
                'name' => 'Илко Профайлър',
                'role' => UserRole::Profiler,
            ],
            [
                'email' => 'home@avalonbg.com',
                'name' => 'Илко Администратор',
                'role' => UserRole::Admin,
            ],
            [
                'email' => 'ilko.iv@gmail.com',
                'name' => 'Илко Иванов',
                'role' => UserRole::User,
            ],
        ];

        foreach ($users as $data) {
            $user = User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'email_verified_at' => now(),
                    'password' => $password,
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_confirmed_at' => null,
                ],
            );

            $user->syncRoles([$data['role']->value]);
        }
    }
}
