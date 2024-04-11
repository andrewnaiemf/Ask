<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Notifications\PushNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BookingStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'status:booking';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
       $bookings = Booking::whereIn('status', ['Today', 'New', 'Completed'])->get();

        $currentDateTime = Carbon::now();

        $currentDateTime = Carbon::now()->startOfDay();

        foreach ($bookings as $booking) {
            if ($booking->department->id != 35) {//35 is Hotels and hotel apartments main department
                $bookingDateTime = Carbon::create(
                    $booking->bookingDetail->year,
                    $booking->bookingDetail->month,
                    $booking->bookingDetail->day,
                )->startOfDay();

                if ($currentDateTime > $bookingDateTime ) {
                    $booking->update(['status' => 'Expired']);
                    PushNotification::create($booking->user_id ,$booking->provider->user->id ,$booking ,'booking_status');
                }
                if($currentDateTime->eq($bookingDateTime)){
                    $booking->update(['status' => 'Today']);
                    PushNotification::create($booking->user_id ,$booking->provider->user->id ,$booking ,'booking_status');
                }
            }elseif ($booking->department->id == 35) {
                $bookingDateTime = Carbon::create(
                    $booking->hotelBookingDetail->year,
                    $booking->hotelBookingDetail->arrival_month,
                    $booking->hotelBookingDetail->arrival_day,
                )->startOfDay();

                if ($currentDateTime > $bookingDateTime ) {
                    $room = $booking->hotelBookingDetail->roomBookingDetail->room;

                    if($booking->status == 'Completed'){
                        $bookingLeaveDateTime = Carbon::create(
                            $booking->hotelBookingDetail->year,
                            $booking->hotelBookingDetail->departure_month,
                            $booking->hotelBookingDetail->departure_day,
                        )->startOfDay();
                        if($currentDateTime > $bookingLeaveDateTime && $room){
                            $room->update(['busy_numbers' => $room->busy_numbers > 0 ? $room->busy_numbers - 1 : 0]);
                        }
                    }else{
                        $booking->update(['status' => 'Expired']);
                        PushNotification::create($booking->provider->user->id, $booking->user_id ,$booking ,'booking_status');

                        $room->update(['busy_numbers' => $room->busy_numbers > 0 ? $room->busy_numbers - 1 : 0]);
                    }
                }

                if($currentDateTime->eq($bookingDateTime) && $booking->status != 'Completed'){
                    $booking->update(['status' => 'Today']);
                    PushNotification::create($booking->provider->user->id , $booking->user_id ,$booking ,'booking_status');
                }
            }

        }
    }
}
