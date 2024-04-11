<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [//user_id user who notify the other one
        'user_id', 'notified_user_id', 'type', 'screen', 'data', 'read'
    ];

    protected $hidden =[
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    public function getDataAttribute($data)
    {
        $data = json_decode($data, true);

        $messageTemplateKey = data_get($data, 'message_template', '');

        if ($messageTemplateKey) {
            $sender = data_get($data, 'sender', '');
            $data['message'] = $this->generateMessage($messageTemplateKey, $sender, $data);
        }


        return $data;
    }

    protected function generateMessage($messageTemplateKey, $sender, $data)
    {
        $message = '';
        $currentLanguage = auth()->user()->lng ?? 'en';
        app()->setLocale($currentLanguage);

        switch ($messageTemplateKey) {
            case 'rating_message':
                $data = data_get($data, 'data', '');
                $stars = data_get($data, 'rate', '');
                $message = str_replace(':sender_name', $sender['name'], __('messages.' . $messageTemplateKey, ['stars' => $stars]));
                break;
            default:
                $message = str_replace(':sender_name', $sender['name'], __('messages.' . $messageTemplateKey, []));
                break;
        }

        return $message;
    }



    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function notifiedUser()
    {
        return $this->belongsTo(User::class, 'notified_user_id');
    }



}
