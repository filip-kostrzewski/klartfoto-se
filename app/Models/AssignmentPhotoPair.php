<?php

namespace App\Models;

use Database\Factories\AssignmentPhotoPairFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assignment_id', 'position', 'room_name', 'note'])]
class AssignmentPhotoPair extends Model
{
    /** @use HasFactory<AssignmentPhotoPairFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AssignmentPhoto::class);
    }
}
