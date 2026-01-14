<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GraduationPortal extends Model
{
    protected $fillable = [
        'name',
        'description',
        'batch',
        'curriculum',
        'curriculum_id',
        'deadline',
        'status',
        'pin',
        'accepted_formats',
        'created_by',
        'department_id',
    ];

    protected $casts = [
        'deadline' => 'date',
        'accepted_formats' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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
}
