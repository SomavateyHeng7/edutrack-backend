# Credit Pools & Levels Capability Review

Reviewed: 2025-12-18

## Summary
- The migrated Laravel backend does not yet expose hierarchical course types, generic course lists, or credit pool entities required by the new feature plan.
- Current endpoints mirror legacy constructs (single-level course types, concentrations, blacklists, elective rules) and lack per-curriculum configuration needed for pools, list overrides, or deterministic ordering.
- Student-facing flows remain stubbed, and there is no pool-aware audit computation to drive readiness results.

## Checklist Mapping

### Course Types (Hierarchy) — Missing
- Course type schema only stores name/color/department; no parent linkage for nesting per [database/migrations/2025_11_11_052116_create_table_name_course_types.php#L11-L20](database/migrations/2025_11_11_052116_create_table_name_course_types.php#L11-L20).
- Controller CRUD ignores parent data and bulk assignment works per department only (no single deepest node per course) per [app/Http/Controllers/API/Chairperson/CourseTypeController.php#L32-L229](app/Http/Controllers/API/Chairperson/CourseTypeController.php#L32-L229).
- Department-course type pivot prevents multiple assignments but has no curriculum scope; bulk assignment writes `curriculum_id` even though schema lacks the column, see [database/migrations/2025_11_11_052330_create_table_name_department_course_types.php#L15-L33](database/migrations/2025_11_11_052330_create_table_name_department_course_types.php#L15-L33).

### Course Lists (Generic) — Missing
- Separate concentration and blacklist tables/controllers persist; there is no unified list model with a type flag or default required-credits field per [database/migrations/2025_11_11_052137_create_table_name_concentrations.php#L11-L27](database/migrations/2025_11_11_052137_create_table_name_concentrations.php#L11-L27) and [database/migrations/2025_11_11_052206_create_table_name_blacklists.php#L11-L27](database/migrations/2025_11_11_052206_create_table_name_blacklists.php#L11-L27).
- Concentration upload expects JSON arrays and does not parse CSV/XLSX per [app/Http/Controllers/API/Chairperson/ConcentrationCourseController.php#L73-L149](app/Http/Controllers/API/Chairperson/ConcentrationCourseController.php#L73-L149); blacklist creation accepts plain course IDs only per [app/Http/Controllers/API/Chairperson/BlacklistController.php#L78-L145](app/Http/Controllers/API/Chairperson/BlacklistController.php#L78-L145).
- Curriculum attachments expose no per-curriculum required credits or label overrides; `curriculum_blacklists` only stores the linkage per [database/migrations/2025_11_11_052524_create_table_name_curriculum_blacklists.php#L11-L29](database/migrations/2025_11_11_052524_create_table_name_curriculum_blacklists.php#L11-L29), and controller responses surface counts without configuration per [app/Http/Controllers/API/Chairperson/CurriculumBlacklistController.php#L29-L147](app/Http/Controllers/API/Chairperson/CurriculumBlacklistController.php#L29-L147).

### Credit Pools — Missing
- No pool tables, models, or routes exist; `routes/api.php` only wires legacy constructs and lacks any `/pools` resources per [routes/api.php#L1-L220](routes/api.php#L1-L220).

### Curriculum Courses / Must-Takes — Partial
- `CurriculumCourse` supports required flags and override columns per [app/Models/CurriculumCourse.php#L14-L46](app/Models/CurriculumCourse.php#L14-L46), but chairperson APIs never surface setters for year/semester or override fields; `CurriculumController::addCourse` stores course links without metadata per [app/Http/Controllers/API/Chairperson/CurriculumController.php#L120-L149](app/Http/Controllers/API/Chairperson/CurriculumController.php#L120-L149), and there is no endpoint to update requirements after creation.

### Pool Computation / Audit — Missing
- No services compute pool credit allocation or enforce deterministic ordering; audit endpoints still expose legacy elective rules (category + credits) per [app/Http/Controllers/API/Chairperson/ElectiveRuleController.php#L18-L182](app/Http/Controllers/API/Chairperson/ElectiveRuleController.php#L18-L182).
- Available-course responses derive simple categories from single course types without pool awareness per [app/Http/Controllers/API/Chairperson/AvailableCourseController.php#L20-L156](app/Http/Controllers/API/Chairperson/AvailableCourseController.php#L20-L156).

### Student Intake — Missing
- Student course upload is stubbed; `CompletedCourseController` returns mock data unless a manual `studentId` is supplied and stores nothing to drive pool auditing per [app/Http/Controllers/API/Student/CompletedCourseController.php#L10-L68](app/Http/Controllers/API/Student/CompletedCourseController.php#L10-L68).
- No transcript ingestion, unmatched-course prompts, or Free Elective routing logic exists in the Laravel codebase.

### Search / Lookup — Partial
- Chairperson-facing course search exists and filters by code/title with role enforcement per [app/Http/Controllers/API/Chairperson/CourseController.php#L18-L68](app/Http/Controllers/API/Chairperson/CourseController.php#L18-L68).
- There is no student-accessible search endpoint; the only student controller is the mocked completed-course service per [routes/api.php#L63-L210](routes/api.php#L63-L210).

### Legacy (Transition) — Missing
- Legacy elective rules remain editable; there is no read-only flag or toggle to hide once pools launch, as seen in [app/Http/Controllers/API/Chairperson/ElectiveRuleController.php#L18-L182](app/Http/Controllers/API/Chairperson/ElectiveRuleController.php#L18-L182).

### Auth / Role — Partial
- Chairperson config endpoints perform role checks, but student auditing endpoints are not implemented. The existing student course API does not require authentication and therefore cannot enforce student-only access per [app/Http/Controllers/API/Student/CompletedCourseController.php#L10-L68](app/Http/Controllers/API/Student/CompletedCourseController.php#L10-L68).

## Risks / Follow-Ups
- Adding parent/child course types will require schema changes (parent_id, nested ordering) plus API updates and potential migration scripts.
- Introducing generic lists and pools demands new tables, pivot metadata (required credits, labels, ordering), and coordinated updates across curriculum editing workflows.
- Pool-aware readiness will need end-to-end redesign of student intake, audit computation, and Free Elective handling; current mocks risk diverging from production behavior.
