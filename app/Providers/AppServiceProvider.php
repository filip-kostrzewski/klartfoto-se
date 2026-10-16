<?php

namespace App\Providers;

use App\Http\Middleware\EnsureStripeWebhookIsConfigured;
use App\Models\Company;
use App\SiteSeo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

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
        RateLimiter::for('assignment-email', function (Request $request): array {
            $user = $request->user();

            return [
                Limit::perHour(5)->by('assignment-email-user:'.($user?->getAuthIdentifier() ?? $request->ip())),
                Limit::perHour(20)->by('assignment-email-company:'.($user?->company_id ?? $request->ip())),
            ];
        });

        RateLimiter::for('contact-message', function (Request $request): Limit {
            return Limit::perHour(5)->by('contact-message:'.($request->ip() ?? 'unknown'));
        });

        Cashier::useCustomerModel(Company::class);

        $this->app->booted(function (): void {
            $this->app->booted(function (): void {
                Route::getRoutes()->getByName('cashier.webhook')?->middleware(EnsureStripeWebhookIsConfigured::class);
            });
        });

        $applicationUrl = rtrim((string) config('app.url', SiteSeo::PUBLIC_BASE_URL), '/');
        $canonicalBaseUrl = SiteSeo::baseUrl();

        $config = [
            'marketing.base_url' => $canonicalBaseUrl,
            'filesystems.disks.public.url' => $applicationUrl.'/storage',
        ];

        $mailDomain = (string) config('mail.mailers.smtp.local_domain');

        if ($mailDomain === '' || preg_match('/(^localhost$|^127\.|\.local$|\.test$)/i', $mailDomain) === 1) {
            $config['mail.mailers.smtp.local_domain'] = parse_url($applicationUrl, PHP_URL_HOST);
        }

        config($config);

        URL::forceRootUrl($applicationUrl);

        $host = parse_url($applicationUrl, PHP_URL_HOST);

        if (! is_string($host) || preg_match('/(^localhost$|^127\.|\.local$|\.test$)/i', $host) !== 1) {
            URL::forceScheme('https');
        }
    }
}
