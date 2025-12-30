<?php

namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use App\Models\TentativeSchedule;
use App\Models\TentativeScheduleCourse;
use App\Models\Course;
use App\Models\Curriculum;
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
                    'days' => isset($courseData['days']) ? json_encode($courseData['days']) : null,
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
                        'days' => isset($courseData['days']) ? json_encode($courseData['days']) : null,
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
                    'name' => $scheduleCourse->course->title,
                    'credits' => $scheduleCourse->course->credits,
                    'description' => $scheduleCourse->course->description,
                    'section' => $scheduleCourse->section,
                    'days' => $scheduleCourse->days ? json_decode($scheduleCourse->days) : null,
                    'time' => $scheduleCourse->time,
                    'instructor' => $scheduleCourse->instructor,
                    'seatLimit' => $scheduleCourse->seat_limit,
                ];
            }),
        ];
    }
}
