<?php

namespace App\Providers;

use App\Services\HellOotelLookupService;
use App\Services\HellOotelReservationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $token = fn() => Auth::user()?->hellootel_access_token ?? config('services.hellootel.token') ?? '';

        $this->app->bind(HellOotelLookupService::class,      fn() => new HellOotelLookupService($token()));
        $this->app->bind(HellOotelReservationService::class,  fn() => new HellOotelReservationService($token()));
    }

    public function boot(): void {}
}
