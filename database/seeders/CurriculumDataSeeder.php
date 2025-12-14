<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\Curriculum;
use App\Models\Course;
use App\Models\CurriculumCourse;
use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Support\Facades\Log;

class CurriculumDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dataPath = base_path('data');
        
        // Excel files to process
        $files = [
            'BSAI2024.xlsx',
            'BSCS651-652.xlsx',
            'BSCS653onwards.xlsx',
            'BSIT2022 (1).xlsx',
        ];

        foreach ($files as $file) {
            $filePath = $dataPath . '/' . $file;
            
            if (!file_exists($filePath)) {
                Log::warning("File not found: {$filePath}");
                continue;
            }

            try {
                $this->processExcelFile($filePath, $file);
                Log::info("Successfully processed: {$file}");
            } catch (\Exception $e) {
                Log::error("Error processing {$file}: " . $e->getMessage());
            }
        }
    }

    private function processExcelFile($filePath, $fileName): void
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Skip header row (assuming first row is header)
        $header = array_shift($rows);

        foreach ($rows as $row) {
            // Process each row based on your Excel structure
            // Adjust column indexes based on your actual Excel format
            
            // Example structure (modify based on your actual Excel columns):
            // [0] => Course Code
            // [1] => Course Name
            // [2] => Credits
            // [3] => Year/Semester
            // etc.
            
            if (empty($row[0])) {
                continue; // Skip empty rows
            }

            // Example: Create or update course
            // Modify this based on your actual Excel structure
            $courseData = [
                'code' => $row[0] ?? null,
                'name' => $row[1] ?? null,
                // Add more fields as needed
            ];

            // Insert logic here based on your data structure
            Log::info("Processing row: " . json_encode($courseData));
        }
    }
}
