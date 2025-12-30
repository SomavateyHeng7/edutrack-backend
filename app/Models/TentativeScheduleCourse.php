<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TentativeScheduleCourse extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tentative_schedule_courses';

    protected $fillable = [
        'tentative_schedule_id',
        'course_id',
        'section',
        'days',
        'time',
        'instructor',
        'seat_limit',
    ];

    protected $casts = [
        'seat_limit' => 'integer',
    ];

    /**
     * Get the tentative schedule this course belongs to
     */
    public function schedule()
    {
        return $this->belongsTo(TentativeSchedule::class, 'tentative_schedule_id');
    }

    /**
     * Get the course details
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}
