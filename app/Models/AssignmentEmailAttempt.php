<?php

namespace App\Models;

use Database\Factories\AssignmentEmailAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'user_id', 'assignment_report_version_id', 'recipient_email', 'status', 'sent_at', 'failed_at', 'unknown_at'])]
class AssignmentEmailAttempt extends Model
{
    /** @use HasFactory<AssignmentEmailAttemptFactory> */
    use HasFactory;

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNKNOWN = 'unknown';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'unknown_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function reportVersion(): BelongsTo
    {
        return $this->belongsTo(AssignmentReportVersion::class, 'assignment_report_version_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
