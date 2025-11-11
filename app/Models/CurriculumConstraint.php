<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumConstraint extends Model
{
    use HasUuids;

    protected $fillable = [
        'curriculum_id',
        'type',
        'name',
        'description',
        'is_required',
        'config',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'config' => 'array',
    ];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }
}
