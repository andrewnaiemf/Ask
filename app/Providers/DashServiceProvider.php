<?php

namespace App\Providers;

use App\Dash\Dashboard\Help;
use App\Dash\Resources\Addresses;
use App\Dash\Resources\AdminGroupRoles;
use App\Dash\Resources\AdminGroups;
use App\Dash\Resources\Admins;
use App\Dash\Resources\Advertisements;
use App\Dash\Resources\AskForNews;
use App\Dash\Resources\Beds;
use App\Dash\Resources\BookingDetails;
use App\Dash\Resources\Categories;
use App\Dash\Resources\Cities;
use App\Dash\Resources\ClinicBookings;
use App\Dash\Resources\ClinicBookingsDetails;
use App\Dash\Resources\Clinics;
use App\Dash\Resources\Departments;
use App\Dash\Resources\Documents;
use App\Dash\Resources\HotelBookings;
use App\Dash\Resources\HotelBookingsDetails;
use App\Dash\Resources\HotelServices;
use App\Dash\Resources\MainDepartments;
use App\Dash\Resources\News;
use App\Dash\Resources\OrderItems;
use App\Dash\Resources\Orders;
use App\Dash\Resources\OtherServiceBookings;
use App\Dash\Resources\PharmacyProducts;
use App\Dash\Resources\Providers;
use App\Dash\Resources\Questions;
use App\Dash\Resources\Ratings;
use App\Dash\Resources\Rooms;
use App\Dash\Resources\RoomTypes;
use App\Dash\Resources\SubDepartments;
use App\Dash\Resources\Suggestions;
use App\Dash\Resources\Users;
use Dash\DashServiceProviderInit;

class DashServiceProvider extends DashServiceProviderInit
{
    /**
     * put your dashboard to rendering in home page
     * @return array
     */
    public static function dashboards()
    {
        return [
            Help::class,
        ];
    }

    /**
     * Put Your Resources Here to register in Dashboard
     * @return array
     */
    public function resources()
    {
        return [
            Users::class,
            Providers::class,
            Documents::class,
            Addresses::class,
            Ratings::class,
            HotelBookingsDetails::class,
            HotelBookings::class,
            Beds::class,
            HotelServices::class,
            Rooms::class,
            RoomTypes::class,
            OtherServiceBookings::class,
            BookingDetails::class,
            Categories::class,
            PharmacyProducts::class,
            ClinicBookings::class,
            ClinicBookingsDetails::class,
            OrderItems::class,
            Orders::class,
            AskForNews::class,
            News::class,
            MainDepartments::class,
            SubDepartments::class,
            Clinics::class,
            Suggestions::class,
            Questions::class,
            Cities::class,
            Advertisements::class
            // Admins::class,
            // AdminGroups::class,
            // AdminGroupRoles::class,
        ];
    }

    /**
     * put notification class
     * @return array
     */
    public static function notifications()
    {
        return [
        ];
    }

    /**
     * Custom Blank Pages
     * @return array
     */
    public static function blankPages()
    {
        return [
        ];
    }

    /**
     * boot method
     * please dont make any change here
     */
    public function boot()
    {
        parent::boot();
    }
}
