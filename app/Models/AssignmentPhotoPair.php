<?php

namespace App\Models;

use Database\Factories\AssignmentPhotoPairFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['label', 'customer_comment', 'internal_note', 'sort_order'])]
#[Hidden(['internal_note'])]
class AssignmentPhotoPair extends Model
{
    /** @use HasFactory<AssignmentPhotoPairFactory> */
    use HasFactory;

    public function place(): BelongsTo
    {
        return $this->belongsTo(AssignmentPlace::class, 'assignment_place_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AssignmentPhoto::class, 'assignment_photo_pair_id')->orderBy('id');
    }
}
