# Graduation Portal - Backend Updates Summary

> **For Backend Team Reference**  
> Last Updated: January 21, 2026

---

## What's New

### Database Changes

**Added 3 columns to `graduation_portals` table:**
| Column | Type | Description |
|--------|------|-------------|
| `pin_hash` | VARCHAR(255), nullable | Hashed PIN for security |
| `max_file_size_mb` | INTEGER, default 5 | File size limit |
| `closed_at` | TIMESTAMP, nullable | When portal was closed |

**Created new `graduation_portal_logs` table:**
| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `portal_id` | BIGINT | Foreign key to graduation_portals |
| `action` | VARCHAR | Action type (created, closed, pin_verified, etc.) |
| `performed_by` | VARCHAR, nullable | User ID |
| `metadata` | JSON, nullable | Additional context |
| `created_at` | TIMESTAMP | When action occurred |

---

## New API Endpoints

### Public (No Auth)
- `GET /api/public/graduation-portals` - List active portals
- `GET /api/public/graduation-portals/{id}` - Portal details
- `POST /api/public/graduation-portals/{id}/verify-pin` - Verify PIN
- `GET /api/public/graduation-portals/{id}/curricula` - Get curricula

### Session Token Auth (Students)
- `POST /api/graduation-portals/{id}/submit` - Submit courses

### Sanctum Auth (Chairperson/Advisor)
- `POST /api/graduation-portals/{id}/close` - Close portal
- `POST /api/graduation-portals/{id}/regenerate-pin` - Regenerate PIN
- `GET /api/graduation-portals/{id}/cache-submissions` - List cached submissions
- `GET /api/graduation-portals/{id}/cache-submissions/{subId}` - Submission details
- `POST /api/graduation-portals/{id}/cache-submissions/{subId}/validate` - Validate
- `POST /api/graduation-portals/{id}/cache-submissions/{subId}/approve` - Approve
- `POST /api/graduation-portals/{id}/cache-submissions/{subId}/reject` - Reject
- `GET /api/graduation-portals/{id}/cache-submissions/{subId}/report` - Download report
- `POST /api/graduation-submissions/batch-validate` - Batch validate

---

## New Files Created

### Models
- `app/Models/GraduationPortalLog.php` - Audit log model

### Services
- `app/Services/GraduationValidationService.php` - Validates courses against curriculum

### Controllers
- `app/Http/Controllers/PublicGraduationPortalController.php` - Public endpoints for students
- `app/Http/Controllers/GraduationSubmissionController.php` - PDPA-compliant cache-based submissions

### Middleware
- `app/Http/Middleware/ValidateGraduationSession.php` - Session token validation

### Request Validation
- `app/Http/Requests/StoreGraduationPortalRequest.php`
- `app/Http/Requests/UpdateGraduationPortalRequest.php`
- `app/Http/Requests/StoreGraduationSubmissionRequest.php`

### Events (WebSocket)
- `app/Events/NewGraduationSubmission.php`
- `app/Events/SubmissionValidated.php`

### Config
- `config/graduation.php` - Configuration for TTL, cache, rate limiting

### Migrations
- `2026_01_20_100000_add_column_name_graduation_portals.php`
- `2026_01_20_100001_create_graduation_portal_logs_table.php`

---

## Files Updated

| File | Changes |
|------|---------|
| `app/Models/GraduationPortal.php` | Added PIN hashing methods, status methods, scopes |
| `app/Http/Controllers/API/Chairperson/GraduationPortalController.php` | Added `close()`, `regeneratePin()` methods |
| `routes/api.php` | Added public + submission routes |
| `bootstrap/app.php` | Registered `graduation.session` middleware |

---

## PDPA Compliance

- Student submissions stored in **cache only** (not database)
- Auto-expires after **30 minutes**
- No permanent storage of student course data
- PIN is now hashed with bcrypt

---

## Configuration (Optional .env)

```env
GRADUATION_SESSION_TTL=15
GRADUATION_SUBMISSION_TTL=30
GRADUATION_VALIDATE_IP=true
GRADUATION_MAX_FILE_SIZE=5
GRADUATION_CACHE_STORE=file
GRADUATION_MAX_PIN_ATTEMPTS=5
```

---

## No Breaking Changes

- All existing APIs work the same
- Original `pin` column still exists (backward compatible)
- Original database-based submission routes still work
- New cache-based routes added separately (`/cache-submissions/...`)

---

## Testing

Run routes check:
```bash
php artisan route:list --path=graduation
```

Test models:
```bash
php artisan tinker --execute="new \App\Models\GraduationPortal(); new \App\Models\GraduationPortalLog();"
```
