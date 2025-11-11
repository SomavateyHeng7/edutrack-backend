<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'code',
        'faculty_id',
    ];
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function curricula(): HasMany
    {
        return $this->hasMany(Curriculum::class);
    }

    public function courseTypes(): HasMany
    {
        return $this->hasMany(CourseType::class);
    }

    public function concentrations(): HasMany
    {
        return $this->hasMany(Concentration::class);
    }

    public function blacklists(): HasMany
    {
        return $this->hasMany(Blacklist::class);
    }

    public function departmentCourseTypes(): HasMany
    {
        return $this->hasMany(DepartmentCourseType::class);
    }
}