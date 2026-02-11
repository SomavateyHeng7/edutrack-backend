<?php

namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use App\Models\TentativeSchedule;
use App\Models\TentativeScheduleCourse;
use App\Models\Course;
use App\Models\Curriculum;
use App\Http\Controllers\API\Student\ScheduleNotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TentativeScheduleController extends Controller
{
    /**
     * Get list of tentative schedules
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            // Build query
            $query = TentativeSchedule::with(['curriculum', 'courses']);
            
            // Filter by chairperson's department
            if ($user->role === 'CHAIRPERSON' && $user->department_id) {
                $query->where('department_id', $user->department_id);
            }
            
            // Search filter
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('semester', 'like', "%{$search}%")
                      ->orWhere('batch', 'like', "%{$search}%");
                });
            }
            
            // Pagination
            $perPage = $request->get('limit', 20);
            $schedules = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            // Format schedule data
            $formattedSchedules = $schedules->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'name' => $schedule->name,
                    'semester' => $schedule->semester,
                    'version' => $schedule->version,
                    'department' => $schedule->department_name,
                    'batch' => $schedule->batch,
                    'coursesCount' => $schedule->courses->count(),
                    'isPublished' => $schedule->is_published,
                    'createdAt' => $schedule->created_at->toISOString(),
                    'updatedAt' => $schedule->updated_at->toISOString(),
                    'curriculum' => $schedule->curriculum ? [
                        'id' => $schedule->curriculum->id,
                        'name' => $schedule->curriculum->name,
                        'year' => $schedule->curriculum->year,
                    ] : null,
                ];
            });
            
            return response()->json([
                'schedules' => $formattedSchedules,
                'pagination' => [
                    'total' => $schedules->total(),
                    'page' => $schedules->currentPage(),
                    'limit' => $schedules->perPage(),
                    'totalPages' => $schedules->lastPage(),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching tentative schedules: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to fetch tentative schedules',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Create a new tentative schedule
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'semester' => 'required|string|max:100',
                'version' => 'required|string|max:50',
                'department' => 'nullable|string|max:255',
                'batch' => 'nullable|string|max:100',
                'curriculumId' => 'nullable|exists:curricula,id',
                'courses' => 'required|array',
                'courses.*.courseId' => 'required|exists:courses,id',
                'courses.*.section' => 'nullable|string|max:50',
                'courses.*.days' => 'nullable|array',
                'courses.*.days.*' => 'string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'courses.*.time' => 'nullable|string|max:100',
                'courses.*.instructor' => 'nullable|string|max:255',
                'courses.*.seatLimit' => 'nullable|integer|min:1',
            ]);
            
            $user = $request->user();
            
            DB::beginTransaction();
            
            // Create tentative schedule
            $schedule = TentativeSchedule::create([
                'name' => $request->name,
                'semester' => $request->semester,
                'version' => $request->version,
                'version_timestamp' => now(),
                'department_id' => $user->department_id,
                'department_name' => $request->department,
                'batch' => $request->batch,
                'curriculum_id' => $request->curriculumId,
                'created_by' => $user->id,
            ]);
            
            // Add courses to schedule
            foreach ($request->courses as $courseData) {
                TentativeScheduleCourse::create([
                    'tentative_schedule_id' => $schedule->id,
                    'course_id' => $courseData['courseId'],
                    'section' => $courseData['section'] ?? null,
                    'days' => $courseData['days'] ?? null,
                    'time' => $courseData['time'] ?? null,
                    'instructor' => $courseData['instructor'] ?? null,
                    'seat_limit' => $courseData['seatLimit'] ?? null,
                ]);
            }
            
            DB::commit();
            
            // Load relationships
            $schedule->load(['curriculum', 'courses.course']);
            
            return response()->json([
                'message' => 'Tentative schedule created successfully',
                'schedule' => $this->formatScheduleResponse($schedule),
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'error' => [
                    'message' => 'Validation error',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating tentative schedule: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to create tentative schedule',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Get a specific tentative schedule
     * 
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        try {
            $schedule = TentativeSchedule::with(['curriculum', 'courses.course'])
                ->findOrFail($id);
            
            // Verify access
            $user = $request->user();
            if ($user->role === 'CHAIRPERSON' && 
                $user->department_id !== $schedule->department_id) {
                return response()->json([
                    'error' => ['message' => 'Access denied']
                ], 403);
            }
            
            return response()->json([
                'schedule' => $this->formatScheduleResponse($schedule),
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => ['message' => 'Tentative schedule not found']
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching tentative schedule: ' . $e->getMessage(), [
                'scheduleId' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to fetch tentative schedule',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Update a tentative schedule
     * 
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $schedule = TentativeSchedule::findOrFail($id);
            
            // Verify access
            $user = $request->user();
            if ($user->role === 'CHAIRPERSON' && 
                $user->department_id !== $schedule->department_id) {
                return response()->json([
                    'error' => ['message' => 'Access denied']
                ], 403);
            }
            
            $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'semester' => 'sometimes|required|string|max:100',
                'version' => 'sometimes|required|string|max:50',
                'department' => 'nullable|string|max:255',
                'batch' => 'nullable|string|max:100',
                'curriculumId' => 'nullable|exists:curricula,id',
                'courses' => 'sometimes|array',
                'courses.*.courseId' => 'required|exists:courses,id',
                'courses.*.section' => 'nullable|string|max:50',
                'courses.*.days' => 'nullable|array',
                'courses.*.days.*' => 'string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'courses.*.time' => 'nullable|string|max:100',
                'courses.*.instructor' => 'nullable|string|max:255',
                'courses.*.seatLimit' => 'nullable|integer|min:1',
            ]);
            
            DB::beginTransaction();
            
            // Update schedule
            $schedule->update([
                'name' => $request->input('name', $schedule->name),
                'semester' => $request->input('semester', $schedule->semester),
                'version' => $request->input('version', $schedule->version),
                'version_timestamp' => now(),
                'department_name' => $request->input('department', $schedule->department_name),
                'batch' => $request->input('batch', $schedule->batch),
                'curriculum_id' => $request->input('curriculumId', $schedule->curriculum_id),
            ]);
            
            // Update courses if provided
            if ($request->has('courses')) {
                // Delete existing courses
                TentativeScheduleCourse::where('tentative_schedule_id', $schedule->id)->delete();
                
                // Add new courses
                foreach ($request->courses as $courseData) {
                    TentativeScheduleCourse::create([
                        'tentative_schedule_id' => $schedule->id,
                        'course_id' => $courseData['courseId'],
                        'section' => $courseData['section'] ?? null,
                        'days' => $courseData['days'] ?? null,
                        'time' => $courseData['time'] ?? null,
                        'instructor' => $courseData['instructor'] ?? null,
                        'seat_limit' => $courseData['seatLimit'] ?? null,
                    ]);
                }
            }
            
            DB::commit();
            
            // Load relationships
            $schedule->load(['curriculum', 'courses.course']);
            
            return response()->json([
                'message' => 'Tentative schedule updated successfully',
                'schedule' => $this->formatScheduleResponse($schedule),
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'error' => [
                    'message' => 'Validation error',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'error' => ['message' => 'Tentative schedule not found']
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating tentative schedule: ' . $e->getMessage(), [
                'scheduleId' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to update tentative schedule',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Delete a tentative schedule
     * 
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        try {
            $schedule = TentativeSchedule::findOrFail($id);
            
            // Verify access
            $user = $request->user();
            if ($user->role === 'CHAIRPERSON' && 
                $user->department_id !== $schedule->department_id) {
                return response()->json([
                    'error' => ['message' => 'Access denied']
                ], 403);
            }
            
            DB::beginTransaction();
            
            // Delete associated courses
            TentativeScheduleCourse::where('tentative_schedule_id', $schedule->id)->delete();
            
            // Delete schedule
            $schedule->delete();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Tentative schedule deleted successfully',
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'error' => ['message' => 'Tentative schedule not found']
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting tentative schedule: ' . $e->getMessage(), [
                'scheduleId' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to delete tentative schedule',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Toggle publish status of a tentative schedule
     * 
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function togglePublish(Request $request, $id)
    {
        try {
            $schedule = TentativeSchedule::findOrFail($id);
            
            // Verify access
            $user = $request->user();
            if ($user->role === 'CHAIRPERSON' && 
                $user->department_id !== $schedule->department_id) {
                return response()->json([
                    'error' => ['message' => 'Access denied']
                ], 403);
            }
            
            // Toggle publish status
            $schedule->is_published = !$schedule->is_published;
            $schedule->save();
            
            $status = $schedule->is_published ? 'published' : 'unpublished';
            
            // Notify subscribers if publishing
            if ($schedule->is_published && $schedule->is_active) {
                ScheduleNotificationController::notifySubscribers($schedule);
            }
            
            return response()->json([
                'message' => "Tentative schedule {$status} successfully",
                'schedule' => [
                    'id' => $schedule->id,
                    'isPublished' => $schedule->is_published,
                ],
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => ['message' => 'Tentative schedule not found']
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error toggling publish status: ' . $e->getMessage(), [
                'scheduleId' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to toggle publish status',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Toggle active status of a tentative schedule (only one active per department)
     * 
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleActive(Request $request, $id)
    {
        try {
            $schedule = TentativeSchedule::findOrFail($id);
            
            // Verify access
            $user = $request->user();
            if ($user->role === 'CHAIRPERSON' && 
                $user->department_id !== $schedule->department_id) {
                return response()->json([
                    'error' => ['message' => 'Access denied']
                ], 403);
            }
            
            DB::beginTransaction();
            
            if (!$schedule->is_active) {
                // Deactivate all other schedules in the same department
                TentativeSchedule::where('department_id', $schedule->department_id)
                    ->where('id', '!=', $id)
                    ->update(['is_active' => false]);
                
                // Activate this schedule
                $schedule->is_active = true;
                
                // Notify subscribers about active schedule
                if ($schedule->is_published) {
                    ScheduleNotificationController::notifySubscribers($schedule);
                }
            } else {
                // Deactivate this schedule
                $schedule->is_active = false;
            }
            
            $schedule->save();
            DB::commit();
            
            $status = $schedule->is_active ? 'activated' : 'deactivated';
            
            return response()->json([
                'message' => "Tentative schedule {$status} successfully",
                'schedule' => [
                    'id' => $schedule->id,
                    'isActive' => $schedule->is_active,
                ],
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'error' => ['message' => 'Tentative schedule not found']
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error toggling active status: ' . $e->getMessage(), [
                'scheduleId' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to toggle active status',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Format schedule response
     * 
     * @param TentativeSchedule $schedule
     * @return array
     */
    private function formatScheduleResponse($schedule)
    {
        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'semester' => $schedule->semester,
            'version' => $schedule->version,
            'versionTimestamp' => $schedule->version_timestamp,
            'department' => $schedule->department_name,
            'batch' => $schedule->batch,
            'isPublished' => $schedule->is_published,
            'isActive' => $schedule->is_active ?? false,
            'createdAt' => $schedule->created_at->toISOString(),
            'updatedAt' => $schedule->updated_at->toISOString(),
            'curriculum' => $schedule->curriculum ? [
                'id' => $schedule->curriculum->id,
                'name' => $schedule->curriculum->name,
                'year' => $schedule->curriculum->year,
            ] : null,
            'courses' => $schedule->courses->map(function ($scheduleCourse) {
                return [
                    'id' => $scheduleCourse->id,
                    'courseId' => $scheduleCourse->course_id,
                    'code' => $scheduleCourse->course->code,
                    'name' => $scheduleCourse->course->name,
                    'credits' => $scheduleCourse->course->credits,
                    'description' => $scheduleCourse->course->description,
                    'section' => $scheduleCourse->section,
                    'days' => $scheduleCourse->days,
                    'time' => $scheduleCourse->time,
                    'instructor' => $scheduleCourse->instructor,
                    'seatLimit' => $scheduleCourse->seat_limit,
                ];
            }),
        ];
    }

    /**
     * Get published tentative schedules (Student accessible)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function publishedSchedules(Request $request)
    {
        try {
            // Build query - only published schedules
            $query = TentativeSchedule::with(['curriculum', 'courses', 'department'])
                ->where('is_published', true);
            
            // Department filter
            if ($request->has('department_id')) {
                $query->where('department_id', $request->department_id);
            }
            
            // Search filter
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('semester', 'like', "%{$search}%")
                      ->orWhere('batch', 'like', "%{$search}%");
                });
            }
            
            // Pagination
            $perPage = $request->get('limit', 20);
            $schedules = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            // Format schedule data
            $formattedSchedules = $schedules->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'name' => $schedule->name,
                    'semester' => $schedule->semester,
                    'year' => $schedule->year ?? '',
                    'version' => $schedule->version,
                    'department' => $schedule->department_name,
                    'departmentId' => $schedule->department_id,
                    'batch' => $schedule->batch,
                    'isPublished' => true,
                    'coursesCount' => $schedule->courses->count(),
                    'createdAt' => $schedule->created_at->toISOString(),
                    'updatedAt' => $schedule->updated_at->toISOString(),
                    'curriculum' => $schedule->curriculum ? [
                        'id' => $schedule->curriculum->id,
                        'name' => $schedule->curriculum->name,
                        'year' => $schedule->curriculum->year,
                    ] : null,
                    'curriculumName' => $schedule->curriculum ? $schedule->curriculum->name : null,
                    'curriculumYear' => $schedule->curriculum ? $schedule->curriculum->year : null,
                ];
            });
            
            return response()->json([
                'schedules' => $formattedSchedules,
                'pagination' => [
                    'total' => $schedules->total(),
                    'page' => $schedules->currentPage(),
                    'limit' => $schedules->perPage(),
                    'totalPages' => $schedules->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching published schedules: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching published schedules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific published tentative schedule (Student accessible)
     * 
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showPublished($id)
    {
        try {
            $schedule = TentativeSchedule::with(['curriculum', 'courses.course'])
                ->where('id', $id)
                ->where('is_published', true)
                ->first();
            
            if (!$schedule) {
                return response()->json([
                    'message' => 'Published schedule not found'
                ], 404);
            }
            
            Log::info('Schedule found:', ['id' => $schedule->id, 'courses_count' => $schedule->courses->count()]);
            
            // Format detailed schedule data with course information
            $formattedSchedule = [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'semester' => $schedule->semester,
                'year' => $schedule->year ?? '',
                'version' => $schedule->version,
                'department' => $schedule->department_name,
                'batch' => $schedule->batch,
                'isPublished' => true,
                'createdAt' => $schedule->created_at->toISOString(),
                'updatedAt' => $schedule->updated_at->toISOString(),
                'curriculumName' => $schedule->curriculum ? $schedule->curriculum->name : null,
                'curriculumYear' => $schedule->curriculum ? $schedule->curriculum->year : null,
                'courses' => $schedule->courses->map(function ($scheduleCourse) {
                    $days = null;
                    $timeStart = null;
                    $timeEnd = null;
                    
                    // Days is already an array from model casting
                    $days = $scheduleCourse->days;
                    
                    // Parse time if available
                    if ($scheduleCourse->time && str_contains($scheduleCourse->time, '-')) {
                        $timeParts = explode('-', $scheduleCourse->time);
                        $timeStart = trim($timeParts[0]);
                        $timeEnd = trim($timeParts[1] ?? '');
                    }
                    
                    // Get first day if multiple days
                    $day = null;
                    if (is_array($days) && count($days) > 0) {
                        $day = $days[0];
                    }
                    
                    return [
                        'id' => $scheduleCourse->id,
                        'course' => [
                            'id' => $scheduleCourse->course->id,
                            'code' => $scheduleCourse->course->code,
                            'title' => $scheduleCourse->course->name,
                            'credits' => $scheduleCourse->course->credits,
                        ],
                        'section' => $scheduleCourse->section,
                        'day' => $day,
                        'days' => $days,
                        'timeStart' => $timeStart,
                        'timeEnd' => $timeEnd,
                        'time' => $scheduleCourse->time,
                        'room' => $scheduleCourse->room ?? 'TBA',
                        'instructor' => $scheduleCourse->instructor,
                        'capacity' => $scheduleCourse->seat_limit ?? 0,
                        'enrolled' => 0, // Default to 0 for now
                        'courseType' => $scheduleCourse->course_type ?? 'Core',
                    ];
                }),
            ];
            
            return response()->json([
                'schedule' => $formattedSchedule
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching published schedule: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching published schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

