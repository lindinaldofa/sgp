<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Contact;
use App\Models\ServiceRequest;
use App\Observers\ContactObserver;
use App\Observers\ServiceRequestObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Contact::observe(ContactObserver::class);
        ServiceRequest::observe(ServiceRequestObserver::class);
    }
}
