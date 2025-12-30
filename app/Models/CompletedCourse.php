<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CompletedCourse extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'student_courses'; // Using existing table

    protected $fillable = [
        'student_id',
        'course_id',
        'grade',
        'semester',
        'year',
    ];

    /**
     * Get the student who completed this course
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
