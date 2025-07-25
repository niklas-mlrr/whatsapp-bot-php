<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WhatsAppMessage>
 */
class WhatsAppMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender' => $this->faker->phoneNumber(),
            'chat' => $this->faker->uuid(),
            'type' => $this->faker->randomElement(['text', 'image']),
            'content' => $this->faker->text(50),
            'sending_time' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
