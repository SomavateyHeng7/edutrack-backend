<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoolCourseAttachment extends Model
{
    protected $fillable = [
        'sub_category_pool_id',
        'course_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the sub-category pool
     */
    public function subCategoryPool(): BelongsTo
    {
        return $this->belongsTo(SubCategoryPool::class, 'sub_category_pool_id');
    }

    /**
     * Get the course
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}
