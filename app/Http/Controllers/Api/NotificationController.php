<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * Liste les notifications de l'utilisateur connecté
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->notifications()->take(50)->get();
        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount
        ]);
    }

    /**
     * Marquer une notification comme lue
     */
    public function markRead(Request $request, $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'message' => 'Notification marquée comme lue.',
            'unread_count' => $request->user()->unreadNotifications()->count()
        ]);
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'Toutes les notifications ont été marquées comme lues.',
            'unread_count' => 0
        ]);
    }
}
