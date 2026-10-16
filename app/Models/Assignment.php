<?php

namespace App\Models;

use App\PhotoPhase;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['customer_name', 'customer_email', 'address', 'performed_on', 'performed_time', 'performed_end_time', 'public_token'])]
#[Hidden(['customer_email'])]
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Assignment $assignment): void {
            if (! is_string($assignment->public_token) || $assignment->public_token === '') {
                $assignment->public_token = Str::random(48);
            }

            if ($assignment->company_id === null && $assignment->user_id !== null) {
                $assignment->company_id = User::query()->whereKey($assignment->user_id)->value('company_id');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'performed_on' => 'date',
            'report_shared_at' => 'datetime',
            'link_copied_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerProperty(): BelongsTo
    {
        return $this->belongsTo(CustomerProperty::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AssignmentPhoto::class)
            ->orderByRaw("case when phase = '".PhotoPhase::Before->value."' then 0 else 1 end")
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function emailAttempts(): HasMany
    {
        return $this->hasMany(AssignmentEmailAttempt::class)->orderByDesc('id');
    }

    public function places(): HasMany
    {
        return $this->hasMany(AssignmentPlace::class)->orderBy('sort_order')->orderBy('id');
    }

    public function reportVersions(): HasMany
    {
        return $this->hasMany(AssignmentReportVersion::class)->orderByDesc('version_number');
    }

    public function reportResponses(): HasMany
    {
        return $this->hasMany(AssignmentReportResponse::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function latestReportVersion(): ?AssignmentReportVersion
    {
        return $this->reportVersions()->first();
    }

    public function performedLabel(): string
    {
        $label = $this->performed_on->locale('sv')->translatedFormat('j F Y');
        $time = $this->performedTimeLabel();

        if ($time === null) {
            return $label;
        }

        return $label.' kl. '.$time;
    }

    public function performedTimeLabel(): ?string
    {
        $start = $this->clockLabel($this->performed_time);
        $end = $this->clockLabel($this->performed_end_time);

        if ($start !== null && $end !== null) {
            return $start.'–'.$end;
        }

        return $start ?? $end;
    }

    private function clockLabel(mixed $time): ?string
    {
        if (! is_string($time) || $time === '') {
            return null;
        }

        return substr($time, 0, 5);
    }

    public function reportCreatedLabel(): string
    {
        return $this->created_at
            ->timezone('Europe/Stockholm')
            ->locale('sv')
            ->translatedFormat('j F Y \\k\\l. H:i');
    }

    /**
     * @return list<array{before: AssignmentPhoto|null, after: AssignmentPhoto|null, label: string|null, customerComment: string|null, placeName: string}>
     */
    public function photoPairs(): array
    {
        if ($this->relationLoaded('places')) {
            $places = $this->places;

            if ($places->isNotEmpty() && $places->contains(fn (AssignmentPlace $place): bool => $place->photoPairs->isNotEmpty())) {
                $pairs = [];

                foreach ($places as $place) {
                    foreach ($place->photoPairs as $photoPair) {
                        $photos = $photoPair->photos->keyBy(fn (AssignmentPhoto $photo): string => $photo->phase->value);
                        $pairs[] = [
                            'label' => $photoPair->label,
                            'customerComment' => $photoPair->customer_comment,
                            'placeName' => $place->name,
                            'before' => $photos->get(PhotoPhase::Before->value),
                            'after' => $photos->get(PhotoPhase::After->value),
                        ];
                    }
                }

                return $pairs;
            }
        }

        $before = $this->photos->where('phase', PhotoPhase::Before)->values();
        $after = $this->photos->where('phase', PhotoPhase::After)->values();
        $count = max($before->count(), $after->count());
        $pairs = [];

        for ($index = 0; $index < $count; $index++) {
            $pairs[] = [
                'before' => $before->get($index),
                'after' => $after->get($index),
                'label' => null,
                'customerComment' => null,
                'placeName' => 'Övrigt',
            ];
        }

        return $pairs;
    }

    public function markLinkCopied(): void
    {
        if ($this->link_copied_at !== null) {
            return;
        }

        $this->forceFill(['link_copied_at' => now()])->save();
    }

    public function markDraftChanged(): void
    {
        $this->forceFill([
            'status' => 'draft',
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ])->save();
    }
}
