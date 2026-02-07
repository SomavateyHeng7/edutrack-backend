<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraduationNotification extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'portal_id',
        'title',
        'message',
        'data',
        'read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========================
    // Notification Types
    // ========================

    public const TYPE_NEW_SUBMISSION = 'new_submission';
    public const TYPE_SUBMISSION_VALIDATED = 'submission_validated';
    public const TYPE_PORTAL_DEADLINE_APPROACHING = 'portal_deadline_approaching';

    // ========================
    // Relationships
    // ========================

    /**
     * Get the user this notification belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the portal this notification is related to
     */
    public function portal(): BelongsTo
    {
        return $this->belongsTo(GraduationPortal::class, 'portal_id');
    }

    // ========================
    // Scopes
    // ========================

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('read', false);
    }

    /**
     * Scope for read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('read', true);
    }

    /**
     * Scope for specific user
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for specific portal
     */
    public function scopeForPortal($query, int $portalId)
    {
        return $query->where('portal_id', $portalId);
    }

    /**
     * Scope for specific type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ========================
    // Helper Methods
    // ========================

    /**
     * Mark this notification as read
     */
    public function markAsRead(): void
    {
        $this->update([
            'read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark this notification as unread
     */
    public function markAsUnread(): void
    {
        $this->update([
            'read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Create a new submission notification for a user
     */
    public static function createNewSubmissionNotification(
        string $userId,
        int $portalId,
        string $portalName,
        string $studentIdentifier,
        string $submissionId,
        int $courseCount
    ): self {
        return self::create([
            'user_id' => $userId,
            'type' => self::TYPE_NEW_SUBMISSION,
            'portal_id' => $portalId,
            'title' => 'New Graduation Submission',
            'message' => "New submission from {$studentIdentifier} in portal \"{$portalName}\" with {$courseCount} courses.",
            'data' => [
                'submission_id' => $submissionId,
                'student_identifier' => $studentIdentifier,
                'course_count' => $courseCount,
                'portal_name' => $portalName,
            ],
            'read' => false,
        ]);
    }

    /**
     * Create notifications for all chairpersons/advisors in a department
     */
    public static function notifyDepartmentStaff(
        string $departmentId,
        int $portalId,
        string $portalName,
        string $studentIdentifier,
        string $submissionId,
        int $courseCount
    ): int {
        $users = User::where('department_id', $departmentId)
            ->whereIn('role', ['CHAIRPERSON', 'ADVISOR', 'chairperson', 'advisor'])
            ->get();

        $count = 0;
        foreach ($users as $user) {
            self::createNewSubmissionNotification(
                $user->id,
                $portalId,
                $portalName,
                $studentIdentifier,
                $submissionId,
                $courseCount
            );
            $count++;
        }

        return $count;
    }
}
