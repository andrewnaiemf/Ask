<?php

namespace App\Models;

use Astrotomic\Translatable\Locales;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'provider_id',
        'room_type_id',
        'numbers',
        'adults',
        'kids',
        'outdoor',
        'images',
        'cost',
        'busy_numbers'
    ];

    protected $hidden =[
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    public function getImagesAttribute($value)
    {
        return json_decode($value, true);
    }
    //بعدين ابقا اعملها بال ترانس او اتنين اندر سكول بحيث تكون ترجمة بالتلات لغات
    // public function getOutdoorAttribute($value)
    // {
    //     $lang = app(Locales::class)->current();
    //     if ($lang == 'ar') {
    //         switch ($value) {
    //             case 'Balcony':
    //                 $translate_name = 'بلكونة';
    //                 break;
    //             case 'Head':
    //                 $translate_name = 'تراس';
    //                 break;
    //             default:
    //                 $translate_name = 'اطلالة';
    //                 break;
    //         }
    //         return $translate_name;
    //     }

    //     return $value;
    // }


    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function beds()
    {
        return $this->belongsToMany(Bed::class, 'bed_room')->withTimestamps()->withPivot('numbers');
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function scopeFilterRooms($query, $filterData)
    {
        // if (isset($filterData['adults'])) {
        //     $query->where('adults', '>=', $filterData['adults']);
        // }

        // if (isset($filterData['kids'])) {
        //     $query->where('kids', '>=', $filterData['kids']);
        // }

        $query->whereColumn('numbers', '>', 'busy_numbers');

        return $query;
    }

    public function daysCalculation($arrival_time, $departure_time, $provider_id, $arrivalDate, $departureDate)
    {
        $numberOfDays = 0;
        $provider = Provider::find($provider_id);
        $hotelSchedule = $provider->hotelSchedule; // Retrieve the HotelSchedule using the relationship

        if ($hotelSchedule) {
            $arrivalHotelStart = Carbon::createFromFormat('H:i:s', $hotelSchedule->arrival_start_time);
            $departureHoteleEnd = Carbon::createFromFormat('H:i:s', $hotelSchedule->departure_end_time);
        }

        $arrivalTime = Carbon::createFromFormat('H:i:s', $arrival_time);
        $departureTime = Carbon::createFromFormat('H:i:s', $departure_time);

        // Check if the arrival time is before the hotel's arrival start
        if ($arrivalTime->lt($arrivalHotelStart)) {
            $numberOfDays += 1;
        }

        // Check if the departure time is after the hotel's departure end
        if ($departureTime->gt($departureHoteleEnd)) {
            $numberOfDays += 1;
        }

        $numberOfDays += ($arrivalDate->diffInDays($departureDate) +1 );//1 for include the leave day

        return $numberOfDays;
    }

    public function calculateTotalCost($filterData){

        $dateString = sprintf('%04d-%02d-%02d', $filterData['year'], $filterData['arrival_month'], $filterData['arrival_day']);
        $arrivalDate = Carbon::createFromFormat('Y-m-d', $dateString);

        $dateString = sprintf('%04d-%02d-%02d', $filterData['year'], $filterData['departure_month'], $filterData['departure_day']);
        $departureDate = Carbon::createFromFormat('Y-m-d', $dateString);

        $numberOfDays = $this->daysCalculation($filterData['arrival_time'], $filterData['departure_time'], $filterData['provider_id'], $arrivalDate, $departureDate);

        return $total_cost = $numberOfDays * $this->cost;
    }

}
