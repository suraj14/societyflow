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
        // UtilityBillController authorizes against this; without the mapping
        // Gate found neither policy nor ability and denied every action, so
        // the whole utility-bills module returned 403 for every role.
        \App\Models\UtilityBill::class => \App\Policies\UtilityBillPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Super Admin passes every gate.
        //
        // Several controllers call authorize() with an ability name that is
        // not a registered permission (for example 'manage_settings', which
        // does not exist in the permissions table). Spatie's Gate::before only
        // resolves real permission names, so those checks denied even Super
        // Admin and made the screens unreachable. This makes the Super Admin
        // role behave the way the rest of the codebase already assumes.
        //
        // Returning null rather than false for everyone else lets normal
        // policy and permission resolution continue.
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });
    }
}