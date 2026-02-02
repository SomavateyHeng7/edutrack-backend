<?php

namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\{
    Curriculum,
    CurriculumCreditPool,
    SubCategoryPool,
    PoolCourseAttachment,
    CourseType,
    Course,
    AuditLog
};

class CreditPoolController extends Controller
{
    /**
     * GET /api/curricula/{curriculumId}/credit-pools
     * Get all credit pools for a curriculum
     */
    public function index($curriculumId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Chairperson access required'], 403);
            }

            $curriculum = Curriculum::findOrFail($curriculumId);

            $pools = CurriculumCreditPool::with([
                'topLevelCourseType',
                'subCategories.courseType',
                'subCategories.attachedCourses.course'
            ])
                ->where('curriculum_id', $curriculumId)
                ->orderBy('order_index')
                ->get();

            return response()->json([
                'pools' => $pools->map(function ($pool) {
                    return [
                        'id' => $pool->id,
                        'curriculumId' => $pool->curriculum_id,
                        'name' => $pool->name,
                        'topLevelCourseTypeId' => $pool->top_level_course_type_id,
                        'topLevelCourseTypeColor' => $pool->topLevelCourseType->color ?? '#6366f1',
                        'enabled' => $pool->enabled,
                        'subCategories' => $pool->subCategories->map(function ($subCat) {
                            return [
                                'id' => $subCat->id,
                                'poolId' => $subCat->pool_id,
                                'courseTypeId' => $subCat->course_type_id,
                                'courseTypeName' => $subCat->courseType->name ?? 'Unknown',
                                'courseTypeColor' => $subCat->courseType->color ?? '#6366f1',
                                'requiredCredits' => $subCat->required_credits,
                                'attachedCourses' => $subCat->attachedCourses->map(function ($attachment) {
                                    $course = $attachment->course;
                                    return [
                                        'id' => $attachment->id,
                                        'courseId' => $course->id,
                                        'code' => $course->code,
                                        'name' => $course->name,
                                        'credits' => $course->credits,
                                        'attachedAt' => $attachment->created_at->toISOString(),
                                    ];
                                }),
                                'attachedCredits' => $subCat->attachedCourses->sum(fn($a) => $a->course->credits ?? 0),
                            ];
                        }),
                        'totalRequiredCredits' => $pool->subCategories->sum('required_credits'),
                        'totalAttachedCredits' => $pool->subCategories->sum(function ($subCat) {
                            return $subCat->attachedCourses->sum(fn($a) => $a->course->credits ?? 0);
                        }),
                        'createdAt' => $pool->created_at->toISOString(),
                        'updatedAt' => $pool->updated_at->toISOString(),
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching credit pools: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'message' => 'Failed to fetch credit pools',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * POST /api/curricula/{curriculumId}/credit-pools
     * Create a new credit pool for a curriculum
     */
    public function store(Request $request, $curriculumId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Chairperson access required'], 403);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'topLevelCourseTypeId' => 'required|string|exists:course_types,id',
                'enabled' => 'sometimes|boolean',
                'subCategories' => 'sometimes|array',
                'subCategories.*.courseTypeId' => 'required|string|exists:course_types,id',
                'subCategories.*.requiredCredits' => 'required|integer|min:0',
            ]);

            $curriculum = Curriculum::findOrFail($curriculumId);

            // Check if pool already exists for this course type
            $existing = CurriculumCreditPool::where('curriculum_id', $curriculumId)
                ->where('top_level_course_type_id', $validated['topLevelCourseTypeId'])
                ->first();

            if ($existing) {
                return response()->json([
                    'error' => 'A credit pool already exists for this course type'
                ], 409);
            }

            $pool = DB::transaction(function () use ($validated, $curriculumId, $user) {
                // Get max order index
                $maxOrder = CurriculumCreditPool::where('curriculum_id', $curriculumId)
                    ->max('order_index') ?? -1;

                $pool = CurriculumCreditPool::create([
                    'curriculum_id' => $curriculumId,
                    'name' => $validated['name'],
                    'top_level_course_type_id' => $validated['topLevelCourseTypeId'],
                    'enabled' => $validated['enabled'] ?? true,
                    'order_index' => $maxOrder + 1,
                ]);

                // Create sub-categories if provided
                if (!empty($validated['subCategories'])) {
                    foreach ($validated['subCategories'] as $index => $subCat) {
                        SubCategoryPool::create([
                            'pool_id' => $pool->id,
                            'course_type_id' => $subCat['courseTypeId'],
                            'required_credits' => $subCat['requiredCredits'],
                            'order_index' => $index,
                        ]);
                    }
                }

                AuditLog::create([
                    'user_id' => $user->id,
                    'entity_type' => 'CurriculumCreditPool',
                    'entity_id' => $pool->id,
                    'action' => 'CREATE',
                    'description' => "Created credit pool '{$pool->name}' for curriculum {$curriculumId}",
                ]);

                return $pool;
            });

            // Reload with relationships
            $pool->load([
                'topLevelCourseType',
                'subCategories.courseType',
                'subCategories.attachedCourses.course'
            ]);

            return response()->json([
                'message' => 'Credit pool created successfully',
                'pool' => $this->formatPool($pool),
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => [
                    'message' => 'Validation error',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating credit pool: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'message' => 'Failed to create credit pool',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * PUT /api/curricula/{curriculumId}/credit-pools/{poolId}
     * Update a credit pool
     */
    public function update(Request $request, $curriculumId, $poolId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Chairperson access required'], 403);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'enabled' => 'sometimes|boolean',
            ]);

            $pool = CurriculumCreditPool::where('curriculum_id', $curriculumId)
                ->findOrFail($poolId);

            $pool->update($validated);

            AuditLog::create([
                'user_id' => $user->id,
                'entity_type' => 'CurriculumCreditPool',
                'entity_id' => $pool->id,
                'action' => 'UPDATE',
                'description' => "Updated credit pool '{$pool->name}'",
            ]);

            $pool->load([
                'topLevelCourseType',
                'subCategories.courseType',
                'subCategories.attachedCourses.course'
            ]);

            return response()->json([
                'message' => 'Credit pool updated successfully',
                'pool' => $this->formatPool($pool),
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating credit pool: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'message' => 'Failed to update credit pool',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * DELETE /api/curricula/{curriculumId}/credit-pools/{poolId}
     * Delete a credit pool
     */
    public function destroy($curriculumId, $poolId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Chairperson access required'], 403);
            }

            $pool = CurriculumCreditPool::where('curriculum_id', $curriculumId)
                ->findOrFail($poolId);

            $poolName = $pool->name;
            $pool->delete();

            AuditLog::create([
                'user_id' => $user->id,
                'entity_type' => 'CurriculumCreditPool',
                'entity_id' => $poolId,
                'action' => 'DELETE',
                'description' => "Deleted credit pool '{$poolName}'",
            ]);

            return response()->json([
                'message' => 'Credit pool deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting credit pool: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'message' => 'Failed to delete credit pool',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * POST /api/curricula/{curriculumId}/credit-pools/{poolId}/sub-categories
     * Add a sub-category to a pool
     */
    public function addSubCategory(Request $request, $curriculumId, $poolId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Chairperson access required'], 403);
            }

            $validated = $request->validate([
                'courseTypeId' => 'required|string|exists:course_types,id',
                'requiredCredits' => 'required|integer|min:0',
            ]);

            $pool = CurriculumCreditPool::where('curriculum_id', $curriculumId)
                ->findOrFail($poolId);

            // Check if sub-category already exists
            $existing = SubCategoryPool::where('pool_id', $poolId)
                ->where('course_type_id', $validated['courseTypeId'])
                ->first();

            if ($existing) {
                return response()->json([
                    'error' => 'This course type is already a sub-category in this pool'
                ], 409);
            }

            $maxOrder = SubCategoryPool::where('pool_id', $poolId)
                ->max('order_index') ?? -1;

            $subCategory = SubCategoryPool::create([
                'pool_id' => $poolId,
                'course_type_id' => $validated['courseTypeId'],
                'required_credits' => $validated['requiredCredits'],
                'order_index' => $maxOrder + 1,
            ]);

            $subCategory->load('courseType', 'attachedCourses.course');

            return response()->json([
                'message' => 'Sub-category added successfully',
                'subCategory' => $this->formatSubCategory($subCategory),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error adding sub-category: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'message' => 'Failed to add sub-category',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * PUT /api/curricula/{curriculumId}/credit-pools/{poolId}/sub-categories/{subCatId}
     * Update a sub-category
     */
    public function updateSubCategory(Request $request, $curriculumId, $poolId, $subCatId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Chairperson access required'], 403);
            }

            $validated = $request->validate([
                'requiredCredits' => 'sometimes|integer|min:0',
            ]);

            $subCategory = SubCategoryPool::where('pool_id', $poolId)
                ->findOrFail($subCatId);

            // Map camelCase to snake_case for model update
            if ($request->has('requiredCredits')) {
                $subCategory->required_credits = $validated['requiredCredits'];
            }

            $subCategory->save();

            return response()->json([
                'message' => 'Sub-category updated successfully',
                'subCategory' => $this->formatSubCategory($subCategory->fresh()->load('courseType', 'attachedCourses.course')),
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating sub-category: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'message' => 'Failed to update sub-category',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * POST /api/curricula/{curriculumId}/credit-pools/sub-categories/{subCatId}/attach-courses
     * Attach courses to a sub-category
     */
    public function attachCourses(Request $request, $curriculumId, $subCatId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Chairperson access required'], 403);
            }

            $validated = $request->validate([
                'courseIds' => 'required|array',
                'courseIds.*' => 'string|exists:courses,id',
            ]);

            $subCategory = SubCategoryPool::findOrFail($subCatId);

            $attached = [];
            foreach ($validated['courseIds'] as $courseId) {
                $existing = PoolCourseAttachment::where('sub_category_pool_id', $subCatId)
                    ->where('course_id', $courseId)
                    ->first();

                if (!$existing) {
                    $attachment = PoolCourseAttachment::create([
                        'sub_category_pool_id' => $subCatId,
                        'course_id' => $courseId,
                    ]);
                    $attached[] = $attachment->load('course');
                }
            }

            return response()->json([
                'message' => count($attached) . ' course(s) attached successfully',
                'attached' => collect($attached)->map(fn($a) => [
                    'id' => $a->id,
                    'courseId' => $a->course->id,
                    'code' => $a->course->code,
                    'name' => $a->course->name,
                    'credits' => $a->course->credits,
                ]),
            ]);

        } catch (\Exception $e) {
            Log::error('Error attaching courses: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'message' => 'Failed to attach courses',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * DELETE /api/curricula/{curriculumId}/credit-pools/attachments/{attachmentId}
     * Detach a course from a sub-category
     */
    public function detachCourse($curriculumId, $attachmentId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Chairperson access required'], 403);
            }

            $attachment = PoolCourseAttachment::findOrFail($attachmentId);
            $attachment->delete();

            return response()->json([
                'message' => 'Course detached successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error detaching course: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'message' => 'Failed to detach course',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Helper method to format pool data
     */
    private function formatPool($pool)
    {
        return [
            'id' => $pool->id,
            'curriculumId' => $pool->curriculum_id,
            'name' => $pool->name,
            'topLevelCourseTypeId' => $pool->top_level_course_type_id,
            'topLevelCourseTypeColor' => $pool->topLevelCourseType->color ?? '#6366f1',
            'enabled' => $pool->enabled,
            'subCategories' => $pool->subCategories->map(fn($sc) => $this->formatSubCategory($sc)),
            'totalRequiredCredits' => $pool->subCategories->sum('required_credits'),
            'totalAttachedCredits' => $pool->subCategories->sum(function ($subCat) {
                return $subCat->attachedCourses->sum(fn($a) => $a->course->credits ?? 0);
            }),
            'createdAt' => $pool->created_at->toISOString(),
            'updatedAt' => $pool->updated_at->toISOString(),
        ];
    }

    /**
     * Helper method to format sub-category data
     */
    private function formatSubCategory($subCat)
    {
        return [
            'id' => $subCat->id,
            'poolId' => $subCat->pool_id,
            'courseTypeId' => $subCat->course_type_id,
            'courseTypeName' => $subCat->courseType->name ?? 'Unknown',
            'courseTypeColor' => $subCat->courseType->color ?? '#6366f1',
            'requiredCredits' => $subCat->required_credits,
            'attachedCourses' => $subCat->attachedCourses->map(function ($attachment) {
                $course = $attachment->course;
                return [
                    'id' => $attachment->id,
                    'courseId' => $course->id,
                    'code' => $course->code,
                    'name' => $course->name,
                    'credits' => $course->credits,
                    'attachedAt' => $attachment->created_at->toISOString(),
                ];
            }),
            'attachedCredits' => $subCat->attachedCourses->sum(fn($a) => $a->course->credits ?? 0),
        ];
    }

    /**
     * PUT /api/curricula/{curriculumId}/credit-pools/reorder
     * Reorder credit pools (affects evaluation priority)
     */
    public function reorderPools(Request $request, $curriculumId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Chairperson access required'], 403);
            }

            $validated = $request->validate([
                'orderedPoolIds' => 'required|array',
                'orderedPoolIds.*' => 'integer',
            ]);

            DB::transaction(function () use ($validated, $curriculumId) {
                foreach ($validated['orderedPoolIds'] as $index => $poolId) {
                    CurriculumCreditPool::where('curriculum_id', $curriculumId)
                        ->where('id', $poolId)
                        ->update(['order_index' => $index]);
                }
            });

            return response()->json([
                'message' => 'Pools reordered successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error reordering pools: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'message' => 'Failed to reorder pools',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * DELETE /api/curricula/{curriculumId}/credit-pools/{poolId}/sub-categories/{subCatId}
     * Delete a sub-category from a pool
     */
    public function deleteSubCategory($curriculumId, $poolId, $subCatId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Chairperson access required'], 403);
            }

            $subCategory = SubCategoryPool::where('pool_id', $poolId)
                ->findOrFail($subCatId);

            $courseTypeName = $subCategory->courseType->name ?? 'Unknown';
            $subCategory->delete();

            AuditLog::create([
                'user_id' => $user->id,
                'entity_type' => 'SubCategoryPool',
                'entity_id' => $subCatId,
                'action' => 'DELETE',
                'description' => "Deleted sub-category '{$courseTypeName}' from pool",
            ]);

            return response()->json([
                'message' => 'Sub-category deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting sub-category: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'message' => 'Failed to delete sub-category',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * PUT /api/curricula/{curriculumId}/credit-pools/{poolId}/sub-categories/reorder
     * Reorder sub-categories within a pool
     */
    public function reorderSubCategories(Request $request, $curriculumId, $poolId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Chairperson access required'], 403);
            }

            $validated = $request->validate([
                'orderedSubCategoryIds' => 'required|array',
                'orderedSubCategoryIds.*' => 'integer',
            ]);

            DB::transaction(function () use ($validated, $poolId) {
                foreach ($validated['orderedSubCategoryIds'] as $index => $subCatId) {
                    SubCategoryPool::where('pool_id', $poolId)
                        ->where('id', $subCatId)
                        ->update(['order_index' => $index]);
                }
            });

            return response()->json([
                'message' => 'Sub-categories reordered successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error reordering sub-categories: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'message' => 'Failed to reorder sub-categories',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * DELETE /api/curricula/{curriculumId}/credit-pools/sub-categories/{subCatId}/courses/{courseId}
     * Detach a course from a sub-category by course ID
     */
    public function detachCourseByIds($curriculumId, $subCatId, $courseId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Chairperson access required'], 403);
            }

            $attachment = PoolCourseAttachment::where('sub_category_pool_id', $subCatId)
                ->where('course_id', $courseId)
                ->first();

            if (!$attachment) {
                return response()->json([
                    'error' => 'Course attachment not found'
                ], 404);
            }

            $attachment->delete();

            return response()->json([
                'message' => 'Course detached successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error detaching course: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'message' => 'Failed to detach course',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * GET /api/curricula/{curriculumId}/credit-pools/available-course-types
     * Get available top-level course types that haven't been used in pools yet
     */
    public function getAvailableCourseTypes(Request $request, $curriculumId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Chairperson access required'], 403);
            }

            $curriculum = Curriculum::findOrFail($curriculumId);
            $departmentId = $curriculum->department_id;

            // Get course types already used in pools
            $usedCourseTypeIds = CurriculumCreditPool::where('curriculum_id', $curriculumId)
                ->pluck('top_level_course_type_id')
                ->toArray();

            // Get all top-level course types (those without parent)
            $availableTypes = CourseType::where('department_id', $departmentId)
                ->whereNull('parent_course_type_id')
                ->whereNotIn('id', $usedCourseTypeIds)
                ->orderBy('position')
                ->get();

            return response()->json([
                'courseTypes' => $availableTypes->map(fn($type) => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'color' => $type->color,
                    'position' => $type->position,
                ]),
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching available course types: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'message' => 'Failed to fetch available course types',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * GET /api/curricula/{curriculumId}/credit-pools/{poolId}/sub-categories/{subCatId}/available-courses
     * Get available courses for a sub-category that haven't been attached yet
     */
    public function getAvailableCourses(Request $request, $curriculumId, $poolId, $subCatId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Chairperson access required'], 403);
            }

            $subCategory = SubCategoryPool::findOrFail($subCatId);
            $curriculum = Curriculum::findOrFail($curriculumId);

            // Get courses already attached to this sub-category
            $attachedCourseIds = PoolCourseAttachment::where('sub_category_pool_id', $subCatId)
                ->pluck('course_id')
                ->toArray();

            // Get courses from curriculum that match the course type and haven't been attached
            $query = $request->query('search');

            $courses = Course::whereHas('departmentCourseTypes', function ($q) use ($subCategory) {
                    $q->where('course_type_id', $subCategory->course_type_id);
                })
                ->whereNotIn('id', $attachedCourseIds)
                ->when($query, function ($q, $search) {
                    $q->where(function ($inner) use ($search) {
                        $inner->where('code', 'like', "%{$search}%")
                              ->orWhere('name', 'like', "%{$search}%");
                    });
                })
                ->orderBy('code')
                ->limit(50)
                ->get();

            return response()->json([
                'courses' => $courses->map(fn($course) => [
                    'id' => $course->id,
                    'code' => $course->code,
                    'name' => $course->name,
                    'credits' => $course->credits,
                    'description' => $course->description,
                ]),
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching available courses: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'message' => 'Failed to fetch available courses',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * GET /api/curricula/{curriculumId}/credit-pools/{poolId}/available-sub-types
     * Get available child course types for a pool's top-level course type
     */
    public function getAvailableSubTypes(Request $request, $curriculumId, $poolId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Chairperson access required'], 403);
            }

            $pool = CurriculumCreditPool::where('curriculum_id', $curriculumId)
                ->findOrFail($poolId);

            // Get course types already used as sub-categories in this pool
            $usedSubTypeIds = SubCategoryPool::where('pool_id', $poolId)
                ->pluck('course_type_id')
                ->toArray();

            // Get all child course types of the pool's top-level type
            $availableSubTypes = CourseType::where('parent_course_type_id', $pool->top_level_course_type_id)
                ->whereNotIn('id', $usedSubTypeIds)
                ->orderBy('position')
                ->get();

            // Also include the top-level type itself if not already used
            $topLevelType = CourseType::find($pool->top_level_course_type_id);
            $includeTopLevel = !in_array($pool->top_level_course_type_id, $usedSubTypeIds);

            $result = [];
            
            if ($includeTopLevel && $topLevelType) {
                $result[] = [
                    'id' => $topLevelType->id,
                    'name' => $topLevelType->name . ' (All)',
                    'color' => $topLevelType->color,
                    'position' => -1, // Put at top
                    'isTopLevel' => true,
                ];
            }

            foreach ($availableSubTypes as $type) {
                $result[] = [
                    'id' => $type->id,
                    'name' => $type->name,
                    'color' => $type->color,
                    'position' => $type->position,
                    'isTopLevel' => false,
                ];
            }

            return response()->json([
                'courseTypes' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching available sub-types: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'message' => 'Failed to fetch available sub-types',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * GET /api/curricula/{curriculumId}/credit-pools/curriculum-courses
     * Get all courses in the curriculum with their course types for pool management
     */
    public function getCurriculumCourses(Request $request, $curriculumId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Chairperson access required'], 403);
            }

            $curriculum = Curriculum::with([
                'curriculumCourses.course.departmentCourseTypes.courseType'
            ])->findOrFail($curriculumId);

            $search = $request->query('search');
            $courseTypeId = $request->query('courseTypeId');

            $courses = $curriculum->curriculumCourses
                ->map(function ($cc) {
                    $course = $cc->course;
                    $courseTypes = $course->departmentCourseTypes->map(fn($dct) => [
                        'id' => $dct->courseType->id,
                        'name' => $dct->courseType->name,
                        'color' => $dct->courseType->color,
                    ]);

                    return [
                        'id' => $course->id,
                        'code' => $course->code,
                        'name' => $course->name,
                        'credits' => $course->credits,
                        'yearLevel' => $cc->year_level,
                        'semester' => $cc->semester,
                        'courseTypes' => $courseTypes,
                    ];
                })
                ->when($search, function ($collection, $search) {
                    return $collection->filter(function ($course) use ($search) {
                        return str_contains(strtolower($course['code']), strtolower($search))
                            || str_contains(strtolower($course['name']), strtolower($search));
                    });
                })
                ->when($courseTypeId, function ($collection, $typeId) {
                    return $collection->filter(function ($course) use ($typeId) {
                        return collect($course['courseTypes'])->contains('id', $typeId);
                    });
                })
                ->values();

            return response()->json([
                'courses' => $courses,
                'total' => $courses->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching curriculum courses: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'message' => 'Failed to fetch curriculum courses',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * GET /api/curricula/{curriculumId}/credit-pools/summary
     * Get a summary of all pools with credit calculations
     */
    public function getSummary($curriculumId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Chairperson access required'], 403);
            }

            $pools = CurriculumCreditPool::with([
                'topLevelCourseType',
                'subCategories.courseType',
                'subCategories.attachedCourses.course'
            ])
                ->where('curriculum_id', $curriculumId)
                ->where('enabled', true)
                ->orderBy('order_index')
                ->get();

            $summary = [
                'pools' => [],
                'totalRequiredCredits' => 0,
                'totalAttachedCredits' => 0,
                'allPoolsSatisfied' => true,
            ];

            foreach ($pools as $pool) {
                $poolRequired = $pool->subCategories->sum('required_credits');
                $poolAttached = $pool->subCategories->sum(function ($subCat) {
                    return $subCat->attachedCourses->sum(fn($a) => $a->course->credits ?? 0);
                });

                $isSatisfied = $poolAttached >= $poolRequired;

                $summary['pools'][] = [
                    'id' => $pool->id,
                    'name' => $pool->name,
                    'color' => $pool->topLevelCourseType->color ?? '#6366f1',
                    'requiredCredits' => $poolRequired,
                    'attachedCredits' => $poolAttached,
                    'isSatisfied' => $isSatisfied,
                    'subCategoriesCount' => $pool->subCategories->count(),
                ];

                $summary['totalRequiredCredits'] += $poolRequired;
                $summary['totalAttachedCredits'] += $poolAttached;
                
                if (!$isSatisfied) {
                    $summary['allPoolsSatisfied'] = false;
                }
            }

            return response()->json($summary);

        } catch (\Exception $e) {
            Log::error('Error fetching pool summary: ' . $e->getMessage());
            return response()->json([
                'error' => [
                    'message' => 'Failed to fetch pool summary',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
}
