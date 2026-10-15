<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CompanyInvitationController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\EarlyAccessController;
use App\Http\Controllers\MarketingPageController;
use App\Http\Controllers\PublicReportController;
use App\LocalizedUrl;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:30,1');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/uppdrag', [AssignmentController::class, 'index'])->name('assignments.index');
    Route::get('/uppdrag/nytt', [AssignmentController::class, 'create'])->name('assignments.create');
    Route::post('/uppdrag', [AssignmentController::class, 'store'])->name('assignments.store');
    Route::post('/uppdrag/kom-igang/dolj', [AssignmentController::class, 'dismissOnboarding'])->name('onboarding.dismiss');
    Route::get('/uppdrag/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
    Route::post('/uppdrag/{assignment}/foton', [AssignmentController::class, 'storePhotos'])->name('assignments.photos.store');
    Route::post('/uppdrag/{assignment}/delning', [AssignmentController::class, 'share'])->name('assignments.share');
    Route::get('/installningar', [CompanySettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/installningar/profil', [CompanySettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::get('/installningar/abonnemang', [BillingController::class, 'show'])->name('settings.billing');
    Route::post('/installningar/abonnemang', [BillingController::class, 'checkout'])
        ->middleware('throttle:10,1')
        ->name('settings.billing.checkout');
    Route::post('/installningar/abonnemang/portal', [BillingController::class, 'portal'])
        ->middleware('throttle:10,1')
        ->name('settings.billing.portal');
    Route::post('/installningar/logotyp', [CompanySettingsController::class, 'storeLogo'])
        ->middleware('throttle:10,1')
        ->name('settings.logo.store');
    Route::delete('/installningar/logotyp', [CompanySettingsController::class, 'destroyLogo'])->name('settings.logo.destroy');
    Route::get('/installningar/logotyp', [CompanySettingsController::class, 'logo'])->name('settings.logo');
    Route::post('/installningar/inbjudningar', [CompanyInvitationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('invitations.store');
    Route::delete('/installningar/inbjudningar/{invitation}', [CompanyInvitationController::class, 'destroy'])->name('invitations.destroy');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/epost/skicka-igen', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::put('/epost', [EmailVerificationController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('verification.update');
});

Route::get('/epost/bekrafta/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/inbjudan/{token}', [CompanyInvitationController::class, 'create'])
    ->where('token', '[A-Za-z0-9]{32,80}')
    ->name('invitations.accept');
Route::post('/inbjudan/{token}', [CompanyInvitationController::class, 'storeAcceptance'])
    ->where('token', '[A-Za-z0-9]{32,80}')
    ->middleware('throttle:10,1')
    ->name('invitations.accept.store');

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::get('/r/{token}/logotyp', [PublicReportController::class, 'logo'])
    ->where('token', '[A-Za-z0-9]{32,80}')
    ->name('reports.logo');

Route::get('/r/{token}/foton/{photo}', [PublicReportController::class, 'photo'])
    ->where('token', '[A-Za-z0-9]{32,80}')
    ->name('reports.photos.show');

Route::get('/r/{token}', [PublicReportController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32,80}')
    ->name('reports.show');

Route::permanentRedirect('/integritet', '/integritetspolicy');
Route::permanentRedirect('/rapportmall-fore-efter', '/exempel-rapport');
Route::permanentRedirect('/sv/integritet', '/integritetspolicy');
Route::permanentRedirect('/sv/rapportmall-fore-efter', '/exempel-rapport');

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
