<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\ServiceProvider::class => \App\Policies\ServiceProviderPolicy::class,
        \App\Models\FacilityBooking::class => \App\Policies\FacilityBookingPolicy::class,
        \App\Models\VillaArea::class => \App\Policies\VillaAreaPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}