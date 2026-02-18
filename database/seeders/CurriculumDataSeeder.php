<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\Curriculum;
use App\Models\Course;
use App\Models\CurriculumCourse;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\CourseType;
use App\Models\DepartmentCourseType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CurriculumDataSeeder extends Seeder
{
    private $currentCategory = null;
    private $departmentId = null;
    private $createdCourses = [];
    private $courseSequence = 1;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting curriculum data seeding...');
        
        $dataPath = base_path('data');
        
        // Map files to curriculum info
        $fileMapping = [
            'BSCS651-652.csv' => ['name' => 'BSCS (2022)', 'year' => '651-652', 'department' => 'Computer Science'],
            'BSCS651-652.xlsx' => ['name' => 'BSCS (2022)', 'year' => '651-652', 'department' => 'Computer Science'],
            'BSCS653onwards.csv' => ['name' => 'BSCS (2023)', 'year' => '653', 'department' => 'Computer Science'],
            'BSCS653onwards.xlsx' => ['name' => 'BSCS (2023)', 'year' => '653', 'department' => 'Computer Science'],
            'BSIT2022.csv' => ['name' => 'BSIT (2022)', 'year' => '653', 'department' => 'Information Technology'],
            'BSIT2022 (1).xlsx' => ['name' => 'BSIT (2022)', 'year' => '653', 'department' => 'Information Technology'],
            'BSAI2024.csv' => ['name' => 'BSAI (2024)', 'year' => '2024', 'department' => 'Artificial Intelligence'],
            'BSAI2024.xlsx' => ['name' => 'BSAI (2024)', 'year' => '2024', 'department' => 'Artificial Intelligence'],
        ];

        foreach ($fileMapping as $file => $info) {
            $filePath = $dataPath . '/' . $file;
            
            if (!file_exists($filePath)) {
                $this->command->warn("File not found: {$filePath}");
                continue;
            }

            try {
                $this->command->info("Processing: {$file}");
                $this->processFile($filePath, $info);
                $this->command->info("✓ Successfully processed: {$file}");
            } catch (\Exception $e) {
                $this->command->error("✗ Error processing {$file}: " . $e->getMessage());
                Log::error("Error processing {$file}: " . $e->getMessage());
            }
        }

        $this->command->info("Seeding completed! Total courses created: " . count($this->createdCourses));
    }

    private function processFile($filePath, $info): void
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        // Get or create faculty first
        $faculty = Faculty::firstOrCreate(
            ['name' => 'Engineering and Technology'],
            ['code' => 'ENG']
        );
        
        // Get or create department
        $department = Department::firstOrCreate(
            ['name' => $info['department']],
            [
                'code' => strtoupper(substr($info['department'], 0, 3)),
                'faculty_id' => $faculty->id
            ]
        );
        $this->departmentId = $department->id;

        // Get a system user (CHAIRPERSON) for created_by_id
        $systemUser = \App\Models\User::where('role', 'CHAIRPERSON')->first();
        if (!$systemUser) {
            // Create a system user if none exists
            $systemUser = \App\Models\User::create([
                'name' => 'System',
                'email' => 'system@edutrack.com',
                'password' => bcrypt('system123'),
                'role' => 'CHAIRPERSON',
                'department_id' => $department->id,
                'is_active' => true,
            ]);
        }

        // Get or create curriculum
        $curriculum = Curriculum::firstOrCreate(
            [
                'name' => $info['name'],
                'year' => $info['year'],
                'department_id' => $department->id
            ],
            [
                'version' => '1.0',
                'description' => "Curriculum for {$info['name']}",
                'is_active' => true,
                'start_id' => substr($info['year'], 0, 2) . '001',
                'end_id' => substr($info['year'], 0, 2) . '999',
                'faculty_id' => $faculty->id,
                'created_by_id' => $systemUser->id,
            ]
        );

        $this->command->info("  → Curriculum: {$curriculum->name} (ID: {$curriculum->id})");

        // Reset state
        $this->currentCategory = null;
        $this->courseSequence = 1;

        // Process based on file type
        if ($extension === 'csv') {
            $this->processCSV($filePath, $curriculum);
        } else {
            $this->processExcel($filePath, $curriculum);
        }

        // Calculate and update total credits required from linked courses
        $totalCredits = CurriculumCourse::where('curriculum_id', $curriculum->id)
            ->with('course')
            ->get()
            ->sum(function ($curriculumCourse) {
                return (int)($curriculumCourse->course->credits ?? 0);
            });
        
        $curriculum->update(['total_credits_required' => $totalCredits]);
        $this->command->info("  → Total Credits Required: {$totalCredits}");
    }

    private function processCSV($filePath, $curriculum): void
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception("Cannot open CSV file: {$filePath}");
        }

        $rowNumber = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $this->processRow($row, $curriculum, $rowNumber);
        }

        fclose($handle);
    }

    private function processExcel($filePath, $curriculum): void
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        $rowNumber = 0;
        foreach ($rows as $row) {
            $rowNumber++;
            $this->processRow($row, $curriculum, $rowNumber);
        }
    }

    private function processRow($row, $curriculum, $rowNumber): void
    {
        // Skip empty rows
        if (empty(array_filter($row))) {
            return;
        }

        $firstCell = trim($row[0] ?? '');
        $secondCell = trim($row[1] ?? '');
        $thirdCell = trim($row[2] ?? '');

        // Detect category headers (e.g., "GENERAL EDUCATIONAL COURSES ( 30 CREDITS )")
        if (preg_match('/^[A-Z\s]+COURSES\s*\(/i', $firstCell) || 
            preg_match('/^Group\s+\d+/i', $firstCell)) {
            $this->currentCategory = $firstCell;
            $this->command->info("  → Category: {$this->currentCategory}");
            
            // Create or get course type
            $this->getOrCreateCourseType($this->currentCategory);
            return;
        }

        // Skip header rows and non-course rows
        if (empty($secondCell) || 
            in_array(strtoupper($firstCell), ['NO.', 'NO', 'NUMBER']) ||
            preg_match('/^(ASSUMPTION|VINCENT|Bachelor|NAME|Students)/i', $firstCell)) {
            return;
        }

        // Extract course data
        // Format: NO., COURSE NO., COURSE TITLE, CREDITS, GRADE, REMARKS
        $courseCode = $this->cleanCourseCode($secondCell);
        $courseName = $this->cleanCourseName($thirdCell);
        $credits = $this->extractCredits($row[3] ?? '3');

        // Skip if no valid course code or name
        if (empty($courseCode) || empty($courseName)) {
            return;
        }

        // Create or update course
        try {
            $course = Course::updateOrCreate(
                ['code' => $courseCode],
                [
                    'name' => $courseName,
                    'credits' => $credits,
                    'credit_hours' => (string)($credits * 3), // Default credit hours as string
                    'description' => '',
                    'is_active' => true,
                ]
            );

            // Link course to curriculum if not already linked
            CurriculumCourse::firstOrCreate(
                [
                    'curriculum_id' => $curriculum->id,
                    'course_id' => $course->id,
                ],
                [
                    'year' => 1,
                    'semester' => 1,
                    'is_required' => true,
                    'sequence' => $this->courseSequence++,
                ]
            );

            // Link to course type if we have a current category
            if ($this->currentCategory) {
                $courseType = $this->getOrCreateCourseType($this->currentCategory);
                DepartmentCourseType::firstOrCreate([
                    'course_id' => $course->id,
                    'course_type_id' => $courseType->id,
                    'department_id' => $this->departmentId,
                    'curriculum_id' => $curriculum->id,
                ]);
            }

            if (!in_array($courseCode, $this->createdCourses)) {
                $this->createdCourses[] = $courseCode;
                $this->command->info("    ✓ {$courseCode}: {$courseName} ({$credits} credits)");
            }

        } catch (\Exception $e) {
            $this->command->error("    ✗ Error creating course {$courseCode}: " . $e->getMessage());
        }
    }

    private function cleanCourseCode($code): string
    {
        // Remove extra spaces and normalize
        $code = trim($code);
        $code = preg_replace('/\s+/', ' ', $code);
        return strtoupper($code);
    }

    private function cleanCourseName($name): string
    {
        $name = trim($name);
        // Remove trailing punctuation
        $name = rtrim($name, '.,;:');
        return $name;
    }

    private function extractCredits($creditString): int
    {
        // Extract number from string like "3", "3 credits", etc.
        if (preg_match('/(\d+)/', $creditString, $matches)) {
            return (int)$matches[1];
        }
        return 3; // Default
    }

    private function getOrCreateCourseType($categoryName): CourseType
    {
        // Normalize category name
        $typeName = trim($categoryName);
        
        // Extract just the category part before parenthesis
        if (preg_match('/^(.+?)\s*\(/i', $typeName, $matches)) {
            $typeName = trim($matches[1]);
        }

        // Shorten long names
        $typeName = str_replace([
            'GENERAL EDUCATIONAL COURSES',
            'MAJOR ELECTIVE COURSES',
            'MAJOR COURSES',
            'CORE COURSES'
        ], [
            'General Education',
            'Major Elective',
            'Major',
            'Core'
        ], $typeName);

        return CourseType::firstOrCreate(
            ['name' => $typeName],
            [
                'description' => "Course category: {$typeName}",
                'color' => $this->getRandomColor(),
            ]
        );
    }

    private function getRandomColor(): string
    {
        $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#14B8A6'];
        return $colors[array_rand($colors)];
    }
}
