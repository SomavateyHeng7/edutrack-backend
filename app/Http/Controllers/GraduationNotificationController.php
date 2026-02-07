<?php

namespace App\Http\Controllers;

use App\Models\GraduationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GraduationNotificationController extends Controller
{
    /**
     * List notifications for the authenticated user
     * 
     * GET /api/graduation-notifications
     * Query params:
     *   - limit: number of notifications (default 50, max 100)
     *   - unread_only: boolean to filter unread only
     *   - portal_id: filter by portal
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $limit = min((int) $request->get('limit', 50), 100);

            $query = GraduationNotification::forUser($user->id)
                ->with('portal:id,name')
                ->orderBy('created_at', 'desc');

            // Filter by unread only
            if ($request->boolean('unread_only')) {
                $query->unread();
            }

            // Filter by portal
            if ($request->has('portal_id')) {
                $query->forPortal($request->input('portal_id'));
            }

            // Filter by type
            if ($request->has('type')) {
                $query->ofType($request->input('type'));
            }

            $notifications = $query->limit($limit)->get();

            // Format response
            $formatted = $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'data' => $notification->data,
                    'read' => $notification->read,
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at->toIso8601String(),
                    'portal' => $notification->portal ? [
                        'id' => (string) $notification->portal->id,
                        'name' => $notification->portal->name,
                    ] : null,
                ];
            });

            return response()->json([
                'notifications' => $formatted,
                'total' => $formatted->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching graduation notifications: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => ['message' => 'Failed to fetch notifications']
            ], 500);
        }
    }

    /**
     * Get unread notification count
     * 
     * GET /api/graduation-notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $count = GraduationNotification::forUser($user->id)
                ->unread()
                ->count();

            return response()->json([
                'unread_count' => $count,
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting unread count: ' . $e->getMessage());

            return response()->json([
                'error' => ['message' => 'Failed to get unread count']
            ], 500);
        }
    }

    /**
     * Mark a notification as read
     * 
     * POST /api/graduation-notifications/{id}/read
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            $notification = GraduationNotification::forUser($user->id)
                ->where('id', $id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'error' => ['message' => 'Notification not found']
                ], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'message' => 'Notification marked as read',
                'notification' => [
                    'id' => $notification->id,
                    'read' => true,
                    'read_at' => $notification->read_at->toIso8601String(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking notification as read: ' . $e->getMessage());

            return response()->json([
                'error' => ['message' => 'Failed to mark notification as read']
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     * 
     * POST /api/graduation-notifications/mark-all-read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $count = GraduationNotification::forUser($user->id)
                ->unread()
                ->update([
                    'read' => true,
                    'read_at' => now(),
                ]);

            return response()->json([
                'message' => 'All notifications marked as read',
                'marked_count' => $count,
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read: ' . $e->getMessage());

            return response()->json([
                'error' => ['message' => 'Failed to mark notifications as read']
            ], 500);
        }
    }

    /**
     * Delete a notification
     * 
     * DELETE /api/graduation-notifications/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            $notification = GraduationNotification::forUser($user->id)
                ->where('id', $id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'error' => ['message' => 'Notification not found']
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'message' => 'Notification deleted',
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting notification: ' . $e->getMessage());

            return response()->json([
                'error' => ['message' => 'Failed to delete notification']
            ], 500);
        }
    }

    /**
     * Delete all read notifications
     * 
     * DELETE /api/graduation-notifications/clear-read
     */
    public function clearRead(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $count = GraduationNotification::forUser($user->id)
                ->read()
                ->delete();

            return response()->json([
                'message' => 'Read notifications cleared',
                'deleted_count' => $count,
            ]);

        } catch (\Exception $e) {
            Log::error('Error clearing read notifications: ' . $e->getMessage());

            return response()->json([
                'error' => ['message' => 'Failed to clear notifications']
            ], 500);
        }
    }
}
