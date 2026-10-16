<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
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
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'meeting_date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'start_time' => '09:00',
            'location' => 'Ruang Rapat Utama',
            'style' => 'ringkas',
            'use_followup' => true,
            'use_kop' => true,
            'use_signature' => true,
            'show_attendance' => true,
            'show_recorded_notice' => false,
            'status' => 'draft',
            'late_after_minutes' => 15,
        ];
    }
}
