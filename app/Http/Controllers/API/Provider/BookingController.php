<?php

namespace App\Http\Controllers\API\Provider;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\PushNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $perPage = $request->header('per_page', 10);

        $validator = Validator::make($request->all(), [
            'year' => 'required|numeric|min:2023',
            'month' => 'required|numeric|min:1|max:12',
            'day' => 'required|numeric|min:1|max:31',
        ]);

        if ($validator->fails()) {
            return $this->returnValidationError(401,$validator->errors()->all());
        }

        $bookings = Booking::where('provider_id', auth()->user()->provider->id)
                        ->when(auth()->user()->provider->subdepartment_id != 36, function ($query) use ($request) {
                            $query->whereHas('bookingDetail', function ($query) use ($request) {
                                $query->where('year', $request->year)
                                    ->where('month', $request->month)
                                    ->where('day', $request->day);
                            });
                        })
                        /////////////sub_department 36 is hotel for main department 35////////////
                        ->when(auth()->user()->provider->subdepartment_id == 36, function ($query) use ($request) {
                            $query->whereHas('hotelBookingDetail', function ($query) use ($request) {
                                $query->where('year', $request->year)
                                    ->whereRaw("arrival_month <= ?", [$request->month])
                                    ->whereRaw("departure_month >= ?", [$request->month])
                                    ->whereRaw("arrival_day <= ?", [$request->day])
                                    ->whereRaw("departure_day >= ?", [$request->day]);
                            });
                        })
                        // Conditional query: If the request status is 'New,Today', filter by 'New,Today' status.
                        ->when(in_array($request->status, ['New', 'Today']), function ($query) use ($request) {
                            return $query->where('status', $request->status);
                        })
                        // Conditional query: If the request status is 'Completed', filter by 'Completed' status.
                        ->when($request->status == 'Completed', function ($query) use ($request) {
                            return $query->where('status', $request->status);
                        })
                        // Conditional query: If the request status is not 'New,Completed', get all statuses.
                        ->when(!in_array($request->status, ['New', 'Completed', 'Today']), function ($query) use ($request) {
                            return $query->whereNotIn('status', ['New','Completed']);
                        })
                        ->with(['hotelBookingDetail.roomBookingDetail.room.room_type','hotelBookingDetail.roomBookingDetail.room.beds','bookingDetail', 'provider.user', 'user', 'clinicBookings.clinic'])
                        ->orderBy('id', 'desc')
                        ->simplePaginate($perPage);


        return $this->returnData($bookings);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

    }

    /**
     * Display the specified resource.
     *
     * @param  inreger  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $booking = Booking::find($id);
        $booking->load((['hotelBookingDetail.roomBookingDetail.room.roomType','hotelBookingDetail.roomBookingDetail.room.beds','bookingDetail','provider.user','user','clinicBookings.clinic']));

        return $this->returnData($booking);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }


    public function update(Request $request, $id)
    {
        $user = User::find(auth()->user()->id);
        $provider = $user->provider;
        $booking = Booking::where('provider_id', $provider->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Completed,Rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $validatedData = $validator->validated();

        $booking->update($validatedData);
        PushNotification::create($provider->user->id, $booking->user_id, $booking ,'booking_status');
        app()->setLocale($user->lng);

        return $this->returnSuccessMessage( trans("api.bookingUpdatedSuccessfully") );

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
