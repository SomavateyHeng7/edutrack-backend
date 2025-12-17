<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumCourse extends Model
{
    use HasUuids;

    protected $fillable = [
        'curriculum_id',
        'course_id',
        'is_required',
        'semester',
        'year',
        'position',
        'override_requires_permission',
        'override_summer_only',
        'override_requires_senior_standing',
        'override_min_credit_threshold',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'override_requires_permission' => 'boolean',
        'override_summer_only' => 'boolean',
        'override_requires_senior_standing' => 'boolean',
    ];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function curriculumPrerequisites(): HasMany
    {
        return $this->hasMany(CurriculumCoursePrerequisite::class, 'curriculum_course_id');
    }

    public function dependentCurriculumCourses(): HasMany
    {
        return $this->hasMany(CurriculumCoursePrerequisite::class, 'prerequisite_course_id');
    }

    public function curriculumCorequisites(): HasMany
    {
        return $this->hasMany(CurriculumCourseCorequisite::class, 'curriculum_course_id');
    }

    public function dependentCurriculumCorequisites(): HasMany
    {
        return $this->hasMany(CurriculumCourseCorequisite::class, 'corequisite_course_id');
    }
}
