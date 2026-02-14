<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ScheduleNotification extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'schedule_notifications';

    protected $fillable = [
        'user_id',
        'department_id',
        'curriculum_id',
        'email',
        'is_active',
        'last_notified_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_notified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user associated with this notification
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the department
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the curriculum
     */
    public function curriculum()
    {
        return $this->belongsTo(Curriculum::class, 'curriculum_id');
    }
}
