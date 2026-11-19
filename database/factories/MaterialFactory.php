<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Material;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    /**
     * Define the model's default state: a small text handout stored on the private disk.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->randomElement(['Session slides', 'Workshop handout', 'Venue map', 'Reading list', 'Exhibitor guide']);
        $fileName = Str::slug($title).'.txt';

        return [
            'event_id' => Event::factory(),
            'uploaded_by' => User::factory()->admin(),
            'title' => $title,
            'path' => fn (array $attributes) => 'materials/'.$attributes['event_id'].'/'.Str::random(12).'-'.$fileName,
            'original_name' => $fileName,
            'size' => 0,
        ];
    }

    /**
     * Write a placeholder file to the private disk so the material can actually be downloaded.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Material $material) {
            $body = $material->title."\n\nSample file generated for demonstration.\n";
            Storage::disk('local')->put($material->path, $body);
            $material->forceFill(['size' => strlen($body)])->saveQuietly();
        });
    }
}
