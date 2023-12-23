<?php

namespace App\Notifications;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Http;


class PushNotification
{
    public static function send($reciever, $screen, $message, $notification_data = null, $type = null)
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
        $serverKey = env('FCM_KEY') ?? 'AAAA62Qu0eY:APA91bFfbQiUIwr8Fnm7PapT9frKtOW1yTC-xZDWwtSu5hr1fZsD2Hme_Ki42Ygh41ciaIr_rYKAZ5ofjzm8pNtPVJXamPYRJYXA7d-2c4LcJ52mnDc3uMGssAiHfyGTGc5XaEtbnF7s';
        $devs=[];
        $devices = $reciever->device_token;
        foreach ($devices as $tokens) {
            if( is_array($tokens) ){
                foreach ($tokens as $token){
                    array_push($devs, $token);
                }
            }else{
                array_push($devs, $tokens);
            }
        }

        $data = [
            "registration_ids" =>$devs,
            "notification" => [
                "body" => $message,
                "title" => 'Ask',
                "sound" => "notify.mp3",
                "tag" => "notification"
            ],
            "data" => [
                'screen' => $screen,
                'notification_data' => json_encode($notification_data),
                "body" => $message,
                "title" => 'Ask',
                "type" => $type
            ]
        ];

        $encodedData = json_encode($data);

        $headers = [
            'Authorization:key=' . $serverKey,
            'Content-Type: application/json',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        // Disabling SSL Certificate support temporarly
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedData);

        // Execute post
        $result = curl_exec($ch);

        if ($result === FALSE) {
            die('Curl failed: ' . curl_error($ch));
        }

        // Close connection
        curl_close($ch);

        // FCM response
        return json_decode($result);
    }



    public static function create($sender_id, $resciever_id, $data, $screen)
    {
        $reciever = User::find($resciever_id);
        $sender = User::find($sender_id);

        $friendLocale = $reciever->lng;
        app()->setLocale($friendLocale);

        $messageTemplateKey = '';

        switch ($screen) {
            case 'rating':
                $message = $sender->name . ' ' . __('messages.rating_message', ['stars' => $data->rate]);
                $messageTemplateKey = 'rating_message';
                break;

            case 'booking':
                $message = $sender->name . ' ' . __('messages.New_booking');
                $messageTemplateKey = 'New_booking';
                break;

            case 'booking_status':
                $status = $data->status;
                $statusTranslationKey = 'provider_' . $status . '_booking';
                $actionMessage = __('messages.' . $statusTranslationKey);
                $message = $sender->name . ' ' . $actionMessage;

                $messageTemplateKey = $actionMessage;
                break;
            case 'new_order':
                $message = $sender->name . ' ' . __('messages.new_order_messages');
                $messageTemplateKey = 'new_order';
                break;
            case 'Pending_order':
                $message = $sender->name . ' ' . __('messages.your_order_pending');
                $messageTemplateKey = 'your_order_pending';
                break;
            case 'Rejected_order':
                $message = $sender->name . ' ' . __('messages.your_order_Rejected');
                $messageTemplateKey = 'your_order_Rejected';
                break;
            case 'Completed_order':
                $message = $sender->name . ' ' . __('messages.your_order_Completed');
                $messageTemplateKey = 'your_order_Completed';
                break;
            default:
                $message = '';
                break;
        }

        Notification::create([
            'user_id' =>  $sender_id,
            'notified_user_id' =>  $resciever_id,
            'type' =>  $screen,
            'screen' =>  $screen,
            'data' =>json_encode( [
                'sender' => $sender,
                'date' => date('Y-m-d'),
                'time' => date('H:i:s'),
                'message_template' => $messageTemplateKey,
                'data' => $data
            ])
        ]);

        $placeholder = " :sender_name";
        $message = str_replace($placeholder, '', $message);

        PushNotification::send($reciever, $screen, $message, $data = null, $type = null);
    }
}
