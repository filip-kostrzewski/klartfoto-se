<?php

namespace App\Http\Controllers\Auth;

use App\Actions\CreateCompanyAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'googleEnabled' => GoogleAuthController::configured(),
        ]);
    }

    public function store(RegisterRequest $request, CreateCompanyAccount $accounts): RedirectResponse
    {
        $user = $accounts->handle(
            $request->string('company_name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            ownerName: $request->string('name')->toString(),
        );

        Auth::login($user);
        $request->session()->regenerate();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()
            ->route('assignments.index')
            ->with('toast', 'Välkommen! Ditt konto är skapat.');
    }
}
