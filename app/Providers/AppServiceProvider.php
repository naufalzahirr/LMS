<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('assessment-start', fn (Request $request) => Limit::perMinute(10)->by(
            $this->rateLimitKey($request, 'assignment'),
        ));

        RateLimiter::for('assessment-answer', fn (Request $request) => Limit::perMinute(120)->by(
            $this->rateLimitKey($request, 'attempt'),
        ));

        RateLimiter::for('assessment-submit', fn (Request $request) => Limit::perMinute(10)->by(
            $this->rateLimitKey($request, 'attempt'),
        ));
    }

    private function rateLimitKey(Request $request, string $parameter): string
    {
        $routeValue = $request->route($parameter);
        $resource = $routeValue instanceof Model ? $routeValue->getKey() : $routeValue;

        return $request->user()?->getAuthIdentifier().'|'.$resource;
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
