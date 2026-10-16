<?php

use App\GoogleAnalytics;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: [
            GoogleAnalytics::CONSENT_COOKIE,
        ]);
        $middleware->appendToGroup('web', SetLocale::class);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('assignments.index'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request): ?RedirectResponse {
            if (! $request->routeIs('assignments.email')) {
                return null;
            }

            $assignment = $request->route('assignment');
            $assignmentKey = is_object($assignment) && method_exists($assignment, 'getKey')
                ? $assignment->getKey()
                : $assignment;

            if (! is_scalar($assignmentKey) || blank($assignmentKey)) {
                return null;
            }

            return redirect()
                ->route('assignments.show', ['assignment' => $assignmentKey])
                ->withInput($request->only(['recipient_email', 'message', 'confirm_incomplete_pairs']))
                ->with('error', __('assignments.email_rate_limit'));
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request): ?Response {
            if ($request->routeIs('verification.verify') && $exception->getStatusCode() === 403) {
                return response()->view('auth.verification-link-invalid', [], 403);
            }

            return null;
        });
    })->create();
