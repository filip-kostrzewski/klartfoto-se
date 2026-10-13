<?php

use App\Http\Controllers\EarlyAccessController;
use App\Http\Controllers\MarketingPageController;
use App\LocalizedUrl;
use Illuminate\Support\Facades\Route;

$pageActions = [
    'home' => 'home',
    'cleaning' => 'cleaning',
    'renovation' => 'renovation',
    'property' => 'property',
    'report' => 'sampleReport',
    'privacy' => 'privacy',
];

foreach (config('marketing.locales') as $locale => $metadata) {
    $prefix = $locale === 'sv' ? '' : $locale;

    Route::prefix($prefix)->name("site.{$locale}.")->group(function () use ($locale, $pageActions): void {
        foreach ($pageActions as $page => $action) {
            $slug = config("marketing.page_slugs.{$locale}.{$page}");
            $path = $slug === '' ? '/' : $slug;

            Route::get($path, [MarketingPageController::class, $action])->name($page);
        }

        $signupSlug = config("marketing.page_slugs.{$locale}.signup");

        Route::post($signupSlug, [EarlyAccessController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('signup');
    });
}

Route::get('/sitemap.xml', [MarketingPageController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [MarketingPageController::class, 'robots'])->name('robots');

Route::get('/sv', fn () => redirect('/', 301));
foreach (config('marketing.page_slugs.sv') as $page => $slug) {
    if ($page !== 'home' && $page !== 'signup') {
        Route::get("/sv/{$slug}", fn () => redirect("/{$slug}", 301));
    }
}

$legacyPolishPaths = [
    '/dla-firm-sprzatajacych' => 'cleaning',
    '/dla-ekip-remontowych' => 'renovation',
    '/dla-zarzadcow-apartamentow' => 'property',
    '/wzor-raportu-przed-po' => 'report',
    '/polityka-prywatnosci' => 'privacy',
];

foreach ($legacyPolishPaths as $legacyPath => $page) {
    Route::get($legacyPath, function () use ($page) {
        $target = LocalizedUrl::route($page, 'pl', false);

        return redirect($target.($page === 'home' ? '#signup' : ''), 301);
    });
}

Route::fallback([MarketingPageController::class, 'notFound']);
