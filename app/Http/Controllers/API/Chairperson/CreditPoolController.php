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

            $subCategory->update($validated);

            return response()->json([
                'message' => 'Sub-category updated successfully',
                'subCategory' => $this->formatSubCategory($subCategory->load('courseType', 'attachedCourses.course')),
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
}
