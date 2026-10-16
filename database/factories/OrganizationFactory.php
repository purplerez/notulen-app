<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'address_text' => fake()->address(),
            'kop_path' => null,
            'plan' => 'internal',
            'quota_minutes' => 6000,
            'parent_id' => null,
            'settings' => [
                'leader_label' => 'Kepala Sekolah',
            ],
        ];
    }
}
