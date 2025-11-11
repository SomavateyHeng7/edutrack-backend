<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConcentrationCourse extends Model
{
    use HasUuids;

    protected $fillable = [
        'concentration_id',
        'course_id',
    ];

    public function concentration(): BelongsTo
    {
        return $this->belongsTo(Concentration::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
