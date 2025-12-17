# Migration from NextAuth to Laravel Sanctum - Summary

## Completed Changes

### ✅ Laravel Backend (edutrack-backend)

1. **AuthController Setup**
   - Location: `app/Http/Controllers/API/Auth/AuthController.php`
   - Endpoints configured:
     - `POST /api/login` - Login with email/password, returns user data and token
     - `POST /api/logout` - Logout and delete token (requires auth)
     - `GET /api/user` - Get authenticated user data (requires auth)
   
2. **Sanctum Configuration**
   - ✅ `HasApiTokens` trait added to User model
   - ✅ Stateful domains configured: `localhost`, `localhost:3000`
   - ✅ CORS configured for `localhost:3000` with credentials support
   - ✅ Routes protected with `auth:sanctum` middleware

3. **User Model**
   - Password hashing disabled (storing plain text as requested)
   - Removed `'password' => 'hashed'` cast

### ✅ Next.js Frontend (course-audit)

1. **New Sanctum Authentication Files Created**
   - `src/lib/auth/sanctum.ts` - Sanctum API client with login/logout/getUser functions
   - `src/contexts/SanctumAuthContext.tsx` - React Context for authentication state
   
2. **NextAuth Removed**
   - ✅ Uninstalled `next-auth` package
   - ✅ Deleted `src/app/api/auth/[...nextauth]/` directory
   - ✅ Updated root layout to use `SanctumAuthProvider`
   - ✅ Updated `AuthForm.tsx` to use Sanctum login
   
3. **Components Updated**
   - ✅ `src/app/dashboard/page.tsx` - Now uses `useAuth()` instead of `useSession()`
   - ✅ `src/app/chairperson/page.tsx` - Now uses `useAuth()` instead of `useSession()`

4. **Environment Variables**
   - ✅ `.env` updated with:
     - `NEXT_PUBLIC_API_URL=http://localhost:8000`
     - `DATABASE_URL` added for Prisma

## Still Need to Update

### ⚠️ Components Using NextAuth (Need Manual Updates)

The following components still import from `next-auth/react` and need to be updated:

1. `src/app/sbase/profile/page.tsx`
2. `src/app/chairperson/TentativeSchedule/page.tsx`
3. `src/app/chairperson/StudentCheckList/page.tsx`
4. `src/app/chairperson/info_config/page.tsx`
5. `src/app/chairperson/create/details/page.tsx`

**Migration Pattern:**
```typescript
// OLD (NextAuth)
import { useSession } from 'next-auth/react';
const { data: session, status } = useSession();
const user = session?.user;

// NEW (Sanctum)
import { useAuth } from '@/contexts/SanctumAuthContext';
const { user, isLoading } = useAuth();
```

### 🔧 API Services Configuration

All API fetch calls in `src/services/*.ts` files need to include:
- `credentials: 'include'` for cookie-based authentication
- `Authorization: Bearer ${token}` header for token-based authentication

Example:
```typescript
import { fetchWithAuth } from '@/lib/auth/sanctum';

const response = await fetchWithAuth(`${API_URL}/api/endpoint`, {
  method: 'POST',
  body: JSON.stringify(data),
});
```

## Testing Checklist

- [ ] Start Laravel backend: `php artisan serve`
- [ ] Start Next.js frontend: `pnpm dev`
- [ ] Test login at `/auth`
- [ ] Verify token stored in localStorage
- [ ] Test protected routes redirect to `/auth` when not logged in
- [ ] Test logout functionality
- [ ] Verify API calls include authentication headers
- [ ] Check browser console for CORS errors

## Important Notes

1. **CORS Configuration**: Ensure Laravel's `config/cors.php` includes your Next.js URL
2. **Token Storage**: Currently using `localStorage` - consider `httpOnly` cookies for production
3. **Password Security**: Currently storing plain text passwords - **NOT recommended for production**
4. **Session vs Token**: Using Bearer token authentication - cookies are used for CSRF protection only

## Recommended Next Steps

1. Update remaining components to use `useAuth()` hook
2. Test all protected routes and API endpoints
3. Add proper error handling for authentication failures
4. Consider implementing refresh token mechanism
5. Re-enable password hashing for production security
6. Add role-based access control middleware
7. Implement password reset flow with Laravel
8. Add API rate limiting with Sanctum

## API Endpoints Reference

### Public Endpoints
- `POST /api/login` - Login
- `GET /sanctum/csrf-cookie` - Get CSRF token

### Protected Endpoints (require `auth:sanctum`)
- `POST /api/logout` - Logout
- `GET /api/user` - Get current user
- `GET /api/faculties` - Get faculties
- `GET /api/departments` - Get departments
- `GET /api/curricula` - Get curricula
- And all other CRUD endpoints in `routes/api.php`

## Environment Variables Required

### Laravel (.env)
```
APP_URL=http://localhost:8000
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000
SESSION_DOMAIN=localhost
```

### Next.js (.env)
```
NEXT_PUBLIC_API_URL=http://localhost:8000
DATABASE_URL=postgresql://username:password@localhost:5432/edutrack_db
```
