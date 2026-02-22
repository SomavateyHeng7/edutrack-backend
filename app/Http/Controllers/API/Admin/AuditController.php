<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AuditLog;

class AuditController extends Controller
{
    /**
     * GET /api/audit-logs
     * Fetch audit logs with optional filtering
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'SUPER_ADMIN') {
            return response()->json(['error' => 'Unauthorized - Super Admin access required'], 401);
        }

        $limit = (int) $request->input('limit', 50);
        $page = (int) $request->input('page', 1);
        $action = $request->input('action');
        $userId = $request->input('user_id');
        $entityType = $request->input('entity_type');

        $query = AuditLog::with('user:id,name,email,role')
            ->orderByDesc('created_at');

        if ($action) {
            $query->where('action', $action);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($entityType) {
            $query->where('entity_type', $entityType);
        }

        $logs = $query->paginate($limit, ['*'], 'page', $page);

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
     * POST /api/audit-logs
     * Record an audit log entry
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'action' => 'required|string|max:255',
            'entity_type' => 'required|string|max:255',
            'entity_id' => 'nullable|string',
            'description' => 'nullable|string',
            'changes' => 'nullable',
            'curriculum_id' => 'nullable|string',
            'course_id' => 'nullable|string',
        ]);

        $log = AuditLog::create([
            'user_id' => $user->id,
            'action' => $validated['action'],
            'entity_type' => $validated['entity_type'],
            'entity_id' => $validated['entity_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'changes' => $validated['changes'] ?? null,
            'curriculum_id' => $validated['curriculum_id'] ?? null,
            'course_id' => $validated['course_id'] ?? null,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Audit log recorded successfully', 'log' => $log], 201);
    }
}
