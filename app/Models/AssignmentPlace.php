<?php

namespace App\Models;

use Database\Factories\AssignmentPlaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'sort_order'])]
class AssignmentPlace extends Model
{
    /** @use HasFactory<AssignmentPlaceFactory> */
    use HasFactory;

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function photoPairs(): HasMany
    {
        return $this->hasMany(AssignmentPhotoPair::class)->orderBy('sort_order')->orderBy('id');
    }
}
