<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PlannedCourse extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'planned_courses';

    protected $fillable = [
        'student_id',
        'course_id',
        'semester',
    ];

    /**
     * Get the student who planned this course
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the course details
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}
