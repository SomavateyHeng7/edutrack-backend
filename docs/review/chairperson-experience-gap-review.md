# Chairperson Experience Gap Review

Reviewed: 2025-12-18

## 1. Authentication & Session Flow — Partial
- Login still accepts plaintext equality before hashing, so legacy unhashed passwords remain valid and bypass security expectations per [app/Http/Controllers/API/Auth/AuthController.php#L40-L43](app/Http/Controllers/API/Auth/AuthController.php#L40-L43).
- The login handler only relies on auth()->login and never mints Sanctum tokens even though every chairperson route is wrapped in the auth:sanctum middleware block in [routes/api.php#L56-L212](routes/api.php#L56-L212), leaving the SPA token negotiation path undefined.
- No rate limiting, account status checks, or role validation precede authentication success, so locked, inactive, or mis-assigned accounts would still receive sessions per [app/Http/Controllers/API/Auth/AuthController.php#L29-L75](app/Http/Controllers/API/Auth/AuthController.php#L29-L75).

## 2. Dashboard & Analytics — Missing
- All dashboard counts are global (User::count(), Faculty::count(), etc.) with no filtering to the chairperson's faculty context per [app/Http/Controllers/API/Admin/DashboardStatsController.php#L36-L63](app/Http/Controllers/API/Admin/DashboardStatsController.php#L36-L63), so the landing page cannot mirror the scoped KPIs from the legacy chairperson view.
- Growth metrics rely on total system user creation dates and disregard department assignments, which prevents per-program progress tracking per [app/Http/Controllers/API/Admin/DashboardStatsController.php#L74-L88](app/Http/Controllers/API/Admin/DashboardStatsController.php#L74-L88).
- Program completion data is hard-coded instead of sourced from curriculum or audit records, as seen in [app/Http/Controllers/API/Admin/DashboardStatsController.php#L90-L96](app/Http/Controllers/API/Admin/DashboardStatsController.php#L90-L96), so readiness charts would stay static.

## 3. Faculty Branding Utilities — Partial
- Concentration label editing is limited to a single string on the logged-in chairperson's faculty; there is no way to preview defaults or manage multi-faculty assignments per [app/Http/Controllers/API/Chairperson/FacultyLabelController.php#L13-L58](app/Http/Controllers/API/Chairperson/FacultyLabelController.php#L13-L58).
- The underlying schema only stores one text field with a universal default of "Concentrations," providing no support for localized or per-program overrides as required by the checklist per [database/migrations/2025_11_11_051901_create_table_name_faculties.php#L12-L19](database/migrations/2025_11_11_051901_create_table_name_faculties.php#L12-L19).

## 4. System Settings & Global Toggles — Missing
- The API only exposes a read-only index endpoint for authenticated users, so chairpersons cannot persist UI toggles (dark mode, term switches, etc.) despite the controller offering CRUD methods per [routes/api.php#L190-L192](routes/api.php#L190-L192) and [app/Http/Controllers/API/SystemSettingController.php#L12-L50](app/Http/Controllers/API/SystemSettingController.php#L12-L50).
- There is no role gate or ownership check around system setting mutations, meaning any authenticated role could create or delete critical flags once routes are wired, conflicting with the chairperson-only configuration requirement per [app/Http/Controllers/API/SystemSettingController.php#L18-L50](app/Http/Controllers/API/SystemSettingController.php#L18-L50).
