<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasUuids;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'faculty_id',
        'department_id',
        'advisor_id',
        'gpa',
        'credits',
        'scholarship_hour',
        'reset_token',
        'reset_token_expiry',
    ];

    protected $hidden = [
        'password',
        'reset_token',
    ];
    protected $casts = [
        'password' => 'hashed',
        'reset_token_expiry' => 'datetime',
        'gpa' => 'float',
    ];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(User::class, 'advisor_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
    public function blacklists(): HasMany
    {
        return $this->hasMany(Blacklist::class, 'created_by_id');
    }

    public function concentrations(): HasMany
    {
        return $this->hasMany(Concentration::class, 'created_by_id');
    }

    public function curricula(): HasMany
    {
        return $this->hasMany(Curriculum::class, 'created_by_id');
    }

    public function courseTypeAssignments(): HasMany
    {
        return $this->hasMany(DepartmentCourseType::class, 'assigned_by_id');
    }

    public function studentCourses(): HasMany
    {
        return $this->hasMany(StudentCourse::class, 'student_id');
    }
}