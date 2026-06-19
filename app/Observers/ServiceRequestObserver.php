<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\ServiceRequest;

class ServiceRequestObserver
{
    public function created(ServiceRequest $serviceRequest): void
    {
        if (auth()->check()) {
            AuditLog::create([
                'tenant_id' => $serviceRequest->tenant_id,
                'user_id' => auth()->id(),
                'action' => 'service_request.created',
                'model_type' => ServiceRequest::class,
                'model_id' => $serviceRequest->id,
                'new_values' => ['subject' => $serviceRequest->subject, 'status' => $serviceRequest->status?->value],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        }
    }
}
