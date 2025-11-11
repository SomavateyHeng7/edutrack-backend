<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Course extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'credits',
        'credit_hours',
        'description',
        'requires_permission',
        'summer_only',
        'requires_senior_standing',
        'min_credit_threshold',
        'is_active',
    ];

    protected $casts = [
        'requires_permission' => 'boolean',
        'summer_only' => 'boolean',
        'requires_senior_standing' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function curriculumCourses(): HasMany
    {
        return $this->hasMany(CurriculumCourse::class);
    }
     public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_prerequisites', 'course_id', 'prerequisite_id');
    }

    public function dependentCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_prerequisites', 'prerequisite_id', 'course_id');
    }

    public function corequisites(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_corequisites', 'course_id', 'corequisite_id');
    }

    public function dependentCorequisites(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_corequisites', 'corequisite_id', 'course_id');
    }

    public function blacklistCourses(): HasMany
    {
        return $this->hasMany(BlacklistCourse::class);
    }

    public function concentrationCourses(): HasMany
    {
        return $this->hasMany(ConcentrationCourse::class);
    }
     public function departmentCourseTypes(): HasMany
    {
        return $this->hasMany(DepartmentCourseType::class);
    }

    public function studentCourses(): HasMany
    {
        return $this->hasMany(StudentCourse::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}