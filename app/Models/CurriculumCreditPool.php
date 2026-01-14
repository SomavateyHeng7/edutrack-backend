<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumCreditPool extends Model
{
    protected $fillable = [
        'curriculum_id',
        'name',
        'top_level_course_type_id',
        'enabled',
        'order_index',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'order_index' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the curriculum this pool belongs to
     */
    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    /**
     * Get the top-level course type
     */
    public function topLevelCourseType(): BelongsTo
    {
        return $this->belongsTo(CourseType::class, 'top_level_course_type_id');
    }

    /**
     * Get all sub-category pools
     */
    public function subCategories(): HasMany
    {
        return $this->hasMany(SubCategoryPool::class, 'pool_id');
    }
}
