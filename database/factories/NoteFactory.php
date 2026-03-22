<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => Str::limit(fake()->words(3, true), 40, ''),
            'description' => fake()->boolean(60)
                ? Str::limit(fake()->sentence(), 120, '')
                : null,
            'note' => fake()->paragraph(),
        ];
    }
}
