<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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

        // User::factory(10)->create();

        $adminUser = User::query()->updateOrCreate(
            ['email' => 'home@avalonbg.com'],
            [
                'name' => 'Администратор',
                'password' => '1Nikola@Stefanov9',
            ]
        );

        $adminUser->assignRole('admin');

    }
}
