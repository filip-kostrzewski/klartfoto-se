<?php

namespace App\Models;

use Database\Factories\AssignmentReportResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'assignment_id', 'assignment_report_version_id', 'type', 'response_key', 'place_index', 'pair_index', 'message', 'read_at'])]
class AssignmentReportResponse extends Model
{
    /** @use HasFactory<AssignmentReportResponseFactory> */
    use HasFactory;

    public const TYPE_RECEIVED = 'received';

    public const TYPE_COMMENT = 'comment';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'place_index' => 'integer',
            'pair_index' => 'integer',
            'read_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function reportVersion(): BelongsTo
    {
        return $this->belongsTo(AssignmentReportVersion::class, 'assignment_report_version_id');
    }
}
