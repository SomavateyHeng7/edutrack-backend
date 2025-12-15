<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumCoursePrerequisite extends Model
{
    use HasUuids;

    protected $fillable = [
        'curriculum_course_id',
        'prerequisite_course_id',
    ];

    public function curriculumCourse(): BelongsTo
    {
        return $this->belongsTo(CurriculumCourse::class, 'curriculum_course_id');
    }

    public function prerequisiteCourse(): BelongsTo
    {
        return $this->belongsTo(CurriculumCourse::class, 'prerequisite_course_id');
    }
}
