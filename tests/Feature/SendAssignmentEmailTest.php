<?php

namespace Tests\Feature;

use App\Actions\CreateAssignmentReportVersion;
use App\CompanyPlan;
use App\Mail\AssignmentReportEmail;
use App\Models\Assignment;
use App\Models\AssignmentEmailAttempt;
use App\Models\AssignmentReportVersion;
use App\Models\User;
use App\PhotoPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class SendAssignmentEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_owner_can_send_a_report_and_the_success_is_noted_as_shared(): void
    {
        $this->travelTo('2026-10-16 14:00:00');
        $this->useArrayMailer();
        $owner = User::factory()->create([
            'name' => 'Sara Städ',
            'email' => 'sara@example.test',
        ]);
        $assignment = $this->assignmentWithPhoto($owner, [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-15',
        ]);
        $recipient = 'anna@example.test';

        $this->actingAs($owner)
            ->from(route('assignments.show', $assignment, false))
            ->post(route('assignments.email', $assignment, false), [
                'recipient_email' => $recipient,
                'message' => 'Tack för besöket!',
            ])
            ->assertRedirect(route('assignments.show', $assignment, false))
            ->assertSessionHas('status', 'Mejlet har lämnats till e-postleverantören.');

        $attempt = AssignmentEmailAttempt::query()->sole();
        $this->assertSame(AssignmentEmailAttempt::STATUS_SENT, $attempt->status);
        $this->assertSame($recipient, $attempt->recipient_email);
        $this->assertNotNull($attempt->sent_at);
        $this->assertSame($recipient, $assignment->fresh()->customer_email);
        $this->assertNotNull($assignment->fresh()->report_shared_at);
        $this->assertNull($assignment->fresh()->link_copied_at);

        $this->get(route('reports.show', ['token' => $assignment->public_token], false))
            ->assertOk()
            ->assertDontSee($recipient, false);

        $transportMessages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $transportMessages);
        $email = $transportMessages->first()->getOriginalMessage();
        $this->assertSame('noreply@klartfoto.se', $email->getFrom()[0]->getAddress());
        $this->assertSame('Klartfoto', $email->getFrom()[0]->getName());
        $this->assertSame($recipient, $email->getTo()[0]->getAddress());
        $this->assertSame('sara@example.test', $email->getReplyTo()[0]->getAddress());
        $this->assertStringContainsString(
            route('reports.show', ['token' => $assignment->public_token]),
            $email->getHtmlBody(),
        );
        $this->assertStringContainsString('Storgatan 1, 111 22 Stockholm', $email->getHtmlBody());
        $this->assertStringContainsString('Anna Andersson', $email->getHtmlBody());
        $this->assertStringContainsString('Sara Städ', $email->getHtmlBody());
        $this->assertStringContainsString('15 oktober 2026', $email->getHtmlBody());
        $this->assertStringContainsString('Tack för besöket!', $email->getTextBody());
        $this->assertSame([], $email->getAttachments());
        $this->assertDoesNotMatchRegularExpression('/<img\b/i', $email->getHtmlBody());

        $this->actingAs($owner)
            ->from(route('assignments.show', $assignment, false))
            ->post(route('assignments.email', $assignment, false), [
                'recipient_email' => $recipient,
                'message' => 'Tack för besöket!',
            ])
            ->assertRedirect(route('assignments.show', $assignment, false))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('assignment_email_attempts', 1);
        $this->assertCount(1, Mail::mailer()->getSymfonyTransport()->messages());
    }

    public function test_mail_fake_null_result_is_not_marked_sent_and_keeps_form_input(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['email' => 'sender@example.test']);
        $assignment = $this->assignmentWithPhoto($owner, [
            'customer_name' => '<img src=x onerror=alert(1)>',
            'address' => 'Storgatan 2',
        ]);
        $recipient = 'customer@example.test';
        $message = '![image](https://example.invalid/pixel) <script>alert(1)</script>';

        $this->actingAs($owner)
            ->from(route('assignments.show', $assignment, false))
            ->post(route('assignments.email', $assignment, false), [
                'recipient_email' => $recipient,
                'message' => $message,
            ])
            ->assertRedirect(route('assignments.show', $assignment, false))
            ->assertSessionHas('error', 'Mejlet kunde inte skickas. Försök igen eller kopiera rapportlänken och skicka den själv.')
            ->assertSessionHas('_old_input.recipient_email', $recipient)
            ->assertSessionHas('_old_input.message', $message);

        $attempt = AssignmentEmailAttempt::query()->sole();
        $this->assertSame(AssignmentEmailAttempt::STATUS_FAILED, $attempt->status);
        $this->assertNotNull($attempt->failed_at);
        $this->assertSame($recipient, $assignment->fresh()->customer_email);
        $this->assertNull($assignment->fresh()->report_shared_at);

        Mail::assertSent(AssignmentReportEmail::class, function (AssignmentReportEmail $mail) use ($recipient, $message): bool {
            $this->assertTrue($mail->hasTo($recipient));
            $this->assertSame('sender@example.test', $mail->envelope()->replyTo[0]->address);
            $mail->assertSeeInHtml('<img src=x onerror=alert(1)>');
            $mail->assertSeeInHtml('<script>alert(1)</script>');
            $mail->assertDontSeeInHtml('<script>alert(1)</script>', false);
            $mail->assertSeeInText($message);

            return true;
        });
    }

    public function test_a_later_resend_uses_the_same_report_version_and_public_link(): void
    {
        $this->travelTo('2026-10-16 14:00:00');
        $this->useArrayMailer();
        $owner = User::factory()->create();
        $assignment = $this->assignmentWithPhoto($owner);

        foreach ([1, 2] as $sendNumber) {
            if ($sendNumber === 2) {
                $this->travel(61)->seconds();
            }

            $this->actingAs($owner)
                ->from(route('assignments.show', $assignment, false))
                ->post(route('assignments.email', $assignment, false), [
                    'recipient_email' => 'customer@example.test',
                    'message' => 'Here is the report.',
                ])
                ->assertRedirect(route('assignments.show', $assignment, false))
                ->assertSessionHas('status');
        }

        $attempts = AssignmentEmailAttempt::query()->orderBy('id')->get();
        $versionId = $assignment->reportVersions()->sole()->id;
        $transportMessages = Mail::mailer()->getSymfonyTransport()->messages();

        $this->assertCount(2, $attempts);
        $this->assertSame([$versionId, $versionId], $attempts->pluck('assignment_report_version_id')->all());
        $this->assertCount(2, $transportMessages);

        foreach ($transportMessages as $transportMessage) {
            $this->assertStringContainsString(
                route('reports.show', ['token' => $assignment->public_token]),
                $transportMessage->getOriginalMessage()->getHtmlBody(),
            );
        }
    }

    public function test_successful_old_version_email_does_not_mark_a_newer_report_as_sent(): void
    {
        $this->travelTo('2026-10-16 14:00:00');
        $this->useArrayMailer();
        $owner = User::factory()->create();
        $assignment = $this->assignmentWithPhoto($owner);
        $versions = app(CreateAssignmentReportVersion::class);
        $firstVersion = $versions->create($assignment, $owner, preserveCurrentToken: true);

        Event::listen(MessageSent::class, function (MessageSent $event) use ($assignment, $owner, $versions): void {
            $assignment->forceFill(['customer_name' => 'Changed while the provider accepted V1'])->save();
            $versions->create($assignment->fresh(), $owner);
        });

        $this->actingAs($owner)
            ->from(route('assignments.show', $assignment, false))
            ->post(route('assignments.email', $assignment, false), [
                'recipient_email' => 'customer@example.test',
            ])
            ->assertRedirect(route('assignments.show', $assignment, false))
            ->assertSessionHas('status');

        $secondVersion = $assignment->latestReportVersion();
        $attempt = AssignmentEmailAttempt::query()->sole();

        $this->assertNotSame($firstVersion->id, $secondVersion?->id);
        $this->assertSame($firstVersion->id, $attempt->assignment_report_version_id);
        $this->assertSame(AssignmentEmailAttempt::STATUS_SENT, $attempt->status);
        $this->assertNotNull($attempt->sent_at);
        $this->assertSame(AssignmentReportVersion::STATUS_SENT, $firstVersion->fresh()->status);
        $this->assertNotNull($firstVersion->fresh()->first_sent_at);
        $this->assertSame(AssignmentReportVersion::STATUS_READY, $secondVersion?->status);
        $this->assertSame($secondVersion?->token, $assignment->fresh()->public_token);
        $this->assertSame('ready', $assignment->fresh()->status);
        $this->assertNotNull($assignment->fresh()->report_shared_at);
    }

    public function test_transport_timeout_is_recorded_as_unknown_without_logging_private_details(): void
    {
        $owner = User::factory()->create();
        $assignment = $this->assignmentWithPhoto($owner);
        $pendingMail = Mockery::mock(PendingMail::class);
        $pendingMail->shouldReceive('send')
            ->once()
            ->with(Mockery::type(AssignmentReportEmail::class))
            ->andThrow(new TransportException('private transport detail'));
        Mail::shouldReceive('to')
            ->once()
            ->with('customer@example.test')
            ->andReturn($pendingMail);

        $response = $this->actingAs($owner)
            ->from(route('assignments.show', $assignment, false))
            ->post(route('assignments.email', $assignment, false), [
                'recipient_email' => 'customer@example.test',
                'message' => 'Please call me.',
            ])
            ->assertRedirect(route('assignments.show', $assignment, false))
            ->assertSessionHas('error', 'Det gick inte att bekräfta om mejlet skickades. Kontrollera med mottagaren innan du skickar igen.')
            ->assertSessionHas('_old_input.message', 'Please call me.');

        $this->assertStringNotContainsString('private transport detail', serialize($response->getSession()->all()));

        $attempt = AssignmentEmailAttempt::query()->sole();
        $this->assertSame(AssignmentEmailAttempt::STATUS_UNKNOWN, $attempt->status);
        $this->assertNotNull($attempt->unknown_at);
        $this->assertNull($attempt->sent_at);
    }

    public function test_stale_sending_is_read_as_unknown_without_get_mutation_and_allows_manual_retry_after_cooldown(): void
    {
        $this->travelTo('2026-10-16 15:00:00');
        $this->useArrayMailer();
        $owner = User::factory()->create();
        $assignment = $this->assignmentWithPhoto($owner);
        $previousAttempt = $assignment->emailAttempts()->create([
            'company_id' => $owner->company_id,
            'user_id' => $owner->id,
            'recipient_email' => 'first@example.test',
            'status' => AssignmentEmailAttempt::STATUS_SENDING,
        ]);
        $previousAttempt->forceFill([
            'created_at' => now()->subMinutes(11),
            'updated_at' => now()->subMinutes(11),
        ])->save();

        $this->actingAs($owner)->get(route('assignments.show', $assignment, false))
            ->assertOk()
            ->assertSee('Okänd status', false)
            ->assertSee('name="recipient_email"', false);

        $this->assertSame(AssignmentEmailAttempt::STATUS_SENDING, $previousAttempt->fresh()->status);
        $this->assertDatabaseCount('assignment_email_attempts', 1);

        $this->actingAs($owner)
            ->from(route('assignments.show', $assignment, false))
            ->post(route('assignments.email', $assignment, false), [
                'recipient_email' => 'second@example.test',
                'message' => 'Sending manually after checking.',
            ])
            ->assertRedirect(route('assignments.show', $assignment, false))
            ->assertSessionHas('status');

        $this->assertSame(AssignmentEmailAttempt::STATUS_UNKNOWN, $previousAttempt->fresh()->status);
        $this->assertSame(AssignmentEmailAttempt::STATUS_SENT, $assignment->emailAttempts()->firstOrFail()->status);
        $this->assertDatabaseCount('assignment_email_attempts', 2);
    }

    public function test_invalid_recipient_or_oversized_message_does_not_write_or_send(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $assignment = $this->assignmentWithPhoto($owner);

        $this->actingAs($owner)
            ->from(route('assignments.show', $assignment, false))
            ->post(route('assignments.email', $assignment, false), [
                'recipient_email' => 'not-an-email',
                'message' => str_repeat('a', 1001),
            ])
            ->assertRedirect(route('assignments.show', $assignment, false))
            ->assertSessionHasErrors(['recipient_email', 'message']);

        $this->assertDatabaseCount('assignment_email_attempts', 0);
        $this->assertNull($assignment->fresh()->customer_email);
        Mail::assertNothingOutgoing();
    }

    public function test_guest_foreign_company_unverified_and_expired_users_cannot_send(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $assignment = $this->assignmentWithPhoto($owner);

        $this->post(route('assignments.email', $assignment, false), [
            'recipient_email' => 'customer@example.test',
        ])->assertRedirect(route('login'));

        $otherCompanyUser = User::factory()->create();
        $this->actingAs($otherCompanyUser)
            ->post(route('assignments.email', $assignment, false), [
                'recipient_email' => 'customer@example.test',
            ])
            ->assertNotFound();

        $unverifiedOwner = User::factory()->unverified()->create();
        $unverifiedAssignment = $this->assignmentWithPhoto($unverifiedOwner);
        $this->actingAs($unverifiedOwner)
            ->from(route('assignments.show', $unverifiedAssignment, false))
            ->post(route('assignments.email', $unverifiedAssignment, false), [
                'recipient_email' => 'customer@example.test',
            ])
            ->assertRedirect(route('assignments.show', $unverifiedAssignment, false))
            ->assertSessionHasErrors('email');

        $expiredOwner = User::factory()->expiredTrial()->create();
        $expiredAssignment = $this->assignmentWithPhoto($expiredOwner);
        $this->actingAs($expiredOwner)
            ->from(route('assignments.show', $expiredAssignment, false))
            ->post(route('assignments.email', $expiredAssignment, false), [
                'recipient_email' => 'customer@example.test',
            ])
            ->assertRedirect(route('assignments.show', $expiredAssignment, false))
            ->assertSessionHasErrors([
                'email' => 'Din åtkomst till nya uppdrag har pausats. Ni kan fortfarande öppna befintliga rapporter.',
            ]);

        $this->assertDatabaseCount('assignment_email_attempts', 0);
        $this->assertNull($unverifiedAssignment->fresh()->customer_email);
        $this->assertNull($expiredAssignment->fresh()->customer_email);
        Mail::assertNothingOutgoing();
    }

    public function test_assignment_without_a_photo_cannot_send(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->from(route('assignments.show', $assignment, false))
            ->post(route('assignments.email', $assignment, false), [
                'recipient_email' => 'customer@example.test',
            ])
            ->assertRedirect(route('assignments.show', $assignment, false))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('assignment_email_attempts', 0);
        $this->assertNull($assignment->fresh()->customer_email);
        Mail::assertNothingOutgoing();
    }

    public function test_paid_start_owner_can_send_but_member_cannot_and_team_member_can(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $assignment = $this->assignmentWithPhoto($owner);
        $member = User::factory()->memberOf($owner->company)->create();
        $owner->company->forceFill([
            'plan' => CompanyPlan::Start,
            'trial_ends_at' => now()->subDay(),
            'paid_until' => now()->addMonth(),
        ])->save();

        $this->actingAs($member)
            ->from(route('assignments.show', $assignment, false))
            ->post(route('assignments.email', $assignment, false), [
                'recipient_email' => 'customer@example.test',
            ])
            ->assertRedirect(route('assignments.show', $assignment, false))
            ->assertSessionHasErrors('email');

        $this->actingAs($owner)
            ->from(route('assignments.show', $assignment, false))
            ->post(route('assignments.email', $assignment, false), [
                'recipient_email' => 'customer@example.test',
            ])
            ->assertRedirect(route('assignments.show', $assignment, false));

        $this->assertDatabaseCount('assignment_email_attempts', 1);
        $this->assertSame('customer@example.test', $assignment->fresh()->customer_email);

        $teamOwner = User::factory()->create();
        $teamOwner->company->forceFill([
            'plan' => CompanyPlan::Team,
            'trial_ends_at' => now()->subDay(),
            'paid_until' => now()->addMonth(),
        ])->save();
        $teamMember = User::factory()->memberOf($teamOwner->company)->create();
        $teamAssignment = $this->assignmentWithPhoto($teamOwner);

        $this->actingAs($teamMember)
            ->from(route('assignments.show', $teamAssignment, false))
            ->post(route('assignments.email', $teamAssignment, false), [
                'recipient_email' => 'team-customer@example.test',
            ])
            ->assertRedirect(route('assignments.show', $teamAssignment, false));

        $this->assertDatabaseCount('assignment_email_attempts', 2);
        Mail::assertSent(AssignmentReportEmail::class, 2);
    }

    public function test_company_and_user_hourly_limits_prevent_mail_bursts(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $owner->company->forceFill([
            'plan' => CompanyPlan::Team,
            'paid_until' => now()->addMonth(),
            'trial_ends_at' => now()->subDay(),
        ])->save();
        $assignment = $this->assignmentWithPhoto($owner);
        $members = collect([$owner]);

        for ($index = 0; $index < 4; $index++) {
            $members->push(User::factory()->memberOf($owner->company)->create());
        }

        foreach ($members as $member) {
            for ($attempt = 0; $attempt < 4; $attempt++) {
                $this->actingAs($member)
                    ->post(route('assignments.email', $assignment, false), [
                        'recipient_email' => 'customer@example.test',
                    ])
                    ->assertRedirect();
            }
        }

        $anotherMember = User::factory()->memberOf($owner->company)->create();
        $this->actingAs($anotherMember)
            ->from(route('assignments.show', $assignment, false))
            ->post(route('assignments.email', $assignment, false), [
                'recipient_email' => 'customer@example.test',
            ])
            ->assertRedirect(route('assignments.show', $assignment, false))
            ->assertSessionHas('error', 'Du har skickat för många rapporter, försök igen om en stund.');

        $this->actingAs($anotherMember)
            ->get(route('assignments.show', $assignment, false))
            ->assertOk()
            ->assertSee('Du har skickat för många rapporter, försök igen om en stund.', false)
            ->assertDontSee('Too Many Requests', false);

        $this->assertDatabaseCount('assignment_email_attempts', 20);
        Mail::assertSent(AssignmentReportEmail::class, 20);
    }

    public function test_sending_publishes_and_emails_the_current_assignment_when_the_latest_version_misses_the_after_photo(): void
    {
        $this->useArrayMailer();
        Storage::fake('local');
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $url = route('assignments.photos.store', $assignment, false);

        $this->actingAs($owner)->post($url, [
            'place_name' => 'Hall',
            'before_photos' => [UploadedFile::fake()->image('fore.jpg', 40, 40)],
            'before_upload_ids' => [Str::uuid()->toString()],
        ], ['Accept' => 'application/json'])->assertOk();

        $firstVersion = $assignment->reportVersions()->sole();
        $this->assertNull($firstVersion->snapshot['places'][0]['pairs'][0]['after']);
        $pairId = $firstVersion->snapshot['places'][0]['pairs'][0]['id'];

        $this->actingAs($owner)->post($url, [
            'assignment_photo_pair_id' => $pairId,
            'after_photos' => [UploadedFile::fake()->image('efter.jpg', 40, 40)],
            'after_upload_ids' => [Str::uuid()->toString()],
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(1, $assignment->reportVersions()->count());
        $this->actingAs($owner)
            ->get(route('assignments.show', $assignment, false))
            ->assertOk()
            ->assertSee('Inte aktuell', false)
            ->assertDontSee('Klar att dela', false);

        $this->actingAs($owner)
            ->from(route('assignments.show', $assignment, false))
            ->post(route('assignments.email', $assignment, false), [
                'recipient_email' => 'kund@example.test',
            ])
            ->assertRedirect(route('assignments.show', $assignment, false))
            ->assertSessionHas('status');

        $sentVersion = $assignment->fresh()->latestReportVersion();
        $attempt = AssignmentEmailAttempt::query()->sole();
        $this->assertNotNull($sentVersion);
        $this->assertSame(2, $sentVersion->version_number);
        $this->assertNotNull($sentVersion->snapshot['places'][0]['pairs'][0]['after']);
        $this->assertSame($sentVersion->id, $attempt->assignment_report_version_id);
        $this->assertStringContainsString(
            route('reports.show', ['token' => $sentVersion->token]),
            Mail::mailer()->getSymfonyTransport()->messages()->first()->getOriginalMessage()->getHtmlBody(),
        );
        $this->get(route('reports.show', ['token' => $sentVersion->token], false))
            ->assertOk()
            ->assertDontSee('Ingen efterbild', false);
    }

    public function test_incomplete_pair_is_not_ready_to_share_and_sending_asks_for_confirmation(): void
    {
        $this->useArrayMailer();
        Storage::fake('local');
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();

        $this->actingAs($owner)->post(route('assignments.photos.store', $assignment, false), [
            'place_name' => 'Kök',
            'before_photos' => [UploadedFile::fake()->image('fore.jpg', 40, 40)],
            'before_upload_ids' => [Str::uuid()->toString()],
        ], ['Accept' => 'application/json'])->assertOk();

        $this->actingAs($owner)
            ->get(route('assignments.show', $assignment, false))
            ->assertOk()
            ->assertSee('Saknar efterbild', false)
            ->assertSee('saknar efterbild', false)
            ->assertSee('Minst ett rum har en förebild men saknar efterbild.', false)
            ->assertSee('name="confirm_incomplete_pairs"', false)
            ->assertDontSee('Klar att dela', false)
            ->assertDontSee('klar att dela', false);

        $this->actingAs($owner)
            ->from(route('assignments.show', $assignment, false))
            ->post(route('assignments.email', $assignment, false), [
                'recipient_email' => 'kund@example.test',
            ])
            ->assertRedirect(route('assignments.show', $assignment, false))
            ->assertSessionHasErrors([
                'confirm_incomplete_pairs' => 'Bekräfta att rapporten ska skickas utan efterbild.',
            ]);

        $this->assertSame(0, AssignmentEmailAttempt::query()->count());

        $this->actingAs($owner)
            ->from(route('assignments.show', $assignment, false))
            ->post(route('assignments.email', $assignment, false), [
                'recipient_email' => 'kund@example.test',
                'confirm_incomplete_pairs' => '1',
            ])
            ->assertRedirect(route('assignments.show', $assignment, false))
            ->assertSessionHas('status', 'Mejlet har lämnats till e-postleverantören.');

        $this->assertSame(1, AssignmentEmailAttempt::query()->count());
        $this->assertSame(1, $assignment->reportVersions()->count());
    }

    public function test_polish_missing_after_status_is_translated(): void
    {
        app()->setLocale('pl');

        $this->assertSame('Brak zdjęcia po', __('assignments.missing_after'));
        $this->assertSame('brak zdjęcia po', __('assignments.missing_after_inline'));
        $this->assertSame(
            'Co najmniej jedno pomieszczenie ma zdjęcie przed, ale nie ma zdjęcia po. Klient zobaczy wtedy, że zdjęcia po brakuje.',
            __('assignments.incomplete_warning'),
        );
        $this->assertSame(
            'Wysłano za dużo raportów. Spróbuj ponownie za chwilę.',
            __('assignments.email_rate_limit'),
        );
    }

    private function assignmentWithPhoto(User $owner, array $attributes = []): Assignment
    {
        $assignment = Assignment::factory()->for($owner)->create($attributes);
        $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => 'assignments/'.$assignment->id.'/before.jpg',
            'sort_order' => 0,
        ]);

        return $assignment;
    }

    private function useArrayMailer(): void
    {
        config([
            'app.url' => 'https://klartfoto.test',
            'mail.default' => 'array',
            'mail.from.address' => 'noreply@klartfoto.se',
            'mail.from.name' => 'Klartfoto',
        ]);
        URL::forceRootUrl('https://klartfoto.test');
        URL::forceScheme('https');
        Mail::purge();
    }
}
