<?php

namespace App\Providers;

use App\Http\Middleware\EnsureStripeWebhookIsConfigured;
use App\Models\Company;
use App\SiteSeo;
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
        Cashier::useCustomerModel(Company::class);

        $this->app->booted(function (): void {
            $this->app->booted(function (): void {
                Route::getRoutes()->getByName('cashier.webhook')?->middleware(EnsureStripeWebhookIsConfigured::class);
            });
        });

        $baseUrl = SiteSeo::baseUrl();

        $config = [
            'app.url' => $baseUrl,
            'marketing.base_url' => $baseUrl,
            'filesystems.disks.public.url' => $baseUrl.'/storage',
        ];

        $mailDomain = (string) config('mail.mailers.smtp.local_domain');

        if ($mailDomain === '' || preg_match('/(^localhost$|^127\.|\.local$|\.test$)/i', $mailDomain) === 1) {
            $config['mail.mailers.smtp.local_domain'] = parse_url($baseUrl, PHP_URL_HOST);
        }

        config($config);

        URL::forceRootUrl($baseUrl);
        URL::forceScheme('https');
    }
}
