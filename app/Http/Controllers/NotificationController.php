<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * In-app notification center API.
 *
 * All routes are scoped to auth:sanctum — users see only their own notifications.
 *
 * GET    /api/v1/notifications              list (paginated, optional ?unread=1)
 * GET    /api/v1/notifications/unread-count badge count
 * PUT    /api/v1/notifications/{id}/read    mark one as read
 * PUT    /api/v1/notifications/read-all     mark all as read
 */
final class NotificationController extends Controller
{
    /**
     * GET /api/v1/notifications
     *
     * Returns the authenticated user's notifications, newest first.
     * Pass ?unread=1 to filter to unread only.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()
            ->notifications()
            ->latest();

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $page = $request->integer('page', 1);
        $perPage = min($request->integer('per_page', 20), 100);

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginated->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'type' => $n->data['type'] ?? null,
                'title' => $n->data['title'] ?? null,
                'message' => $n->data['message'] ?? null,
                'action_url' => $n->data['action_url'] ?? null,
                'read' => $n->read_at !== null,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    /**
     * GET /api/v1/notifications/unread-count
     *
     * Returns just the unread count for the notification badge.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * PUT /api/v1/notifications/{id}/read
     *
     * Mark a single notification as read. Returns 403 if it belongs to another user.
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        /** @var DatabaseNotification $notification */
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['read' => true]);
    }

    /**
     * PUT /api/v1/notifications/read-all
     *
     * Mark all unread notifications as read for the authenticated user.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['marked' => true]);
    }

    /**
     * DELETE /api/v1/notifications/{id}
     *
     * Delete (dismiss) a single notification. Returns 403 if it belongs to another user.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var DatabaseNotification $notification */
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->delete();

        return response()->json(null, 204);
    }
}
