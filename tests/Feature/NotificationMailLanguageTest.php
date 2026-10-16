<?php

namespace Tests\Feature;

use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\CompanyInvitationNotification;
use App\Notifications\SwedishResetPassword;
use App\Notifications\SwedishVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationMailLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_reset_and_invitation_mail_uses_swedish_chrome_signed_klartfoto(): void
    {
        config(['app.name' => 'Laravel']);
        $user = User::factory()->create();
        $invitation = CompanyInvitation::factory()->for($user->company)->create([
            'invited_by' => $user->id,
        ]);

        $user->notifyNow(new SwedishVerifyEmail);
        $user->notifyNow(new SwedishResetPassword('reset-token'));

        $bodies = $this->sentMailBodies();
        $invitationHtml = (new CompanyInvitationNotification($invitation))->toMail(new \stdClass)->render()->toHtml();

        foreach ([...$bodies, $invitationHtml] as $body) {
            $this->assertStringContainsString('Med vänliga hälsningar', $body);
            $this->assertStringContainsString('Klartfoto', $body);
            $this->assertStringContainsString('Alla rättigheter förbehållna.', $body);
            $this->assertStringContainsString('Om du har problem att klicka på knappen', $body);
            $this->assertStringNotContainsString('Regards', $body);
            $this->assertStringNotContainsString('All rights reserved', $body);
            $this->assertStringNotContainsString("If you're having trouble clicking", $body);
            $this->assertStringNotContainsString('Laravel Logo', $body);
            $this->assertStringNotContainsString('>Laravel<', $body);
        }
    }

    public function test_stored_polish_locale_uses_polish_notification_chrome(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['locale' => 'pl']);
        app()->setLocale('en');

        $user->notifyNow(new SwedishVerifyEmail);

        $html = $this->sentMailBodies()[0];

        $this->assertStringContainsString('Pozdrawiamy', $html);
        $this->assertStringContainsString('Klartfoto', $html);
        $this->assertStringContainsString('Wszelkie prawa zastrzeżone.', $html);
        $this->assertStringContainsString('Jeśli masz problem z kliknięciem przycisku', $html);
        $this->assertStringNotContainsString('Regards', $html);
        $this->assertStringNotContainsString('All rights reserved', $html);
        $this->assertStringNotContainsString('Med vänliga hälsningar', $html);
    }

    public function test_mail_stays_swedish_when_the_user_has_no_stored_locale(): void
    {
        $user = User::factory()->create();
        app()->setLocale('en');

        $user->notifyNow(new SwedishResetPassword('reset-token'));

        $html = $this->sentMailBodies()[0];

        $this->assertStringContainsString('Med vänliga hälsningar', $html);
        $this->assertStringContainsString('Klartfoto', $html);
        $this->assertStringNotContainsString('Regards', $html);
        $this->assertStringNotContainsString('All rights reserved', $html);
    }

    /**
     * @return array<int, string>
     */
    private function sentMailBodies(): array
    {
        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        return $transport->messages()
            ->map(function ($message): string {
                $original = $message->getOriginalMessage();

                return (string) $original->getHtmlBody()."\n".(string) $original->getTextBody();
            })
            ->all();
    }
}
