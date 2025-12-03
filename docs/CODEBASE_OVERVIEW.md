# EduTrack Backend Codebase Overview

This document provides a high-level overview of the structure and purpose of the main components in the EduTrack backend codebase.

## Directory Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php
│   │   ├── FacultyConcentrationLabelController.php
│   │   ├── PublicConcentrationController.php
│   │   ├── PublicCurriculumController.php
│   │   ├── PublicDepartmentController.php
│   │   ├── PublicFacultyController.php
│   │   └── API/
│   │       ├── SystemSettingController.php
│   │       ├── Admin/
│   │       │   ├── DashboardStatsController.php
│   │       │   ├── DepartmentController.php
│   │       │   ├── FacultyController.php
│   │       │   └── UserController.php
│   │       ├── Auth/
│   │       │   └── AuthController.php
│   │       ├── Chairperson/
│   │       │   ├── AvailableCourseController.php
│   │       │   ├── BlacklistController.php
│   │       │   ├── ConcentrationCourseController.php
│   │       │   ├── CourseController.php
│   │       │   ├── CourseTypeController.php
│   │       │   ├── CurriculaController.php
│   │       │   └── CurriculumController.php
│   │       ├── download/
│   │       │   └── DownloadController.php
│   │       └── Student/
│   │           └── CompletedCourseController.php
│   └── Middleware/
│       └── AuthRedirectMiddleware.php
├── Models/
│   ├── AuditLog.php
│   ├── Blacklist.php
│   ├── BlacklistCourse.php
│   ├── Concentration.php
│   ├── ConcentrationCourse.php
│   ├── Course.php
│   ├── CourseType.php
│   ├── Curriculum.php
│   ├── CurriculumBlacklist.php
│   ├── CurriculumConcentration.php
│   ├── CurriculumConstraint.php
│   ├── CurriculumCourse.php
│   ├── Department.php
│   ├── DepartmentCourseType.php
│   ├── ElectiveRule.php
│   ├── Faculty.php
│   ├── StudentCourse.php
│   ├── SystemSetting.php
│   └── User.php
└── Providers/
    └── AppServiceProvider.php
```

## Main Components

### 1. Controllers
- **API Controllers**: Organized by user roles (Admin, Chairperson, Student) and features (download, system settings).
  - **Admin**: Manage faculties, departments, users, and dashboard statistics.
  - **Chairperson**: Manage courses, concentrations, curricula, course types, available courses, and blacklists.
  - **Student**: Track completed courses.
  - **download**: Handles file downloads (e.g., sample CSV/XLSX).
  - **SystemSettingController**: Manages system-wide settings.
- **Public Controllers**: Provide public endpoints for faculties, departments, curricula, and concentrations.
- **Base Controller**: `Controller.php` is the base class for all controllers.

### 2. Middleware
- **AuthRedirectMiddleware**: Handles authentication redirects and access control.

### 3. Models
- **User, Faculty, Department**: Core entities representing users and organizational structure.
- **Course, CourseType**: Academic courses and their types.
- **Curriculum, CurriculumCourse, CurriculumBlacklist, CurriculumConcentration, CurriculumConstraint**: Curriculum management and relationships.
- **Concentration, ConcentrationCourse**: Academic concentrations and their courses.
- **Blacklist, BlacklistCourse**: Course blacklists for curricula.
- **ElectiveRule**: Rules for elective courses.
- **AuditLog**: Tracks changes and actions for auditing.
- **SystemSetting**: Stores global system settings.
- **StudentCourse**: Tracks student course completions.

### 4. Providers
- **AppServiceProvider**: Registers application services and bindings.

## API Structure
- **Routes** are organized to match controller responsibilities and user roles.
- **Authentication** is handled via `AuthController` and middleware.
- **CRUD operations** are available for most entities (faculties, departments, courses, curricula, etc.).
- **Download endpoints** provide sample files for import/export.

## Usage
- **Admin users** manage organizational data and users.
- **Chairperson users** manage academic data (courses, curricula, concentrations, blacklists).
- **Student users** access their completed courses.
- **Public endpoints** provide read-only access to selected data.

## Extensibility
- The codebase is modular, allowing for easy addition of new controllers, models, and features.
- Relationships between models are managed via Eloquent ORM.

---
