<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraduationPortalLog extends Model
{
    use HasUuids;

    protected $table = 'graduation_portal_logs';
    
    public $timestamps = false;
    
    protected $fillable = [
        'portal_id',
        'action',
        'performed_by',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // ========================
    // Relationships
    // ========================

    /**
     * Get the portal this log belongs to
     */
    public function portal(): BelongsTo
    {
        return $this->belongsTo(GraduationPortal::class, 'portal_id');
    }

    /**
     * Get the user who performed the action
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    // ========================
    // Factory Methods
    // ========================

    /**
     * Create a log entry for a portal action
     */
    public static function log(
        int $portalId,
        string $action,
        ?string $performedBy = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'portal_id' => $portalId,
            'action' => $action,
            'performed_by' => $performedBy,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    // ========================
    // Action Constants
    // ========================

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_CLOSED = 'closed';
    public const ACTION_REOPENED = 'reopened';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_PIN_REGENERATED = 'pin_regenerated';
    public const ACTION_PIN_VERIFIED = 'pin_verified';
    public const ACTION_PIN_FAILED = 'pin_failed';
    public const ACTION_SUBMISSION_RECEIVED = 'submission_received';
    public const ACTION_SUBMISSION_VALIDATED = 'submission_validated';
    public const ACTION_SUBMISSION_APPROVED = 'submission_approved';
    public const ACTION_SUBMISSION_REJECTED = 'submission_rejected';
}
