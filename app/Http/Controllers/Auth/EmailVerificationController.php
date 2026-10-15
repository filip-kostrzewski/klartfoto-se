<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAccountEmailRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::query()->find($id);
        abort_if($user === null, 404);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $redirect = $request->user() === null
            ? redirect()->route('login')
            : redirect()->route('assignments.index');

        return $redirect->with('status', 'E-postadressen är bekräftad.');
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if ($user->hasVerifiedEmail()) {
            return back()->with('status', 'E-postadressen är redan bekräftad.');
        }

        $this->sendVerification($user);

        return back()->with('status', 'Vi har skickat en ny länk till '.$user->email.'.');
    }

    public function update(UpdateAccountEmailRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $email = $request->string('account_email')->toString();

        if (strcasecmp($email, $user->email) === 0 && $user->hasVerifiedEmail()) {
            return back()->with('status', 'E-postadressen är redan bekräftad.');
        }

        $user->forceFill([
            'email' => $email,
            'email_verified_at' => null,
        ])->save();
        $this->sendVerification($user);

        return back()->with('status', 'E-postadressen är uppdaterad. Bekräfta den via mejlet.');
    }

    private function sendVerification(User $user): void
    {
        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
