<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubCategoryPool extends Model
{
    protected $fillable = [
        'pool_id',
        'course_type_id',
        'required_credits',
        'order_index',
    ];

    protected $casts = [
        'required_credits' => 'integer',
        'order_index' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the parent credit pool
     */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(CurriculumCreditPool::class, 'pool_id');
    }

    /**
     * Get the course type (sub-category)
     */
    public function courseType(): BelongsTo
    {
        return $this->belongsTo(CourseType::class, 'course_type_id');
    }

    /**
     * Get all attached courses
     */
    public function attachedCourses(): HasMany
    {
        return $this->hasMany(PoolCourseAttachment::class, 'sub_category_pool_id');
    }
}
