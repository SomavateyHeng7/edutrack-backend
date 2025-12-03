Here’s a more detailed mapping of your Next.js API routes to recommended Laravel structure.
For each Next.js API route, I’ll suggest the corresponding Laravel controller, route, and method style.

1. admin
audit/, dashboard/, health/, users/
Laravel:
Controller: AdminController
Routes:
GET /api/admin/audit → audit()
GET /api/admin/dashboard → dashboard()
GET /api/admin/health → health()
GET|POST /api/admin/users → users()
2. auth
[...nextauth]/, forgot-password/, reset-password/, signup/
Laravel:
Controller: AuthController
Routes:
POST /api/auth/login → login()
POST /api/auth/forgot-password → forgotPassword()
POST /api/auth/reset-password → resetPassword()
POST /api/auth/signup → signup()
3. available-courses
route.ts
Laravel:
Controller: AvailableCoursesController
Route: GET /api/available-courses → index()
4. blacklists
[id]/, courses/, route.ts
Laravel:
Controller: BlacklistController
Routes:
GET /api/blacklists → index()
GET /api/blacklists/{id} → show($id)
GET /api/blacklists/{id}/courses → courses($id)
5. completed-courses
route.ts
Laravel:
Controller: CompletedCoursesController
Route: GET /api/completed-courses → index()
6. concentrations
[id]/, route.ts
Laravel:
Controller: ConcentrationController
Routes:
GET /api/concentrations → index()
GET /api/concentrations/{id} → show($id)
7. course-types
[id]/, assign/, route.ts
Laravel:
Controller: CourseTypeController
Routes:
GET /api/course-types → index()
GET /api/course-types/{id} → show($id)
POST /api/course-types/assign → assign()
 courses
[courseId]/, bulk-create/, route.ts, search/
Laravel:
Controller: CourseController
Routes:
GET /api/courses → index()
GET /api/courses/{courseId} → show($courseId)
POST /api/courses/bulk-create → bulkCreate()
GET /api/courses/search → search()
9. curricula
[id]/, route.ts
Laravel:
Controller: CurriculaController
Routes:
GET /api/curricula → index()
GET /api/curricula/{id} → show($id)
10. curriculum
[id]/, bscs2022/, route.tsx, template/, upload/
Laravel:
Controller: CurriculumController
Routes:
GET /api/curriculum → index()
GET /api/curriculum/{id} → show($id)
GET /api/curriculum/bscs2022 → bscs2022()
GET /api/curriculum/template → template()
POST /api/curriculum/upload → upload()
11. departments
[id]/, route.ts
Laravel:
Controller: DepartmentController
Routes:
GET /api/departments → index()
GET /api/departments/{id} → show($id)
12. download
sample-csv/, sample-xlsx/
Laravel:
Controller: DownloadController
Routes:
GET /api/download/sample-csv → sampleCsv()
GET /api/download/sample-xlsx → sampleXlsx()
13. faculties
[id]/, route.ts
Laravel:
Controller: FacultyController
Routes:
GET /api/faculties → index()
GET /api/faculties/{id} → show($id)
14. faculty
concentration-label/
Laravel:
Controller: FacultyController
Route: GET /api/faculty/concentration-label → concentrationLabel()
15. public-concentrations
route.ts
Laravel:
Controller: PublicConcentrationController
Route: GET /api/public-concentrations → index()
16. public-curricula
[id]/, route.ts
Laravel:
Controller: PublicCurriculaController
Routes:
GET /api/public-curricula → index()
GET /api/public-curricula/{id} → show($id)
17. public-departments
route.ts
Laravel:
Controller: PublicDepartmentController
Route: GET /api/public-departments → index()
18. public-faculties
route.ts
Laravel:
Controller: PublicFacultyController
Route: GET /api/public-faculties → index()
19. student-profile
route.ts
Laravel:
Controller: StudentProfileController
Route: GET /api/student-profile → index()
20. test
route.ts
Laravel:
Controller: TestController
Route: GET /api/test → index()
21. test-db
route.ts
Laravel:
Controller: TestDbController
Route: GET /api/test-db → index()