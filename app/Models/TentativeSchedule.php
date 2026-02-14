<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TentativeSchedule extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tentative_schedules';

    protected $fillable = [
        'name',
        'semester',
        'version',
        'version_timestamp',
        'department_id',
        'department_name',
        'batch',
        'curriculum_id',
        'created_by',
        'is_published',
        'is_active',
    ];

    protected $casts = [
        'version_timestamp' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_published' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the curriculum associated with this schedule
     */
    public function curriculum()
    {
        return $this->belongsTo(Curriculum::class, 'curriculum_id');
    }

    /**
     * Get the courses in this schedule
     */
    public function courses()
    {
        return $this->hasMany(TentativeScheduleCourse::class, 'tentative_schedule_id');
    }

    /**
     * Get the department
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the user who created this schedule
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
