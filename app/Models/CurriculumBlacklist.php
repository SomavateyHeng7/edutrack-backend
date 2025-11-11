<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumBlacklist extends Model
{
    use HasUuids;

    protected $fillable = [
        'curriculum_id',
        'blacklist_id',
    ];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function blacklist(): BelongsTo
    {
        return $this->belongsTo(Blacklist::class);
    }
}
