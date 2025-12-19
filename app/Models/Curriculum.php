<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Curriculum extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'year',
        'version',
        'description',
        'total_credits_required',
        'start_id',
        'end_id',
        'is_active',
        'department_id',
        'faculty_id',
        'created_by_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function curriculumCourses(): HasMany
    {
        return $this->hasMany(CurriculumCourse::class);
    }

    public function electiveRules(): HasMany
    {
        return $this->hasMany(ElectiveRule::class);
    }

    public function curriculumConcentrations(): HasMany
    {
        return $this->hasMany(CurriculumConcentration::class);
    }

    public function curriculumBlacklists(): HasMany
    {
        return $this->hasMany(CurriculumBlacklist::class);
    }

    // Get all blacklists assigned to this curriculum through the pivot table
    public function blacklists()
    {
        return $this->hasManyThrough(
            Blacklist::class,
            CurriculumBlacklist::class,
            'curriculum_id',  // Foreign key on curriculum_blacklists table
            'id',             // Foreign key on blacklists table
            'id',             // Local key on curricula table
            'blacklist_id'    // Local key on curriculum_blacklists table
        );
    }

    public function curriculumConstraints(): HasMany
    {
        return $this->hasMany(CurriculumConstraint::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
