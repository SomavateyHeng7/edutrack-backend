<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Faculty;
use App\Models\Department;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        echo "🌱 Starting database seeding...\n";

        // --- Default Faculty ---
        $defaultFaculty = Faculty::updateOrCreate(
            ['code' => 'DEFAULT'],
            ['name' => 'Default Faculty', 'code' => 'DEFAULT']
        );
        echo "✅ Created default faculty: {$defaultFaculty->name}\n";

        // --- Default Department (under Default Faculty) ---
        $defaultDepartment = Department::updateOrCreate(
            ['code' => 'DEFAULT_DEPT', 'faculty_id' => $defaultFaculty->id],
            ['name' => 'Default Department', 'code' => 'DEFAULT_DEPT', 'faculty_id' => $defaultFaculty->id]
        );
        echo "✅ Created default department: {$defaultDepartment->name}\n";

        // --- Super Admin (HASHED password for Laravel) ---
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@edutrack.com'],
            [
                'password' => 'superadmin123', // Plain text password (not recommended for production)
                'name' => 'Super Administrator',
                'role' => 'SUPER_ADMIN',
                'faculty_id' => $defaultFaculty->id,
                'department_id' => $defaultDepartment->id,
            ]
        );
        echo "✅ Created super admin user: {$superAdmin->name}\n";

        // --- Sample Faculties ---
        $faculties = [
            ['name' => 'Vincent Mary School of Science and Engineering', 'code' => 'VMES'],
            ['name' => 'Martin de Tours School of Management and Economics', 'code' => 'MSME'],
        ];

        foreach ($faculties as $facultyData) {
            $faculty = Faculty::updateOrCreate(
                ['code' => $facultyData['code']],
                $facultyData
            );
            echo "✅ Created faculty: {$faculty->name}\n";
        }

        // --- Sample Departments ---
        $departments = [
            ['name' => 'Computer Science', 'code' => 'CS', 'faculty_code' => 'VMES'],
            ['name' => 'Business Administration', 'code' => 'BBA', 'faculty_code' => 'MSME'],
        ];

        foreach ($departments as $deptData) {
            $faculty = Faculty::where('code', $deptData['faculty_code'])->first();
            if (!$faculty) {
                echo "⚠️  Skipping department {$deptData['name']}: faculty {$deptData['faculty_code']} not found\n";
                continue;
            }

            $department = Department::updateOrCreate(
                ['code' => $deptData['code'], 'faculty_id' => $faculty->id],
                ['name' => $deptData['name'], 'code' => $deptData['code'], 'faculty_id' => $faculty->id]
            );
            echo "✅ Created department: {$department->name}\n";
        }

        echo "🎉 Database seeding completed successfully!\n\n";
        echo "📋 Super Admin Credentials:\n";
        echo "   Email: superadmin@edutrack.com\n";
        echo "   Password: superadmin123\n\n";
        echo "⚠️  Please change the password after first login!\n";

        // Seed curriculum data from Excel files (if needed)
        // $this->call([
        //     CurriculumDataSeeder::class,
        // ]);
    }
}
