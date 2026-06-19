<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AuditLog;
use App\Models\User;

trait HasAuditLog
{
    public static function bootHasAuditLog(): void
    {
        static::updated(function ($model) {
            $user = auth()->user();
            if ($user instanceof User) {
                AuditLog::create([
                    'tenant_id' => $user->tenant_id,
                    'user_id' => $user->id,
                    'action' => strtolower(class_basename($model)).'.updated',
                    'model_type' => get_class($model),
                    'model_id' => $model->getKey(),
                    'old_values' => $model->getOriginal(),
                    'new_values' => $model->getDirty(),
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                ]);
            }
        });

        static::deleted(function ($model) {
            $user = auth()->user();
            if ($user instanceof User) {
                AuditLog::create([
                    'tenant_id' => $user->tenant_id,
                    'user_id' => $user->id,
                    'action' => strtolower(class_basename($model)).'.deleted',
                    'model_type' => get_class($model),
                    'model_id' => $model->getKey(),
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                ]);
            }
        });
    }
}
