<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Illuminate\Support\Facades\File;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

   public function boot()
{
    $keyPath = storage_path('oauth-private.key');

    if (!File::exists($keyPath) && env('OAUTH_PRIVATE_KEY')) {
        File::put($keyPath, env('OAUTH_PRIVATE_KEY'));
        chmod($keyPath, 0600);
    }

    $keyPath = storage_path('oauth-public.key');

if (!File::exists($keyPath) && env('OAUTH_PUBLIC_KEY')) {
    File::put($keyPath, env('OAUTH_PUBLIC_KEY'));
    chmod($keyPath, 0644);
}
}
}
