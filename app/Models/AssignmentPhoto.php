<?php

namespace App\Models;

use App\PhotoPhase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['phase', 'path', 'sort_order'])]
class AssignmentPhoto extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phase' => PhotoPhase::class,
            'sort_order' => 'integer',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
