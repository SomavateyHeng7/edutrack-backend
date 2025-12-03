<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DownloadController extends Controller
{
    // GET /api/download/sample-xlsx
    public function sampleXlsx()
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $this->writeData($sheet);

            // Style header row
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '366092']
                ],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ];
            $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(12);
            $sheet->getColumnDimension('B')->setWidth(40);
            $sheet->getColumnDimension('C')->setWidth(8);
            $sheet->getColumnDimension('D')->setWidth(50);
            $sheet->getColumnDimension('E')->setWidth(10);

            // Output file
            $writer = new Xlsx($spreadsheet);
            ob_start();
            $writer->save('php://output');
            $excelOutput = ob_get_clean();

            return response($excelOutput, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="sample_curriculum_courses.xlsx"',
                'Cache-Control' => 'no-cache',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate XLSX file'], 500);
        }
    }

    // GET /api/download/sample-csv
    public function sampleCsv()
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $this->writeData($sheet);

            $writer = new Csv($spreadsheet);
            ob_start();
            $writer->save('php://output');
            $csvOutput = ob_get_clean();

            // Add UTF-8 BOM for Excel compatibility
            $csvOutput = "\xEF\xBB\xBF" . $csvOutput;

            return response($csvOutput, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="sample_curriculum_courses.csv"',
                'Cache-Control' => 'no-cache',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate CSV file'], 500);
        }
    }

    /**
     * Write sample data to the sheet
     */
    private function writeData($sheet)
    {
        $sampleData = [
            ['Course Code', 'Course Title', 'Credits', 'Course Description', 'Crd Hour'],
            ['CSX3010', 'Senior Project I', 3, 'First part of capstone project development', '3-0-6'],
            ['CSX3011', 'Senior Project II', 3, 'Completion and presentation of capstone project', '3-0-6'],
            ['CSX3001', 'Fundamentals of Computer Programming', 3, 'Basic programming concepts and logic structures', '3-0-6'],
            ['CSX3002', 'Object-Oriented Concept and Programming', 3, 'OOP principles and design patterns', '3-0-6'],
            ['CSX3003', 'Data Structure and Algorithm', 3, 'Data structures implementation and algorithm analysis', '3-0-6'],
            ['CSX3004', 'Programming Language', 3, 'Programming language theory and implementation', '3-0-6'],
            ['CSX3009', 'Algorithm Design', 3, 'Advanced algorithm design and analysis techniques', '3-0-6'],
            ['CSX2009', 'Cloud Computing', 3, 'Cloud platforms and distributed computing services', '3-0-6'],
            ['CSX3005', 'Computer Network', 3, 'Network protocols and distributed systems', '3-0-6'],
            ['CSX2003', 'Principles of Statistics', 3, 'Statistical methods and data analysis', '3-0-6'],
            ['CSX2006', 'Mathematics and Statistics for Data Science', 3, 'Mathematical foundations for data science applications', '3-0-6'],
            ['CSX2008', 'Mathematics Foundation for Computer Science', 3, 'Discrete mathematics and logic for computer science', '3-0-6'],
            ['ITX2005', 'Design Thinking', 3, 'Human-centered design methodology and innovation', '3-0-6'],
            ['ITX2007', 'Data Science', 3, 'Data analysis mining and visualization techniques', '3-0-6'],
            ['ITX3007', 'Software Engineering', 3, 'Software development lifecycle and project management', '3-0-6'],
            ['ITX3002', 'Introduction to Information Technology', 3, 'Fundamentals of information technology systems', '3-0-6'],
            ['ELE1001', 'Communicative English I', 3, 'Basic English communication skills', '3-0-6'],
            ['ELE1002', 'Communicative English II', 3, 'Intermediate English communication and writing', '3-0-6'],
            ['ELE2000', 'Academic English', 3, 'Academic writing and research skills', '3-0-6'],
            ['ELE2001', 'Advanced Academic English', 3, 'Advanced academic communication and presentation', '3-0-6'],
            ['GE1403', 'Communication in Thai', 2, 'Thai language communication skills', '2-0-4'],
            ['GE2110', 'Human Civilizations and Global Citizens', 2, 'World cultures and global citizenship', '2-0-4'],
            ['GE4044', 'How to be homo', 2, 'Human development and social interaction', '2-0-4'],
            ['CSX4001', 'Advanced Database Systems', 3, 'Database optimization and distributed databases', '3-0-6'],
            ['CSX4002', 'Machine Learning', 3, 'Statistical learning and neural networks', '3-0-6'],
            ['CSX4003', 'Artificial Intelligence', 3, 'AI algorithms and intelligent systems', '3-0-6'],
            ['CSX4004', 'Cybersecurity', 3, 'Information security and cryptography principles', '3-0-6'],
            ['CSX4005', 'Web Development', 3, 'Full-stack web application development', '3-0-6'],
            ['CSX4006', 'Mobile App Development', 3, 'iOS and Android application development', '3-0-6'],
            ['CSX4007', 'Computer Graphics', 3, '2D and 3D graphics programming and rendering', '3-0-6'],
            ['CSX4008', 'Human-Computer Interaction', 3, 'User interface design and usability testing', '3-0-6'],
            ['ITX4001', 'Project Management', 3, 'IT project planning and management methodologies', '3-0-6'],
            ['ITX4002', 'Systems Analysis and Design', 3, 'Information systems analysis and design', '3-0-6'],
            ['ITX4003', 'Database Management Systems', 3, 'Advanced database concepts and administration', '3-0-6'],
            ['ITX4004', 'Network Security', 3, 'Network security protocols and implementation', '3-0-6'],
            ['GE1001', 'Mathematics I', 3, 'Calculus and differential equations', '3-0-6'],
            ['GE1002', 'Mathematics II', 3, 'Integral calculus and series expansion', '3-0-6'],
            ['GE1003', 'Physics I', 4, 'Mechanics and thermodynamics principles', '3-2-8'],
            ['GE1004', 'Physics II', 4, 'Electricity magnetism and wave physics', '3-2-8'],
            ['GE2001', 'Economics', 3, 'Microeconomics and macroeconomics principles', '3-0-6'],
            ['GE2002', 'Psychology', 3, 'Introduction to human behavior and cognition', '3-0-6'],
            ['GE2003', 'Philosophy', 3, 'Logic critical thinking and ethics', '3-0-6'],
            ['GE3001', 'Business Communication', 3, 'Professional communication and presentation skills', '3-0-6'],
            ['ELE3001', 'Technical Writing', 3, 'Technical documentation and report writing', '3-0-6'],
            ['ELE3002', 'Research Methodology', 3, 'Research design and academic writing', '3-0-6']
        ];

        foreach ($sampleData as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 1, $value);
            }
        }
    }
}
