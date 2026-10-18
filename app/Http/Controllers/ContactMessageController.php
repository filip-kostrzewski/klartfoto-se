<?php

namespace App\Http\Controllers;

use App\LocalizedUrl;
use App\Mail\ContactMessage;
use App\Support\FormSpam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class ContactMessageController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $locale = app()->getLocale();
        $contactUrl = LocalizedUrl::route('contact', $locale, false).'#contact-form';
        $reason = FormSpam::rejectionReason($request, 'website', ['name', 'message']);

        if ($reason !== null) {
            FormSpam::record($request, 'contact', $reason, ['name', 'email', 'company', 'message', 'website']);

            return redirect()->to($contactUrl)->with('contact_status', 'success');
        }

        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'name' => ['bail', 'required', 'string', 'max:120'],
                'email' => ['bail', 'required', 'string', 'email', 'max:254'],
                'company' => ['nullable', 'string', 'max:160'],
                'message' => ['bail', 'required', 'string', 'max:5000'],
                'website' => ['prohibited'],
            ],
            __('marketing.contact.validation.messages'),
            __('marketing.contact.validation.attributes'),
        );

        if ($validator->fails()) {
            return redirect()->to($contactUrl)
                ->withErrors($validator)
                ->withInput($this->safeOldInput($request));
        }

        $validated = $validator->validated();
        $recipient = config('klartfoto.provider_contact_email');

        try {
            if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                throw new \RuntimeException('Contact recipient is not configured.');
            }

            Mail::to($recipient)->send(new ContactMessage(
                name: $validated['name'],
                email: $validated['email'],
                company: $validated['company'] ?? null,
                messageBody: $validated['message'],
            ));
        } catch (Throwable) {
            Log::warning('Contact message delivery failed.');

            return redirect()->to($contactUrl)
                ->withInput($this->safeOldInput($request))
                ->with('contact_status', 'failed');
        }

        return redirect()->to($contactUrl)->with('contact_status', 'sent');
    }

    /**
     * @return array<string, string>
     */
    private function safeOldInput(Request $request): array
    {
        $oldInput = $request->only(['name', 'email', 'company', 'message', FormSpam::STARTED_AT_FIELD]);

        foreach ($oldInput as $key => $value) {
            if (! is_string($value)) {
                unset($oldInput[$key]);
            }
        }

        return $oldInput;
    }
}
