<?php

namespace Database\Factories;

use App\Models\Attendee;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\OrgContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendee>
 */
class AttendeeFactory extends Factory
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
            'meeting_id' => Meeting::factory(),
            'contact_id' => OrgContact::factory(),
            'name' => fake()->name(),
            'position' => fake()->jobTitle(),
            'phone_e164' => '628'.fake()->unique()->numerify('1########'),
            'status' => null,
            'is_guest' => false,
        ];
    }
}
