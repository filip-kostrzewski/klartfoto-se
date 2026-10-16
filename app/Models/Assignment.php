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

#[Fillable(['customer_name', 'address', 'performed_on', 'performed_time', 'performed_end_time', 'public_token'])]
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

    public function photoPairRecords(): HasMany
    {
        return $this->hasMany(AssignmentPhotoPair::class)->orderBy('position')->orderBy('id');
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
            ->translatedFormat('j F Y \k\l. H:i');
    }

    /**
     * @return list<array{before: AssignmentPhoto|null, after: AssignmentPhoto|null, room_name: string|null, note: string|null, pair: AssignmentPhotoPair|null}>
     */
    public function photoPairs(): array
    {
        $photos = $this->relationLoaded('photos') ? $this->photos : $this->photos()->get();
        $records = $this->relationLoaded('photoPairRecords') ? $this->photoPairRecords : $this->photoPairRecords()->get();
        $pairs = [];
        $usedIds = [];

        foreach ($records as $record) {
            $members = $photos->where('assignment_photo_pair_id', $record->id);
            $before = $members->first(fn (AssignmentPhoto $photo): bool => $photo->phase === PhotoPhase::Before);
            $after = $members->first(fn (AssignmentPhoto $photo): bool => $photo->phase === PhotoPhase::After);

            if (! $before instanceof AssignmentPhoto && ! $after instanceof AssignmentPhoto) {
                continue;
            }

            foreach ([$before, $after] as $photo) {
                if ($photo instanceof AssignmentPhoto) {
                    $usedIds[] = $photo->id;
                }
            }

            $pairs[] = [
                'before' => $before instanceof AssignmentPhoto ? $before : null,
                'after' => $after instanceof AssignmentPhoto ? $after : null,
                'room_name' => $this->filledText($record->room_name),
                'note' => $this->filledText($record->note),
                'pair' => $record,
            ];
        }

        $legacy = $photos->reject(fn (AssignmentPhoto $photo): bool => in_array($photo->id, $usedIds, true));
        $before = $legacy->filter(fn (AssignmentPhoto $photo): bool => $photo->phase === PhotoPhase::Before)->values();
        $after = $legacy->filter(fn (AssignmentPhoto $photo): bool => $photo->phase === PhotoPhase::After)->values();
        $count = max($before->count(), $after->count());

        for ($index = 0; $index < $count; $index++) {
            $pairs[] = [
                'before' => $before->get($index),
                'after' => $after->get($index),
                'room_name' => null,
                'note' => null,
                'pair' => null,
            ];
        }

        return $pairs;
    }

    public function materializeLegacyPairs(): void
    {
        $legacy = array_values(array_filter(
            $this->photoPairs(),
            fn (array $pair): bool => $pair['pair'] === null,
        ));

        if ($legacy === []) {
            return;
        }

        $next = $this->photoPairRecords()->exists()
            ? ((int) $this->photoPairRecords()->max('position')) + 1
            : 0;

        foreach ($legacy as $offset => $pair) {
            $record = $this->photoPairRecords()->create([
                'position' => $next + $offset,
                'room_name' => null,
                'note' => null,
            ]);

            $pair['before']?->update(['assignment_photo_pair_id' => $record->id]);
            $pair['after']?->update(['assignment_photo_pair_id' => $record->id]);
        }

        $this->unsetRelation('photos');
        $this->unsetRelation('photoPairRecords');
    }

    private function filledText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function markLinkCopied(): void
    {
        if ($this->link_copied_at !== null) {
            return;
        }

        $this->forceFill(['link_copied_at' => now()])->save();
    }
}
