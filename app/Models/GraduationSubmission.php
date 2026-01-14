<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraduationSubmission extends Model
{
    protected $fillable = [
        'portal_id',
        'student_id',
        'file_name',
        'file_path',
        'file_size',
        'status',
        'validation_result',
        'notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'validation_result' => 'array',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the portal this submission belongs to
     */
    public function portal(): BelongsTo
    {
        return $this->belongsTo(GraduationPortal::class);
    }

    /**
     * Get the student who made this submission
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the user who reviewed this submission
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
