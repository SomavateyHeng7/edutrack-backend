<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumCourseCorequisite extends Model
{
    use HasUuids;

    protected $fillable = [
        'curriculum_course_id',
        'corequisite_course_id',
    ];

    public function curriculumCourse(): BelongsTo
    {
        return $this->belongsTo(CurriculumCourse::class, 'curriculum_course_id');
    }

    public function corequisiteCourse(): BelongsTo
    {
        return $this->belongsTo(CurriculumCourse::class, 'corequisite_course_id');
    }
}
