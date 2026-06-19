<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company().' '.fake()->companySuffix();

        return [
            'ulid' => Str::ulid()->toBase32(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'plan' => 'essential',
            'is_active' => true,
        ];
    }
}
