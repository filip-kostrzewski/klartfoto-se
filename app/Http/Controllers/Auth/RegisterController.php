<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\TrialPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'googleEnabled' => filled(config('services.google.client_id')),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $request->string('company_name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'trial_ends_at' => TrialPeriod::endsAt(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('assignments.index');
    }
}
