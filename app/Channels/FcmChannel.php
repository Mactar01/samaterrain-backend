<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use App\Services\FcmService;

class FcmChannel
{
    protected $fcmService;

    public function __construct(FcmService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toFcm')) {
            return;
        }

        $message = $notification->toFcm($notifiable);

        // Get the FCM token from the user
        $fcmToken = $notifiable->fcm_token;

        if (!$fcmToken) {
            return;
        }

        $this->fcmService->sendPushNotification(
            $fcmToken,
            $message['title'],
            $message['body'],
            $message['data'] ?? []
        );
    }
}
