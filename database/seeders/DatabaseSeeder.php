<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use LogicException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $profiler = Validator::make((array) config('seeding.profiler'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
        ])->validate();

        DB::transaction(function () use ($profiler): void {
            $this->call(RolesSeeder::class);

            $user = User::query()->firstOrCreate(
                ['email' => $profiler['email']],
                [
                    'name' => $profiler['name'],
                    'password' => $profiler['password'],
                    'email_verified_at' => now(),
                ],
            );

            if ($user->wasRecentlyCreated) {
                $user->assignRole(UserRole::Profiler->value);
            } elseif (! $user->isProfiler()) {
                throw new LogicException('The bootstrap account must be a profiler.');
            }

            $this->call(ProfitTypeSeeder::class);
        });
    }
}
