<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationSignatory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationSignatory>
 */
class OrganizationSignatoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'position' => fake()->jobTitle(),
            'role_label' => fake()->randomElement(['Mengetahui', 'Ketua Rapat', 'Notulis']),
            'sort' => 1,
        ];
    }
}
