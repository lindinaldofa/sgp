<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Tenant;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $user = auth()->user();
            if ($user instanceof User && empty($model->tenant_id)) {
                $model->tenant_id = $user->tenant_id;
            }
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
