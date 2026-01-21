<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GraduationPortal extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'batch',
        'curriculum',
        'curriculum_id',
        'deadline',
        'status',
        'pin',
        'pin_hash',
        'accepted_formats',
        'max_file_size_mb',
        'created_by',
        'department_id',
        'closed_at',
    ];

    protected $casts = [
        'deadline' => 'datetime',
        'accepted_formats' => 'array',
        'max_file_size_mb' => 'integer',
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'pin_hash',
    ];

    // ========================
    // Relationships
    // ========================

    /**
     * Get the curriculum that this portal belongs to
     */
    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    /**
     * Get the user who created this portal
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the department this portal belongs to
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get all submissions for this portal
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(GraduationSubmission::class, 'portal_id');
    }

    /**
     * Get all audit logs for this portal
     */
    public function logs(): HasMany
    {
        return $this->hasMany(GraduationPortalLog::class, 'portal_id');
    }

    // ========================
    // PIN Methods
    // ========================

    /**
     * Set a new PIN and return the raw PIN for display
     */
    public function setPin(?string $rawPin = null): string
    {
        $rawPin = $rawPin ?? self::generatePin();
        $this->pin = $rawPin; // Store raw for display purposes
        $this->pin_hash = Hash::make($rawPin);
        $this->save();
        
        return $rawPin;
    }

    /**
     * Verify a PIN against the hash
     */
    public function verifyPin(string $pin): bool
    {
        // First try hash verification
        if ($this->pin_hash && Hash::check($pin, $this->pin_hash)) {
            return true;
        }
        
        // Fallback to plain text comparison for backwards compatibility
        if ($this->pin && $this->pin === $pin) {
            return true;
        }
        
        return false;
    }

    /**
     * Generate a random PIN
     */
    public static function generatePin(int $length = 6): string
    {
        return strtoupper(Str::random($length));
    }

    // ========================
    // Status Methods
    // ========================

    /**
     * Check if the portal is currently active
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        
        if ($this->closed_at !== null) {
            return false;
        }
        
        if ($this->deadline && $this->deadline->isPast()) {
            return false;
        }
        
        return true;
    }

    /**
     * Close the portal
     */
    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    /**
     * Reopen the portal
     */
    public function reopen(): void
    {
        $this->update([
            'status' => 'active',
            'closed_at' => null,
        ]);
    }

    // ========================
    // Scopes
    // ========================

    /**
     * Scope to get only active portals
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->whereNull('closed_at')
                     ->where(function ($q) {
                         $q->whereNull('deadline')
                           ->orWhere('deadline', '>=', now());
                     });
    }

    /**
     * Scope to filter by department
     */
    public function scopeForDepartment($query, string $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope to filter by curriculum
     */
    public function scopeForCurriculum($query, string $curriculumId)
    {
        return $query->where('curriculum_id', $curriculumId);
    }
}
