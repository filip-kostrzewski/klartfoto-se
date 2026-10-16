<?php

namespace App\Actions;

use App\Mail\AssignmentReportEmail;
use App\Models\Assignment;
use App\Models\AssignmentEmailAttempt;
use App\Models\AssignmentReportVersion;
use App\Models\User;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class SendAssignmentReportEmail
{
    public const SENDING_TIMEOUT_MINUTES = 5;

    private const SENT_COOLDOWN_SECONDS = 60;

    private const UNKNOWN_COOLDOWN_MINUTES = 5;

    public function canStartAnotherAttempt(?AssignmentEmailAttempt $latestAttempt): bool
    {
        $blockReason = $this->blockReason($latestAttempt);

        if ($blockReason === 'stale_sending') {
            return $this->isReadyForManualRetry($latestAttempt);
        }

        return $blockReason === null;
    }

    public function send(
        Assignment $assignment,
        User $sender,
        string $recipientEmail,
        ?string $message,
        CreateAssignmentReportVersion $versions,
    ): ?AssignmentEmailAttempt {
        $dispatch = DB::transaction(function () use ($assignment, $sender, $recipientEmail, $versions): ?array {
            $lockedAssignment = Assignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedSender = User::query()
                ->whereKey($sender->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $lockedAssignment->company_id !== null
                && $lockedAssignment->company_id === $lockedSender->company_id,
                404,
            );

            $lockedSender->loadMissing('company');
            abort_unless($lockedSender->hasVerifiedEmail() && $lockedSender->canManageJobs(), 403);
            abort_unless($lockedAssignment->photos()->exists(), 403);

            $version = $lockedAssignment->reportVersions()->first();

            if (! $version instanceof AssignmentReportVersion) {
                $version = $versions->create($lockedAssignment, $lockedSender, preserveCurrentToken: true);
                $lockedAssignment->refresh();
            } elseif ($versions->hasUnpublishedDraftChanges($lockedAssignment, $version)) {
                throw ValidationException::withMessages([
                    'email' => 'Skapa en ny rapportversion innan du skickar ändringarna.',
                ]);
            }

            $latestAttempt = $lockedAssignment->emailAttempts()->lockForUpdate()->first();
            $blockReason = $this->blockReason($latestAttempt);

            if ($blockReason !== null) {
                if (
                    $blockReason === 'stale_sending'
                    && $latestAttempt instanceof AssignmentEmailAttempt
                    && $this->isReadyForManualRetry($latestAttempt)
                ) {
                    $this->markOutcome($latestAttempt, AssignmentEmailAttempt::STATUS_UNKNOWN, $versions);
                } else {
                    if ($blockReason === 'stale_sending' && $latestAttempt instanceof AssignmentEmailAttempt) {
                        $this->markOutcome($latestAttempt, AssignmentEmailAttempt::STATUS_UNKNOWN, $versions);
                    }

                    return null;
                }
            }

            $lockedAssignment->forceFill(['customer_email' => $recipientEmail])->save();

            $attempt = $lockedAssignment->emailAttempts()->create([
                'company_id' => $lockedAssignment->company_id,
                'user_id' => $lockedSender->id,
                'assignment_report_version_id' => $version->id,
                'recipient_email' => $recipientEmail,
                'status' => AssignmentEmailAttempt::STATUS_SENDING,
            ]);

            return ['attempt' => $attempt, 'version' => $version];
        });

        if (! is_array($dispatch) || ! $dispatch['attempt'] instanceof AssignmentEmailAttempt || ! $dispatch['version'] instanceof AssignmentReportVersion) {
            return null;
        }

        /** @var AssignmentEmailAttempt $attempt */
        $attempt = $dispatch['attempt'];
        /** @var AssignmentReportVersion $version */
        $version = $dispatch['version'];
        $snapshot = $version->snapshot;

        $assignment->loadMissing('company');

        try {
            $sentMessage = Mail::to($recipientEmail)->send(new AssignmentReportEmail(
                companyName: (string) ($snapshot['company_name'] ?? ''),
                customerName: (string) ($snapshot['customer_name'] ?? ''),
                address: (string) ($snapshot['address'] ?? ''),
                performedOn: Carbon::parse($snapshot['performed_on'] ?? now())->locale('sv')->translatedFormat('j F Y'),
                reportUrl: route('reports.show', ['token' => $version->token]),
                customerMessage: $message,
                senderEmail: $sender->email,
            ));
        } catch (TransportExceptionInterface) {
            return $this->markOutcome($attempt, AssignmentEmailAttempt::STATUS_UNKNOWN, $versions);
        } catch (Throwable) {
            return $this->markOutcome($attempt, AssignmentEmailAttempt::STATUS_FAILED, $versions);
        }

        if (! $sentMessage instanceof SentMessage) {
            return $this->markOutcome($attempt, AssignmentEmailAttempt::STATUS_FAILED, $versions);
        }

        return $this->markOutcome($attempt, AssignmentEmailAttempt::STATUS_SENT, $versions);
    }

    private function blockReason(?AssignmentEmailAttempt $latestAttempt): ?string
    {
        if ($latestAttempt === null) {
            return null;
        }

        return match ($latestAttempt->status) {
            AssignmentEmailAttempt::STATUS_SENDING => $latestAttempt->created_at === null
                || $latestAttempt->created_at->isAfter(now()->subMinutes(self::SENDING_TIMEOUT_MINUTES))
                ? 'sending'
                : 'stale_sending',
            AssignmentEmailAttempt::STATUS_SENT => $latestAttempt->created_at?->isAfter(now()->subSeconds(self::SENT_COOLDOWN_SECONDS))
                ? 'sent'
                : null,
            AssignmentEmailAttempt::STATUS_UNKNOWN => $latestAttempt->unknown_at === null
                || $latestAttempt->unknown_at->isAfter(now()->subMinutes(self::UNKNOWN_COOLDOWN_MINUTES))
                ? 'unknown'
                : null,
            default => null,
        };
    }

    private function markOutcome(
        AssignmentEmailAttempt $attempt,
        string $status,
        CreateAssignmentReportVersion $versions,
    ): AssignmentEmailAttempt {
        $now = now();

        DB::transaction(function () use ($attempt, $status, $now, $versions): void {
            $assignment = $status === AssignmentEmailAttempt::STATUS_SENT
                ? Assignment::query()->whereKey($attempt->assignment_id)->lockForUpdate()->firstOrFail()
                : null;
            $lockedAttempt = AssignmentEmailAttempt::query()
                ->whereKey($attempt->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedAttempt->forceFill([
                'status' => $status,
                'sent_at' => $status === AssignmentEmailAttempt::STATUS_SENT ? $now : null,
                'failed_at' => $status === AssignmentEmailAttempt::STATUS_FAILED ? $now : null,
                'unknown_at' => $status === AssignmentEmailAttempt::STATUS_UNKNOWN ? $now : null,
            ])->save();

            if ($status === AssignmentEmailAttempt::STATUS_SENT) {
                abort_unless($assignment instanceof Assignment, 404);
                $assignment->forceFill(['report_shared_at' => $now]);

                $version = $lockedAttempt->assignment_report_version_id === null
                    ? null
                    : AssignmentReportVersion::query()
                        ->whereKey($lockedAttempt->assignment_report_version_id)
                        ->lockForUpdate()
                        ->first();

                if ($version instanceof AssignmentReportVersion) {
                    if ($version->first_sent_at === null) {
                        $version->forceFill([
                            'status' => AssignmentReportVersion::STATUS_SENT,
                            'first_sent_at' => $now,
                            'first_sent_by_user_id' => $lockedAttempt->user_id,
                        ])->save();
                    }

                    $currentVersion = $assignment->latestReportVersion();
                    if (
                        $currentVersion instanceof AssignmentReportVersion
                        && $currentVersion->getKey() === $version->getKey()
                        && $version->token === $assignment->public_token
                        && ! $versions->hasUnpublishedDraftChanges($assignment, $version)
                    ) {
                        $assignment->forceFill(['status' => 'sent']);
                    }
                }

                $assignment->save();
            }
        });

        return $attempt->refresh();
    }

    private function isReadyForManualRetry(?AssignmentEmailAttempt $attempt): bool
    {
        return $attempt?->created_at?->lte(now()->subMinutes(
            self::SENDING_TIMEOUT_MINUTES + self::UNKNOWN_COOLDOWN_MINUTES,
        )) ?? false;
    }
}
