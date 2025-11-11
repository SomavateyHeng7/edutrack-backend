<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'entity_type',
        'entity_id',
        'action',
        'changes',
        'description',
        'curriculum_id',
        'course_id',
        'concentration_id',
        'blacklist_id',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
    public function concentration(): BelongsTo
    {
        return $this->belongsTo(Concentration::class);
    }

    public function blacklist(): BelongsTo
    {
        return $this->belongsTo(Blacklist::class);
    }
}
