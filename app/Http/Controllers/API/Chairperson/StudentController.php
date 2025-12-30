<?php

namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CompletedCourse;
use App\Models\PlannedCourse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentController extends Controller
{
    /**
     * Get list of students
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            // Build query for students
            $query = User::where('role', 'STUDENT')
                ->with(['faculty', 'department', 'curriculum']);
            
            // Filter by chairperson's department if chairperson
            if ($user->role === 'CHAIRPERSON' && $user->department_id) {
                $query->where('department_id', $user->department_id);
            }
            
            // Optional filters
            if ($request->has('curriculumId')) {
                $query->where('curriculum_id', $request->curriculumId);
            }
            
            if ($request->has('year')) {
                $query->where('year', $request->year);
            }
            
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('student_id', 'like', "%{$search}%");
                });
            }
            
            // Pagination
            $perPage = $request->get('limit', 50);
            $students = $query->orderBy('name', 'asc')->paginate($perPage);
            
            // Format student data
            $formattedStudents = $students->map(function ($student) {
                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'studentId' => $student->student_id,
                    'department' => $student->department ? $student->department->name : null,
                    'year' => $student->year,
                    'gpa' => $student->gpa,
                    'curriculum' => $student->curriculum ? [
                        'id' => $student->curriculum->id,
                        'name' => $student->curriculum->name,
                    ] : null,
                ];
            });
            
            return response()->json([
                'students' => $formattedStudents,
                'pagination' => [
                    'total' => $students->total(),
                    'page' => $students->currentPage(),
                    'limit' => $students->perPage(),
                    'totalPages' => $students->lastPage(),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching students: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to fetch students',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Get academic progress for a specific student
     * 
     * @param Request $request
     * @param string $studentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function progress(Request $request, $studentId)
    {
        try {
            $student = User::with(['curriculum', 'completedCourses.course'])
                ->where('role', 'STUDENT')
                ->findOrFail($studentId);
            
            // Verify chairperson has access to this student
            $user = $request->user();
            if ($user->role === 'CHAIRPERSON' && $user->department_id !== $student->department_id) {
                return response()->json([
                    'error' => ['message' => 'Access denied']
                ], 403);
            }
            
            // Calculate completed credits
            $completedCredits = $student->completedCourses->sum(function ($completed) {
                return $completed->course ? $completed->course->credits : 0;
            });
            
            // Get total courses from curriculum
            $totalCourses = 0;
            $totalCreditsRequired = 0;
            if ($student->curriculum) {
                $totalCourses = $student->curriculum->curriculumCourses()->count();
                $totalCreditsRequired = $student->curriculum->total_credits ?? 0;
            }
            
            // Calculate completed courses count
            $completedCoursesCount = $student->completedCourses()->count();
            
            // Calculate GPA (simple average of grades)
            $gpa = $student->gpa ?? 0.0;
            
            // Determine current semester based on completed credits
            $currentSemester = $this->calculateCurrentSemester($completedCredits, $totalCreditsRequired);
            
            // Calculate graduation progress percentage
            $graduationProgress = $totalCreditsRequired > 0 
                ? round(($completedCredits / $totalCreditsRequired) * 100, 2) 
                : 0;
            
            return response()->json([
                'totalCredits' => $completedCredits,
                'totalCreditsRequired' => $totalCreditsRequired,
                'completedCourses' => $completedCoursesCount,
                'totalCourses' => $totalCourses,
                'gpa' => round($gpa, 2),
                'currentSemester' => $currentSemester,
                'graduationProgress' => $graduationProgress,
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => ['message' => 'Student not found']
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching student progress: ' . $e->getMessage(), [
                'studentId' => $studentId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to fetch student progress',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Get planned courses for a student
     * 
     * @param Request $request
     * @param string $studentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function plannedCourses(Request $request, $studentId)
    {
        try {
            $student = User::where('role', 'STUDENT')->findOrFail($studentId);
            
            // Verify chairperson has access to this student
            $user = $request->user();
            if ($user->role === 'CHAIRPERSON' && $user->department_id !== $student->department_id) {
                return response()->json([
                    'error' => ['message' => 'Access denied']
                ], 403);
            }
            
            // Get planned courses
            $plannedCourses = PlannedCourse::where('student_id', $studentId)
                ->with(['course'])
                ->orderBy('semester', 'asc')
                ->get();
            
            // Check if courses are completed
            $completedCourseIds = CompletedCourse::where('student_id', $studentId)
                ->pluck('course_id')
                ->toArray();
            
            $formattedCourses = $plannedCourses->map(function ($planned) use ($completedCourseIds) {
                return [
                    'id' => $planned->id,
                    'code' => $planned->course->code,
                    'name' => $planned->course->title,
                    'credits' => $planned->course->credits,
                    'semester' => $planned->semester,
                    'isCompleted' => in_array($planned->course_id, $completedCourseIds),
                ];
            });
            
            return response()->json([
                'courses' => $formattedCourses,
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => ['message' => 'Student not found']
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching planned courses: ' . $e->getMessage(), [
                'studentId' => $studentId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to fetch planned courses',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Update student's planned courses
     * 
     * @param Request $request
     * @param string $studentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $studentId)
    {
        try {
            $student = User::where('role', 'STUDENT')->findOrFail($studentId);
            
            // Verify chairperson has access to this student
            $user = $request->user();
            if ($user->role === 'CHAIRPERSON' && $user->department_id !== $student->department_id) {
                return response()->json([
                    'error' => ['message' => 'Access denied']
                ], 403);
            }
            
            $request->validate([
                'plannedCourses' => 'sometimes|array',
                'plannedCourses.*.courseId' => 'required|exists:courses,id',
                'plannedCourses.*.semester' => 'required|string',
            ]);
            
            DB::beginTransaction();
            
            // Update planned courses if provided
            if ($request->has('plannedCourses')) {
                // Delete existing planned courses
                PlannedCourse::where('student_id', $studentId)->delete();
                
                // Insert new planned courses
                foreach ($request->plannedCourses as $plannedCourse) {
                    PlannedCourse::create([
                        'student_id' => $studentId,
                        'course_id' => $plannedCourse['courseId'],
                        'semester' => $plannedCourse['semester'],
                    ]);
                }
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Student information updated successfully',
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                ],
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
                'error' => ['message' => 'Student not found']
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating student: ' . $e->getMessage(), [
                'studentId' => $studentId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to update student',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Calculate current semester based on credits
     * 
     * @param int $completedCredits
     * @param int $totalCredits
     * @return int
     */
    private function calculateCurrentSemester($completedCredits, $totalCredits)
    {
        if ($totalCredits <= 0) {
            return 1;
        }
        
        // Assuming average of 15-18 credits per semester
        $creditsPerSemester = 15;
        $semester = ceil($completedCredits / $creditsPerSemester) + 1;
        
        // Cap at 8 semesters (4 years)
        return min($semester, 8);
    }
}
