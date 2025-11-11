<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Concentration extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'department_id',
        'created_by_id',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(ConcentrationCourse::class);
    }

    public function curriculumConcentrations(): HasMany
    {
        return $this->hasMany(CurriculumConcentration::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
