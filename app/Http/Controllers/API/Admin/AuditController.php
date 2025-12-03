<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AuditLog;

class AuditController extends Controller
{
    /**
     * GET /api/admin/audit
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'SUPER_ADMIN') {
            return response()->json(['error' => 'Unauthorized - Super Admin access required'], 401);
        }

        $limit = (int) $request->input('limit', 50);

        $logs = AuditLog::orderByDesc('created_at')->paginate($limit);

        return response()->json([
            'logs' => $logs->items(),
            'pagination' => [
                'page' => $logs->currentPage(),
                'limit' => $logs->perPage(),
                'total' => $logs->total(),
                'pages' => $logs->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/admin/audit
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'action' => 'required|string|max:255',
            'resource' => 'required|string|max:255',
            'resourceId' => 'nullable',
            'details' => 'nullable',
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => $validated['action'],
            'resource' => $validated['resource'],
            'resource_id' => $validated['resourceId'] ?? null,
            'details' => $validated['details'] ?? null,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Audit log recorded successfully'], 201);
    }
}
