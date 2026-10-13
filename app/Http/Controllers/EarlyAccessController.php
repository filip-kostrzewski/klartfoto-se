<?php

namespace App\Http\Controllers;

use App\LocalizedUrl;
use App\Mail\EarlyAccessConfirmation;
use App\SiteSeo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class EarlyAccessController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(SiteSeo::waitlistIsOpen(), 503);

        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        if ($request->filled('company_url')) {
            return redirect()->to(LocalizedUrl::route('home', app()->getLocale(), false).'#signup')->with(
                'signup_status',
                __('marketing.form.status.honeypot'),
            );
        }

        $validator = Validator::make($request->all(), [
            'email' => ['bail', 'required', 'string', 'email', 'max:254'],
            'industry' => ['bail', 'required', 'string', 'in:cleaning,renovation,property,other'],
            'consent' => ['accepted'],
        ], [
            ...__('marketing.form.validation'),
        ]);

        if ($validator->fails()) {
            $oldInput = $request->only(['email', 'industry', 'consent']);

            foreach ($oldInput as $key => $value) {
                if (! is_string($value)) {
                    unset($oldInput[$key]);
                }
            }

            return redirect()->to(LocalizedUrl::route('home', app()->getLocale(), false).'#signup')
                ->withErrors($validator)
                ->withInput($oldInput);
        }

        $validated = $validator->validated();

        $timestamp = now();
        $created = DB::table('early_access_signups')->insertOrIgnore([
            'email' => $validated['email'],
            'industry' => $validated['industry'],
            'consented_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]) === 1;

        $mailFailed = false;

        if ($created && (bool) config('marketing.email_confirmation')) {
            try {
                Mail::to($validated['email'])
                    ->locale(app()->getLocale())
                    ->send(new EarlyAccessConfirmation);
            } catch (Throwable $exception) {
                report($exception);
                $mailFailed = true;
            }
        }

        $message = match (true) {
            ! $created => __('marketing.form.status.duplicate'),
            $mailFailed => __('marketing.form.status.mail_failed'),
            default => __('marketing.form.status.success'),
        };

        return redirect()->to(LocalizedUrl::route('home', app()->getLocale(), false).'#signup')->with('signup_status', $message);
    }
}
