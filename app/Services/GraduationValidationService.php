<?php

namespace App\Services;

use App\Models\Curriculum;
use App\Models\Course;
use App\Models\CurriculumCourse;
use App\Models\CurriculumConstraint;
use App\Models\ElectiveRule;
use App\Models\CurriculumBlacklist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GraduationValidationService
{
    /**
     * Grade points mapping for GPA calculation
     */
    private const GRADE_POINTS = [
        'A' => 4.0,
        'A-' => 3.7,
        'B+' => 3.3,
        'B' => 3.0,
        'B-' => 2.7,
        'C+' => 2.3,
        'C' => 2.0,
        'C-' => 1.7,
        'D+' => 1.3,
        'D' => 1.0,
        'D-' => 0.7,
        'F' => 0.0,
    ];

    /**
     * Passing grades
     */
    private const PASSING_GRADES = ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'D-', 'S', 'P'];

    /**
     * Main validation entry point
     */
    public function validate(array $courses, string $curriculumId): array
    {
        $curriculum = Curriculum::with([
            'curriculumCourses.course',
            'curriculumCourses.curriculumPrerequisites.prerequisiteCourse.course',
            'curriculumCourses.curriculumCorequisites.corequisiteCourse.course',
            'electiveRules',
        ])->find($curriculumId);

        if (!$curriculum) {
            return [
                'valid' => false,
                'canGraduate' => false,
                'errors' => ['Curriculum not found'],
                'warnings' => [],
            ];
        }

        // Convert array to collection for easier processing
        $submittedCourses = collect($courses);

        // Step 1: Match courses to curriculum
        $matchResult = $this->matchCoursesToCurriculum($submittedCourses, $curriculum);
        
        // Step 2: Get curriculum constraints
        $constraints = CurriculumConstraint::where('curriculum_id', $curriculumId)->get();
        
        // Step 3: Validate constraints
        $constraintResult = $this->validateConstraints($matchResult['matchedCourses'], $constraints);
        
        // Step 4: Validate elective rules
        $electiveResult = $this->validateElectiveRules($matchResult['matchedCourses'], $curriculum->electiveRules);
        
        // Step 5: Validate blacklists
        $blacklistResult = $this->validateBlacklists($matchResult['matchedCourses'], $curriculumId);
        
        // Step 5.5: Validate prerequisites and corequisites
        $prereqResult = $this->validatePrerequisites($matchResult['matchedCourses'], $curriculum);
        
        // Step 6: Calculate category progress
        $categoryProgress = $this->calculateCategoryProgress($matchResult['matchedCourses'], $curriculum);
        
        // Step 7: Calculate credits
        $completedCourses = $matchResult['matchedCourses']->filter(fn($c) => $c['status'] === 'completed');
        $creditsCompleted = $completedCourses->sum('credits');
        $creditsInProgress = $matchResult['matchedCourses']->filter(fn($c) => $c['status'] === 'in_progress')->sum('credits');
        $creditsPlanned = $matchResult['matchedCourses']->filter(fn($c) => $c['status'] === 'planned')->sum('credits');
        
        // Step 8: Calculate GPA
        $gpa = $this->calculateGPA($completedCourses);
        
        // Combine all errors and warnings
        $allErrors = array_merge(
            $matchResult['errors'],
            $constraintResult['errors'],
            $electiveResult['errors'],
            $blacklistResult['errors'],
            $prereqResult['errors']
        );
        
        $allWarnings = array_merge(
            $matchResult['warnings'],
            $constraintResult['warnings'] ?? [],
            $electiveResult['warnings'] ?? [],
            $prereqResult['warnings'] ?? []
        );

        // Step 9: Evaluate graduation requirements
        $requirements = $this->evaluateGraduationRequirements(
            $creditsCompleted,
            $gpa,
            $curriculum,
            $categoryProgress,
            $allErrors
        );

        // Calculate completion percentage
        $totalRequired = $curriculum->total_credits_required ?? 0;
        $completionPercentage = $totalRequired > 0 
            ? round(($creditsCompleted / $totalRequired) * 100, 1) 
            : 0;

        // Build requirements array in the format expected by frontend
        $requirementsList = [];
        foreach ($requirements['details'] as $key => $detail) {
            $requirementsList[] = [
                'name' => $detail['name'],
                'met' => $detail['met'],
                'label' => $detail['message'],
                'description' => $detail['message'],
                'message' => $detail['message'],
                'required' => $detail['required'],
                'current' => $detail['current'],
            ];
        }

        return [
            'valid' => empty($allErrors),
            'can_graduate' => $requirements['canGraduate'],
            'canGraduate' => $requirements['canGraduate'], // keep legacy key for backwards compat
            'summary' => [
                'totalCreditsRequired' => $curriculum->total_credits_required ?? 0,
                'totalCreditsEarned' => $creditsCompleted,
                'creditsCompleted' => $creditsCompleted,
                'creditsInProgress' => $creditsInProgress,
                'creditsPlanned' => $creditsPlanned,
                'completionPercentage' => $completionPercentage,
                'gpa' => round($gpa, 2),
                'matchedCourses' => $matchResult['matchedCount'],
                'unmatchedCourses' => $matchResult['unmatchedCount'],
                // Legacy keys for backwards compat
                'coursesMatched' => $matchResult['matchedCount'],
                'coursesUnmatched' => $matchResult['unmatchedCount'],
            ],
            'categoryProgress' => $categoryProgress,
            'requirements' => $requirementsList,
            'errors' => $allErrors,
            'warnings' => $allWarnings,
            'matchedCourses' => $matchResult['matchedCourses']->map(function ($course) {
                return [
                    'code' => $course['code'],
                    'name' => $course['name'] ?? null,
                    'credits' => $course['credits'] ?? 0,
                    'grade' => $course['grade'] ?? null,
                    'status' => $course['status'] ?? 'completed',
                    'semester' => $course['semester'] ?? null,
                    'category' => $course['category'] ?? null,
                    'matched' => true,
                ];
            })->values()->toArray(),
            'unmatchedCourses' => collect($matchResult['unmatchedCourses'])->map(function ($course) {
                if (is_string($course)) {
                    return ['code' => $course, 'name' => null, 'credits' => 0, 'grade' => null, 'status' => 'completed', 'reason' => 'Not in curriculum'];
                }
                return [
                    'code' => $course['code'] ?? null,
                    'name' => $course['name'] ?? null,
                    'credits' => $course['credits'] ?? 0,
                    'grade' => $course['grade'] ?? null,
                    'status' => $course['status'] ?? 'completed',
                    'reason' => $course['reason'] ?? 'Not in curriculum',
                ];
            })->values()->toArray(),
        ];
    }

    /**
     * Match submitted courses to curriculum courses
     */
    private function matchCoursesToCurriculum(Collection $courses, Curriculum $curriculum): array
    {
        $warnings = [];
        $errors = [];
        $matchedCourses = collect();
        $unmatchedCourses = [];

        // Get all curriculum courses with their course details
        $curriculumCourseMap = $curriculum->curriculumCourses->keyBy(function ($cc) {
            return strtoupper(trim($cc->course->code));
        });

        foreach ($courses as $submitted) {
            $code = strtoupper(trim($submitted['code']));
            
            if (isset($curriculumCourseMap[$code])) {
                $curriculumCourse = $curriculumCourseMap[$code];
                $course = $curriculumCourse->course;
                
                // Check credit mismatch
                if (isset($submitted['credits']) && $submitted['credits'] != $course->credits) {
                    $warnings[] = "Credit mismatch for {$code}: submitted {$submitted['credits']}, expected {$course->credits}";
                }
                
                $matchedCourses->push([
                    'code' => $code,
                    'name' => $course->name,
                    'credits' => $course->credits,
                    'grade' => $submitted['grade'] ?? null,
                    'status' => $submitted['status'] ?? 'completed',
                    'semester' => $submitted['semester'] ?? null,
                    'category' => $submitted['category'] ?? null,
                    'is_required' => $curriculumCourse->is_required,
                    'curriculum_course_id' => $curriculumCourse->id,
                    'course_id' => $course->id,
                    'matched' => true,
                ]);
            } else {
                $warnings[] = "Course {$code} not found in curriculum";
                $unmatchedCourses[] = [
                    'code' => $code,
                    'name' => $submitted['name'] ?? null,
                    'credits' => $submitted['credits'] ?? 0,
                    'grade' => $submitted['grade'] ?? null,
                    'status' => $submitted['status'] ?? 'completed',
                    'reason' => 'Not in curriculum',
                ];
            }
        }

        return [
            'matchedCourses' => $matchedCourses,
            'unmatchedCourses' => $unmatchedCourses,
            'matchedCount' => $matchedCourses->count(),
            'unmatchedCount' => count($unmatchedCourses),
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /**
     * Validate against curriculum constraints (category requirements)
     */
    private function validateConstraints(Collection $completedCourses, $constraints): array
    {
        $errors = [];
        $warnings = [];

        foreach ($constraints as $constraint) {
            if (!$constraint->is_required) {
                continue;
            }

            $config = $constraint->config ?? [];
            
            // Handle different constraint types
            switch ($constraint->type) {
                case 'min_credits':
                    $minCredits = $config['min_credits'] ?? 0;
                    $category = $config['category'] ?? null;
                    
                    $relevantCredits = $completedCourses
                        ->when($category, fn($q) => $q->where('category', $category))
                        ->where('status', 'completed')
                        ->sum('credits');
                    
                    if ($relevantCredits < $minCredits) {
                        $categoryLabel = $category ?? 'total';
                        $errors[] = "Missing " . ($minCredits - $relevantCredits) . " credits for {$categoryLabel} requirement";
                    }
                    break;

                case 'required_course':
                    $requiredCourseCode = $config['course_code'] ?? null;
                    if ($requiredCourseCode) {
                        $hasCourse = $completedCourses
                            ->where('code', strtoupper($requiredCourseCode))
                            ->where('status', 'completed')
                            ->isNotEmpty();
                        
                        if (!$hasCourse) {
                            $errors[] = "Required course {$requiredCourseCode} not completed";
                        }
                    }
                    break;
                    
                case 'min_gpa':
                    // This will be validated in graduation requirements
                    break;
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate elective rules
     */
    private function validateElectiveRules(Collection $completedCourses, $electiveRules): array
    {
        $errors = [];
        $warnings = [];

        foreach ($electiveRules as $rule) {
            $category = $rule->category;
            $requiredCredits = $rule->required_credits;

            $categoryCredits = $completedCourses
                ->where('category', $category)
                ->where('status', 'completed')
                ->sum('credits');

            if ($categoryCredits < $requiredCredits) {
                $deficit = $requiredCredits - $categoryCredits;
                $errors[] = "Missing {$deficit} credits for {$category} electives (required: {$requiredCredits}, completed: {$categoryCredits})";
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate against blacklisted course combinations
     */
    private function validateBlacklists(Collection $completedCourses, string $curriculumId): array
    {
        $errors = [];

        // Get all blacklists for this curriculum
        $blacklists = CurriculumBlacklist::with(['blacklist.courses.course'])
            ->where('curriculum_id', $curriculumId)
            ->get();

        $completedCodes = $completedCourses->pluck('code')->map(fn($c) => strtoupper($c))->toArray();

        foreach ($blacklists as $curriculumBlacklist) {
            $blacklist = $curriculumBlacklist->blacklist;
            if (!$blacklist) continue;

            $blacklistCodes = $blacklist->courses->pluck('course.code')
                ->map(fn($c) => strtoupper($c))
                ->toArray();

            // Check if student has taken multiple courses from the same blacklist
            $conflicts = array_intersect($completedCodes, $blacklistCodes);
            
            if (count($conflicts) > 1) {
                $conflictList = implode(', ', $conflicts);
                $errors[] = "Blacklist violation: Cannot take multiple courses from same group ({$conflictList})";
            }
        }

        return ['errors' => $errors];
    }

    /**
     * Validate prerequisites and corequisites
     */
    private function validatePrerequisites(Collection $completedCourses, Curriculum $curriculum): array
    {
        $errors = [];
        $warnings = [];

        // Build a map of completed course codes with their statuses
        $courseStatusMap = $completedCourses->keyBy(function ($course) {
            return strtoupper($course['code']);
        });

        // Get courses that are completed or in progress (not just planned)
        $takenCodes = $completedCourses
            ->whereIn('status', ['completed', 'in_progress'])
            ->pluck('code')
            ->map(fn($c) => strtoupper($c))
            ->toArray();

        // Get all curriculum courses with their prerequisites
        foreach ($curriculum->curriculumCourses as $curriculumCourse) {
            $courseCode = strtoupper($curriculumCourse->course->code);
            
            // Only check prerequisites for courses that the student is taking/has taken
            if (!isset($courseStatusMap[$courseCode])) {
                continue;
            }
            
            $courseStatus = $courseStatusMap[$courseCode]['status'];
            
            // Skip prerequisite check for planned courses (just warn)
            if ($courseStatus === 'planned') {
                // Check if prerequisites would be satisfied
                foreach ($curriculumCourse->curriculumPrerequisites as $prereq) {
                    $prereqCode = strtoupper($prereq->prerequisiteCourse->course->code ?? '');
                    if ($prereqCode && !in_array($prereqCode, $takenCodes)) {
                        $warnings[] = "Planned course {$courseCode} requires prerequisite {$prereqCode}";
                    }
                }
                continue;
            }

            // For completed/in_progress courses, check prerequisites
            foreach ($curriculumCourse->curriculumPrerequisites as $prereq) {
                $prereqCode = strtoupper($prereq->prerequisiteCourse->course->code ?? '');
                if (!$prereqCode) continue;

                // Check if prerequisite is completed
                $prereqCompleted = isset($courseStatusMap[$prereqCode]) 
                    && $courseStatusMap[$prereqCode]['status'] === 'completed';
                
                if (!$prereqCompleted) {
                    $errors[] = "Missing prerequisite: {$courseCode} requires {$prereqCode} to be completed first";
                }
            }

            // For completed/in_progress courses, check corequisites
            foreach ($curriculumCourse->curriculumCorequisites as $coreq) {
                $coreqCode = strtoupper($coreq->corequisiteCourse->course->code ?? '');
                if (!$coreqCode) continue;

                // Check if corequisite is at least taken (completed or in_progress)
                $coreqTaken = in_array($coreqCode, $takenCodes);
                
                if (!$coreqTaken) {
                    $errors[] = "Missing corequisite: {$courseCode} must be taken together with {$coreqCode}";
                }
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Calculate progress by category
     */
    private function calculateCategoryProgress(Collection $courses, Curriculum $curriculum): array
    {
        $progress = [];
        
        // Group courses by category
        $byCategory = $courses->groupBy('category');
        
        // Get elective rules for required credits per category
        $electiveRules = $curriculum->electiveRules->keyBy('category');

        foreach ($byCategory as $category => $categoryCourses) {
            if (!$category) continue;
            
            $completed = $categoryCourses->where('status', 'completed');
            $inProgress = $categoryCourses->where('status', 'in_progress');
            $planned = $categoryCourses->where('status', 'planned');
            
            $creditsCompleted = $completed->sum('credits');
            $creditsInProgress = $inProgress->sum('credits');
            $creditsPlanned = $planned->sum('credits');
            
            $requiredCredits = $electiveRules->has($category) 
                ? $electiveRules[$category]->required_credits 
                : 0;

            $progress[$category] = [
                'name' => $category,
                'creditsRequired' => $requiredCredits,
                'creditsCompleted' => $creditsCompleted,
                'creditsInProgress' => $creditsInProgress,
                'creditsPlanned' => $creditsPlanned,
                'coursesCompleted' => $completed->count(),
                'coursesInProgress' => $inProgress->count(),
                'coursesPlanned' => $planned->count(),
                'percentComplete' => $requiredCredits > 0 
                    ? min(100, round(($creditsCompleted / $requiredCredits) * 100, 1))
                    : 100,
                'isComplete' => $requiredCredits > 0 && $creditsCompleted >= $requiredCredits,
            ];
        }

        return $progress;
    }

    /**
     * Calculate GPA from completed courses
     */
    private function calculateGPA(Collection $courses): float
    {
        $totalPoints = 0;
        $totalCredits = 0;

        foreach ($courses as $course) {
            if ($course['status'] !== 'completed') {
                continue;
            }

            $grade = strtoupper($course['grade'] ?? '');
            $credits = $course['credits'] ?? 0;

            // Skip S/U, P/F, W grades
            if (in_array($grade, ['S', 'U', 'P', 'W', 'I', 'IP'])) {
                continue;
            }

            // Normalize grade (e.g., "A+" -> "A")
            $normalizedGrade = $this->normalizeGrade($grade);

            if (isset(self::GRADE_POINTS[$normalizedGrade])) {
                $totalPoints += self::GRADE_POINTS[$normalizedGrade] * $credits;
                $totalCredits += $credits;
            }
        }

        return $totalCredits > 0 ? $totalPoints / $totalCredits : 0;
    }

    /**
     * Normalize grade to standard format
     */
    private function normalizeGrade(string $grade): string
    {
        $grade = strtoupper(trim($grade));
        
        // Handle A+ as A
        if ($grade === 'A+') {
            return 'A';
        }
        
        return $grade;
    }

    /**
     * Evaluate graduation requirements
     */
    private function evaluateGraduationRequirements(
        float $creditsCompleted,
        float $gpa,
        Curriculum $curriculum,
        array $categoryProgress,
        array $errors
    ): array {
        $requirements = [];
        $canGraduate = true;

        // 1. Total credits requirement
        $totalRequired = $curriculum->total_credits_required ?? 120;
        $creditsReqMet = $creditsCompleted >= $totalRequired;
        $requirements['totalCredits'] = [
            'name' => 'Minimum Credits',
            'required' => $totalRequired,
            'current' => $creditsCompleted,
            'met' => $creditsReqMet,
            'label' => "Minimum {$totalRequired} credits required",
            'description' => $creditsReqMet
                ? "Student has {$creditsCompleted} completed credits"
                : "Student has {$creditsCompleted} completed credits, needs " . ($totalRequired - $creditsCompleted) . " more",
            'message' => $creditsReqMet 
                ? "Minimum credit requirement: {$creditsCompleted}/{$totalRequired} completed"
                : "Minimum credit requirement: {$creditsCompleted}/{$totalRequired} completed — need " . ($totalRequired - $creditsCompleted) . " more",
        ];
        if (!$creditsReqMet) $canGraduate = false;

        // 2. GPA requirement (default 2.0)
        $minGpa = 2.0;
        $gpaRounded = round($gpa, 2);
        $gpaReqMet = $gpa >= $minGpa;
        $requirements['gpa'] = [
            'name' => 'GPA Requirement',
            'required' => $minGpa,
            'current' => $gpaRounded,
            'met' => $gpaReqMet,
            'label' => "Minimum GPA of {$minGpa} required",
            'description' => $gpaReqMet
                ? "Current GPA: {$gpaRounded} (meets requirement)"
                : "Current GPA: {$gpaRounded} (below minimum {$minGpa})",
            'message' => $gpaReqMet 
                ? "Current GPA: {$gpaRounded} (meets requirement)"
                : "Current GPA: {$gpaRounded} is below minimum {$minGpa}",
        ];
        if (!$gpaReqMet) $canGraduate = false;

        // 3. Category requirements
        foreach ($categoryProgress as $category => $progress) {
            if ($progress['creditsRequired'] > 0 && !$progress['isComplete']) {
                $deficit = $progress['creditsRequired'] - $progress['creditsCompleted'];
                $requirements["category_{$category}"] = [
                    'name' => "{$category} Complete",
                    'required' => $progress['creditsRequired'],
                    'current' => $progress['creditsCompleted'],
                    'met' => false,
                    'label' => "All {$category} credits must be completed",
                    'description' => "{$deficit} credits remaining in {$category}",
                    'message' => "{$deficit} credits remaining in {$category}",
                ];
                $canGraduate = false;
            }
        }

        // 4. No critical errors
        $noErrorsReqMet = empty($errors);
        $requirements['noErrors'] = [
            'name' => 'No Validation Errors',
            'required' => 0,
            'current' => count($errors),
            'met' => $noErrorsReqMet,
            'label' => 'No validation errors',
            'description' => $noErrorsReqMet 
                ? 'No validation errors found'
                : count($errors) . ' validation error(s) found',
            'message' => $noErrorsReqMet 
                ? "No validation errors"
                : count($errors) . " validation error(s) found",
        ];
        if (!$noErrorsReqMet) $canGraduate = false;

        return [
            'canGraduate' => $canGraduate,
            'details' => $requirements,
        ];
    }
}
