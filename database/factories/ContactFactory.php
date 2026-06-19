<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContactType;
use App\Models\Contact;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Contact> */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ulid' => Str::ulid()->toBase32(),
            'tenant_id' => Tenant::factory(),
            'name' => fake('pt_BR')->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake('pt_BR')->phoneNumber(),
            'type' => ContactType::Voter,
            'engagement_score' => fake()->numberBetween(1, 5),
            'city' => fake('pt_BR')->city(),
            'state' => fake('pt_BR')->stateAbbr(),
            'lgpd_consent' => true,
        ];
    }
}
