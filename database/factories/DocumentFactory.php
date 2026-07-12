<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word().'.pdf';

        return [
            'user_id' => User::factory(),
            'original_name' => $name,
            'storage_path' => 'documents/'.Str::uuid().'/'.Str::slug($name),
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1024, 5_000_000),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
