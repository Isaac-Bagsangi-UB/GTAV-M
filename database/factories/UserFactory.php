<?php

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => User::ROLE_ATTENDEE,
            'company' => fake()->company(),
        ];
    }

    /**
     * Indicate that the user is a Global Event Administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => User::ROLE_ADMIN, 'chapter_id' => null]);
    }

    /**
     * Indicate that the user is a Regional Chapter Manager of the given chapter.
     */
    public function chapterManager(Chapter $chapter): static
    {
        return $this->state(fn (array $attributes) => ['role' => User::ROLE_MANAGER, 'chapter_id' => $chapter->id]);
    }

    /**
     * Indicate that the user is Venue Gate Staff for the given chapter.
     */
    public function gateStaff(Chapter $chapter): static
    {
        return $this->state(fn (array $attributes) => ['role' => User::ROLE_GATE, 'chapter_id' => $chapter->id]);
    }

    /**
     * Indicate that the user is a Guest Speaker.
     */
    public function speaker(): static
    {
        return $this->state(fn (array $attributes) => ['role' => User::ROLE_SPEAKER]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
