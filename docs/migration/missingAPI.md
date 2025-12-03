# Missing API Endpoints and Controllers

This document lists the missing controllers and methods in your Laravel backend, based on the mapping from your Next.js API routes to Laravel structure in `nextjs-to-laravel-migration.md`.

## Missing Controllers
- **AdminController**
  - Endpoints for `audit`, `health` are not present. You have `DashboardStatsController` and `UserController`, but not a unified `AdminController`.
- **StudentProfileController**
  - No controller for `/api/student-profile`.
- **TestController**
  - No controller for `/api/test`.
- **TestDbController**
  - No controller for `/api/test-db`.

## Missing Methods in Existing Controllers
- **CourseTypeController**
  - `assign()` method for `/api/course-types/assign` is missing or needs verification.
- **CourseController**
  - `bulkCreate()` and `search()` methods for `/api/courses/bulk-create` and `/api/courses/search` are missing or need verification.
- **CurriculumController**
  - `bscs2022()`, `template()`, and `upload()` methods for `/api/curriculum/bscs2022`, `/api/curriculum/template`, and `/api/curriculum/upload` are missing or need verification.
- **DepartmentController**
  - `show($id)` method for `/api/departments/{id}` is missing or needs verification.
- **FacultyController**
  - `show($id)` and `concentrationLabel()` methods for `/api/faculties/{id}` and `/api/faculty/concentration-label` are missing or need verification.
- **Public Controllers**
  - `show($id)` methods for public controllers (e.g., `PublicCurriculaController`) are missing or need verification.

## Summary Table
| API Route                        | Expected Controller         | Method(s) Missing         |
|----------------------------------|----------------------------|--------------------------|
| /api/admin/audit                 | AdminController            | audit()                  |
| /api/admin/health                | AdminController            | health()                 |
| /api/concentrations              | ConcentrationController    | (fixed)                  |
| /api/student-profile             | StudentProfileController   | index()                  |
| /api/test                        | TestController             | index()                  |
| /api/test-db                     | TestDbController           | index()                  |
| /api/course-types/assign         | CourseTypeController       | assign()                 |
| /api/courses/bulk-create         | CourseController           | bulkCreate()             |
| /api/courses/search              | CourseController           | search()                 |
| /api/curriculum/bscs2022         | CurriculumController       | bscs2022()               |
| /api/curriculum/template         | CurriculumController       | template()               |
| /api/curriculum/upload           | CurriculumController       | upload()                 |
| /api/departments/{id}            | DepartmentController       | show($id)                |
| /api/faculties/{id}              | FacultyController          | show($id)                |
| /api/faculty/concentration-label | FacultyController          | concentrationLabel()     |
| /api/public-curricula/{id}       | PublicCurriculaController  | show($id)                |

---

**Action Required:**
- Create the missing controllers and methods listed above.
- Verify and implement any missing methods in existing controllers.
- Ensure all public controllers have both `index()` and `show($id)` if required.

Refer to `nextjs-to-laravel-migration.md` for the full mapping and details.


