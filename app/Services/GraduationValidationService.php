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
            $blacklistResult['errors']
        );
        
        $allWarnings = array_merge(
            $matchResult['warnings'],
            $constraintResult['warnings'] ?? [],
            $electiveResult['warnings'] ?? []
        );

        // Step 9: Evaluate graduation requirements
        $requirements = $this->evaluateGraduationRequirements(
            $creditsCompleted,
            $gpa,
            $curriculum,
            $categoryProgress,
            $allErrors
        );

        return [
            'valid' => empty($allErrors),
            'canGraduate' => $requirements['canGraduate'],
            'summary' => [
                'totalCreditsRequired' => $curriculum->total_credits_required ?? 0,
                'creditsCompleted' => $creditsCompleted,
                'creditsInProgress' => $creditsInProgress,
                'creditsPlanned' => $creditsPlanned,
                'gpa' => round($gpa, 2),
                'coursesMatched' => $matchResult['matchedCount'],
                'coursesUnmatched' => $matchResult['unmatchedCount'],
            ],
            'categoryProgress' => $categoryProgress,
            'requirements' => $requirements['details'],
            'errors' => $allErrors,
            'warnings' => $allWarnings,
            'matchedCourses' => $matchResult['matchedCourses']->toArray(),
            'unmatchedCourses' => $matchResult['unmatchedCourses'],
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
        $blacklists = CurriculumBlacklist::with(['blacklist.blacklistCourses.course'])
            ->where('curriculum_id', $curriculumId)
            ->get();

        $completedCodes = $completedCourses->pluck('code')->map(fn($c) => strtoupper($c))->toArray();

        foreach ($blacklists as $curriculumBlacklist) {
            $blacklist = $curriculumBlacklist->blacklist;
            if (!$blacklist) continue;

            $blacklistCodes = $blacklist->blacklistCourses->pluck('course.code')
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
            'name' => 'Total Credits',
            'required' => $totalRequired,
            'current' => $creditsCompleted,
            'met' => $creditsReqMet,
            'message' => $creditsReqMet 
                ? "Completed {$creditsCompleted} of {$totalRequired} credits"
                : "Need " . ($totalRequired - $creditsCompleted) . " more credits",
        ];
        if (!$creditsReqMet) $canGraduate = false;

        // 2. GPA requirement (default 2.0)
        $minGpa = 2.0;
        $gpaReqMet = $gpa >= $minGpa;
        $requirements['gpa'] = [
            'name' => 'Minimum GPA',
            'required' => $minGpa,
            'current' => round($gpa, 2),
            'met' => $gpaReqMet,
            'message' => $gpaReqMet 
                ? "GPA of {$gpa} meets minimum {$minGpa}"
                : "GPA of {$gpa} is below minimum {$minGpa}",
        ];
        if (!$gpaReqMet) $canGraduate = false;

        // 3. Category requirements
        foreach ($categoryProgress as $category => $progress) {
            if ($progress['creditsRequired'] > 0 && !$progress['isComplete']) {
                $requirements["category_{$category}"] = [
                    'name' => "{$category} Credits",
                    'required' => $progress['creditsRequired'],
                    'current' => $progress['creditsCompleted'],
                    'met' => false,
                    'message' => "Need " . ($progress['creditsRequired'] - $progress['creditsCompleted']) . " more {$category} credits",
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
