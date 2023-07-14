<?php

namespace App\Models;

use Astrotomic\Translatable\Locales;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends Model
{
    use HasFactory,SoftDeletes;


    protected $fillable = [
        'user_id', 'commercial_register', 'location', 'info', 'service', 'email',
        'latitude', 'longitude', 'facebook_link', 'instagram_link', 'twitter_link',
        'snapchat_link', 'linkedin_link', 'department_id', 'subdepartment_id',
        'open_all_time','status', 'commercial_register_iamge'
    ];

    protected $dates = ['deleted_at'];

    protected $hidden = [
        'deleted_at',
        'updated_at',
        'created_at',
        'email',
        'latitude',
        'longitude',
        'info',
        'service',
        'facebook_link',
        'instagram_link',
        'twitter_link',
        'snapchat_link',
        'linkedin_link',
        'department_id',
        'subdepartment_id',
        'open_all_time',
        'hotelRatingRelation', // Hide the relationship method name
        'hotel_rating', // Hide the attribute name
    ];


    protected $appends = ['communications', 'description', 'rating'];


    public function getRatingAttribute()
    {
        if (auth()->user()) {
            $average_rating =  $this->ratings()->avg('rate');
            return number_format($average_rating, 2);
        }
        return '0' ;
    }

    public function getCommunicationsAttribute()
    {
        $lang = app(Locales::class)->current();

        $user = User::with('city')
            ->where('id', $this->user_id)
            ->first();

        $fields = [
            'facebook_link',
            'instagram_link',
            'twitter_link',
            'snapchat_link',
            'linkedin_link',
            'phone',
            'email',
            'city',
            'longitude',
            'latitude',
        ];

        $communication = [];

        foreach ($fields as $field) {
            if (!is_null($this->$field)) {
                $communication[$field] = ($field === 'city') ? $user->city->{'name_'.$lang} : $this->$field;
            }
            if($field === 'email'){
                $communication[$field] = $user->email;
            }
        }

        if (empty($communication)) {
            return null;
        }

        return (object) $communication;
    }


    public function getDescriptionAttribute()
    {

        $fields = [
            'info',
            'service',
            'hotel_rating'
        ];

        $isNull = true;

        foreach ($fields as $field) {
            if (!is_null($this->$field)) {
                $isNull = false;
                break;
            }
        }

        if ($isNull) {
            return null;
        }

        if ($this->department->id == 35) {//Hotels and hotel apartments
            $serviceIds = json_decode($this->service);
            $service = isset($serviceIds) ?  HotelService::whereIn('id',$serviceIds)->get() : "";
        } else {
            $service = $this->service;
        }


        return (object) [
            'info' => $this->info,
            'service' => $service,
            'hotel_rating' => $this->getHotelRatingAttribute()
        ];
    }

    public function toArray()
    {
        $array = parent::toArray();

        $array['communications'] = $this->communications;
        // $array['description'] = $this->description;

        return $array;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id' );
    }

    public function subdepartment()
    {
        return $this->belongsTo(Department::class, 'subdepartment_id');
    }

    public function documents()
    {
        return $this->hasMany(DocumentProvider::class);
    }

    public function images()
    {
        return $this->hasMany(DocumentProvider::class)->where('name','describe_image');
    }


    public function ratings()
    {
        return $this->hasMany(Rating::class, 'rated_user_id');
    }

    public function schedule()
    {
        return $this->hasMany(Schedule::class);
    }

    public function clinics()
    {
        return $this->belongsToMany(Clinic::class, 'provider_clinic');
    }

    public function schedules()
    {
        return $this->hasManyThrough(ClinicSchedule::class, Clinic::class);
    }

    public function hotelServices()
    {
        return $this->belongsToMany(HotelService::class, 'provider_hotel_services');
    }

    public function getHotelRatingAttribute()
    {
        if ($this->hotelRatingRelation) {
            return $this->hotelRatingRelation->rating;
        }
        return null;
    }

    public function hotelRatingRelation()
    {
        return $this->hasOne(HotelRating::class, 'provider_id');
    }

    public function hotelSchedule()
    {
        return $this->hasOne(HotelSchedule::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class, 'provider_id');
    }

}
