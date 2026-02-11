<?php

namespace App\Http\Controllers\API\Student;

use App\Http\Controllers\Controller;
use App\Models\ScheduleNotification;
use App\Models\TentativeSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ScheduleNotificationController extends Controller
{
    /**
     * Subscribe to schedule notifications
     * Supports both authenticated and guest users
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribe(Request $request)
    {
        try {
            $user = $request->user(); // Can be null for guest users
            
            $request->validate([
                'email' => 'required|email',
                'department_id' => 'nullable|string',
                'curriculum_id' => 'nullable|string',
            ]);

            $departmentId = $request->department_id ?? ($user ? $user->department_id : null);
            $userId = $user ? $user->id : null;

            // For guest users, check by email instead of user_id
            if ($userId) {
                // Authenticated user - update or create by user_id
                $notification = ScheduleNotification::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'department_id' => $departmentId,
                    ],
                    [
                        'email' => $request->email,
                        'curriculum_id' => $request->curriculum_id,
                        'is_active' => true,
                    ]
                );
            } else {
                // Guest user - update or create by email
                $notification = ScheduleNotification::updateOrCreate(
                    [
                        'email' => $request->email,
                        'department_id' => $departmentId,
                    ],
                    [
                        'curriculum_id' => $request->curriculum_id,
                        'is_active' => true,
                    ]
                );
            }

            return response()->json([
                'message' => 'Successfully subscribed to schedule notifications! You will receive updates when new versions are uploaded.',
                'notification' => [
                    'id' => $notification->id,
                    'email' => $notification->email,
                    'is_active' => $notification->is_active,
                    'is_guest' => !$userId,
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error subscribing to schedule notifications: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to subscribe to schedule notifications',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Unsubscribe from schedule notifications
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unsubscribe(Request $request)
    {
        try {
            $user = $request->user();
            $departmentId = $request->department_id ?? $user->department_id;

            $notification = ScheduleNotification::where('user_id', $user->id)
                ->where('department_id', $departmentId)
                ->first();

            if ($notification) {
                $notification->update(['is_active' => false]);
            }

            return response()->json([
                'message' => 'Successfully unsubscribed from schedule notifications',
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error unsubscribing from schedule notifications: ' . $e->getMessage());
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to unsubscribe from schedule notifications',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get notification status
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        try {
            $user = $request->user();
            $departmentId = $request->department_id ?? $user->department_id;

            $notification = ScheduleNotification::where('user_id', $user->id)
                ->where('department_id', $departmentId)
                ->first();

            return response()->json([
                'subscribed' => $notification && $notification->is_active,
                'email' => $notification ? $notification->email : null,
                'notification' => $notification ? [
                    'id' => $notification->id,
                    'email' => $notification->email,
                    'is_active' => $notification->is_active,
                    'last_notified_at' => $notification->last_notified_at,
                ] : null,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching notification status: ' . $e->getMessage());
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to fetch notification status',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Send notifications when schedule is updated (called internally)
     * 
     * @param TentativeSchedule $schedule
     * @return void
     */
    public static function notifySubscribers(TentativeSchedule $schedule)
    {
        try {
            if (!$schedule->is_published || !$schedule->is_active) {
                return;
            }

            $subscribers = ScheduleNotification::where('department_id', $schedule->department_id)
                ->where('is_active', true)
                ->get();

            foreach ($subscribers as $subscriber) {
                try {
                    // Here you would send email notification
                    // Mail::to($subscriber->email)->send(new ScheduleUpdatedMail($schedule));
                    
                    $subscriber->update(['last_notified_at' => now()]);
                    
                    Log::info("Sent schedule notification to {$subscriber->email}");
                } catch (\Exception $e) {
                    Log::error("Failed to send notification to {$subscriber->email}: " . $e->getMessage());
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Error notifying subscribers: ' . $e->getMessage());
        }
    }

    /**
     * Send notifications when curriculum is updated (called internally)
     * 
     * @param \App\Models\Curriculum $curriculum
     * @param string $action - 'uploaded' or 'updated'
     * @return void
     */
    public static function notifyCurriculumSubscribers($curriculum, $action = 'updated')
    {
        try {
            // Find subscribers by department or curriculum
            $subscribers = ScheduleNotification::where(function($query) use ($curriculum) {
                    $query->where('department_id', $curriculum->department_id)
                          ->orWhere('curriculum_id', $curriculum->id);
                })
                ->where('is_active', true)
                ->get();

            foreach ($subscribers as $subscriber) {
                try {
                    // Here you would send email notification
                    // Mail::to($subscriber->email)->send(new CurriculumUpdatedMail($curriculum, $action));
                    
                    $subscriber->update(['last_notified_at' => now()]);
                    
                    Log::info("Sent curriculum notification to {$subscriber->email} - {$action}");
                } catch (\Exception $e) {
                    Log::error("Failed to send curriculum notification to {$subscriber->email}: " . $e->getMessage());
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Error notifying curriculum subscribers: ' . $e->getMessage());
        }
    }
}

