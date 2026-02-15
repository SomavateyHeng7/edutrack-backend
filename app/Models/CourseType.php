<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseType extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'color',
        'faculty_id',
        'parent_course_type_id',
        'position',
        'seeded',
    ];

    protected $casts = [
        'position' => 'integer',
        'seeded' => 'boolean',
    ];

    /* =====================================================
     * Relationships
     * ===================================================== */

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function departmentCourseTypes(): HasMany
    {
        return $this->hasMany(DepartmentCourseType::class);
    }

    /**
     * Parent course type (null if root node).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(CourseType::class, 'parent_course_type_id');
    }

    /**
     * Child course types.
     */
    public function children(): HasMany
    {
        return $this->hasMany(CourseType::class, 'parent_course_type_id')
            ->orderBy('position');
    }

    /* =====================================================
     * Hierarchy Helper Methods
     * ===================================================== */

    /**
     * Get count of direct children.
     */
    public function getChildCountAttribute(): int
    {
        return $this->children()->count();
    }

    /**
     * Get count of courses using this type (via DepartmentCourseType).
     */
    public function getUsageCountAttribute(): int
    {
        return $this->departmentCourseTypes()->count();
    }

    /**
     * Collect all descendant IDs (for cycle detection).
     */
    public function getDescendantIds(): array
    {
        $ids = [];
        $this->collectDescendantIds($this, $ids);
        return $ids;
    }

    /**
     * Recursively collect descendant IDs.
     */
    private function collectDescendantIds(CourseType $node, array &$ids): void
    {
        foreach ($node->children as $child) {
            $ids[] = $child->id;
            $this->collectDescendantIds($child, $ids);
        }
    }

    /**
     * Check if the given ID is a descendant of this node.
     * Used for cycle detection when updating parentId.
     */
    public function isDescendant(string $nodeId): bool
    {
        return in_array($nodeId, $this->getDescendantIds());
    }

    /**
     * Get all ancestor IDs (for breadcrumb display).
     */
    public function getAncestorIds(): array
    {
        $ids = [];
        $current = $this->parent;
        while ($current) {
            $ids[] = $current->id;
            $current = $current->parent;
        }
        return array_reverse($ids);
    }

    /**
     * Build nested tree structure from a flat collection.
     * 
     * @param \Illuminate\Support\Collection $types All course types for a department
     * @return array Nested tree structure
     */
    public static function buildTree($types): array
    {
        $lookup = [];
        $tree = [];

        // First pass: index by ID
        foreach ($types as $type) {
            $lookup[$type->id] = [
                'id' => $type->id,
                'name' => $type->name,
                'color' => $type->color,
                'parentId' => $type->parent_course_type_id,
                'position' => $type->position,
                'usageCount' => $type->usage_count,
                'children' => [],
            ];
        }

        // Second pass: build tree
        foreach ($lookup as $id => &$node) {
            if ($node['parentId'] && isset($lookup[$node['parentId']])) {
                $lookup[$node['parentId']]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }

        // Sort children by position
        self::sortTreeByPosition($tree);

        return $tree;
    }

    /**
     * Recursively sort tree nodes by position.
     */
    private static function sortTreeByPosition(array &$nodes): void
    {
        usort($nodes, fn($a, $b) => $a['position'] <=> $b['position']);
        foreach ($nodes as &$node) {
            if (!empty($node['children'])) {
                self::sortTreeByPosition($node['children']);
            }
        }
    }
}
