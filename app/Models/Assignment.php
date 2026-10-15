<?php

namespace App\Models;

use App\PhotoPhase;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['customer_name', 'address', 'performed_on', 'public_token'])]
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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AssignmentPhoto::class)
            ->orderByRaw('case when phase = ? then 0 else 1 end', [PhotoPhase::Before->value])
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return list<array{before: AssignmentPhoto|null, after: AssignmentPhoto|null}>
     */
    public function photoPairs(): array
    {
        $before = $this->photos->where('phase', PhotoPhase::Before)->values();
        $after = $this->photos->where('phase', PhotoPhase::After)->values();
        $count = max($before->count(), $after->count());
        $pairs = [];

        for ($index = 0; $index < $count; $index++) {
            $pairs[] = [
                'before' => $before->get($index),
                'after' => $after->get($index),
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
}
