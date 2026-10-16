<?php

namespace App\Models;

use Database\Factories\AssignmentReportVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['version_number', 'token', 'status', 'snapshot', 'snapshot_hash', 'created_by_user_id', 'first_sent_at', 'first_sent_by_user_id'])]
class AssignmentReportVersion extends Model
{
    public const STATUS_READY = 'ready';

    public const STATUS_SENT = 'sent';

    /** @use HasFactory<AssignmentReportVersionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'first_sent_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function firstSentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'first_sent_by_user_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(AssignmentReportVersionAsset::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AssignmentReportResponse::class, 'assignment_report_version_id')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function markSent(?User $sender = null): void
    {
        $firstSentAt = $this->first_sent_at ?? now();

        $this->forceFill([
            'status' => self::STATUS_SENT,
            'first_sent_at' => $firstSentAt,
            'first_sent_by_user_id' => $this->first_sent_by_user_id ?? $sender?->id,
        ])->save();
    }

    public function photoAssets(): HasMany
    {
        return $this->assets()->where('type', AssignmentReportVersionAsset::TYPE_PHOTO);
    }

    public function logoAsset(): ?AssignmentReportVersionAsset
    {
        return $this->assets()->where('type', AssignmentReportVersionAsset::TYPE_LOGO)->first();
    }

    /**
     * @return list<array{name: string, pairs: list<array{label: string|null, before: int|null, after: int|null}>}>
     */
    public function photoPlaces(): array
    {
        $places = [];

        foreach ($this->snapshot['places'] ?? [] as $place) {
            $pairs = [];

            foreach ($place['pairs'] ?? [] as $pair) {
                $pairs[] = [
                    'label' => $pair['label'] ?? null,
                    'before' => isset($pair['before']) ? (int) $pair['before'] : null,
                    'after' => isset($pair['after']) ? (int) $pair['after'] : null,
                ];
            }

            $places[] = [
                'name' => (string) ($place['name'] ?? 'Övrigt'),
                'pairs' => $pairs,
            ];
        }

        return $places;
    }
}
