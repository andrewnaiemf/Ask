<?php

namespace App\Http\Controllers\API\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingDetails;
use App\Models\Clinic;
use App\Models\ClinicBooking;
use App\Models\HotelBookingDetails;
use App\Models\HotelBookingRoom;
use App\Models\Provider;
use App\Models\Room;
use App\Models\User;
use App\Notifications\PushNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->header('per_page', 10);


        $bookings = User::find(auth()->user()->id)->bookings()

                ->when($request->status == 'New', function ($query) use ($request) {
                    return $query->whereIn('status', ['New', 'Today']);
                })

                ->when($request->status != 'New', function ($query) use ($request) {
                    return $query->whereNotIn('status', ['New', 'Today']);
                })
                // ->when($request->status == 'New', function ($query) use ($request) {
                //     return $query->where('status', $request->status);
                // })
                // ->unless($request->status == 'New', function ($query) {
                //     return $query->whereNotIn('status', ['New']);
                // })
                ->with(['hotelBookingDetail.roomBookingDetail.room.roomType','bookingDetail', 'provider.user', 'user', 'clinicBookings.clinic'])
                ->orderBy('id', 'desc')
                ->simplePaginate($perPage);

        return $this->returnData($bookings);
    }

    public function show($id)
    {
        $booking = Booking::find($id);
        $booking->load((['hotelBookingDetail.roomBookingDetail.room.roomType','bookingDetail', 'provider.user', 'user', 'clinicBookings.clinic']));

        return $this->returnData($booking);
    }

    public function store(Request $request)
    {

        $validation =  $this->validateBookingData($request);

        if ($validation) {
            return $this->returnError($validation);
        }

        $provider = Provider::find($request->provider_id);

        $data = [
            'department_id' => $provider->department->id,
            'sub_department_id' => $provider->subDepartment->id,
            'user_id' => auth()->user()->id
        ];

        $data = array_merge($data, $request->all());

        $booking = Booking::create($data);

        if ($request->filled(['year', 'month', 'day', 'time'])) {
            $this->bookingDetail($request, $booking);
        }

        if ($request['clinic_id']) {
            $this->clinicBooking($request, $booking->id);
        }

        if ($request['booking_type'] == 'Hotel') {
            $response = $this->hotelBooking($request, $booking);
            if ($response) {
                return $this->returnError($response);
            }
        }

        PushNotification::create($booking->user_id, $provider->user->id, $booking, 'booking');

        return $this->returnSuccessMessage(trans("api.bookingSentSuccessfully"));
    }

    public function bookingDetail($request, $booking)
    {
        $bookingDetails = new BookingDetails();
        $bookingDetails->year = $request->input('year');
        $bookingDetails->month = $request->input('month');
        $bookingDetails->day = $request->input('day');
        $bookingDetails->time = $request->input('time');
        $booking->bookingDetail()->save($bookingDetails);
    }

    public function clinicBooking($request, $booking_id)
    {
        $clinic = Clinic::find($request['clinic_id']);
        if ($clinic) {
            $clinic_boooking = ClinicBooking::create([
                'booking_id' => $booking_id,
                'doctor_name' => $request->doctor_name,
                'cost' => $request->cost,
                'clinic_id' => $clinic->id,
            ]);
        }
    }

    public function hotelBooking($request, $booking)
    {

        $room_id = $request->input('room_id');
        try {
            \DB::beginTransaction();

            $room = Room::lockForUpdate()->find($room_id); // Lock the room for update
            if ($room->busy_numbers >= $room->numbers) {
                throw new \Exception('The room is fully booked.');
            }
            $hotelBookingDetails = new HotelBookingDetails();
            $hotelBookingDetails->year = $request->input('year');
            $hotelBookingDetails->arrival_month = $request->input('arrival_month');
            $hotelBookingDetails->arrival_day = $request->input('arrival_day');
            $hotelBookingDetails->arrival_time = $request->input('arrival_time');
            $hotelBookingDetails->departure_month = $request->input('departure_month');
            $hotelBookingDetails->departure_day = $request->input('departure_day');
            $hotelBookingDetails->departure_time = $request->input('departure_time');
            $hotelBookingDetails->total_cost = $request->input('total_cost');
            $hotelBookingDetails->adults =  $room->adults;
            $hotelBookingDetails->kids =  $room->kids;

            $booking->hotelBookingDetail()->save($hotelBookingDetails);


            $booking_room = new HotelBookingRoom();
            $booking_room->room_id = $room_id;
            $booking_room->booking_id = $booking->id;


            $room = Room::find($room_id);
            $room->update(['busy_numbers' => $room->busy_numbers + 1]);
            $booking->hotelBookingDetail->roomBookingDetail()->save($booking_room);
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            $booking->forceDelete();
            return $e->getMessage();
        }
    }

    public function validateBookingData($request)
    {


        // Common validation rules for both clinic and hotel booking
        $commonRules = [
            'provider_id' => 'required|exists:providers,id',
            'notes' => 'nullable|string',
        ];

        // Validation rules for clinic booking
        $clinicRules = [
            'year' => 'required|integer|date_format:Y|in:' . date('Y'),
            'month' => [
                'required',
                'digits:2',
                'between:01,12',
                function ($attribute, $value, $fail) {
                    $currentMonth = date('m');
                    if ($value < $currentMonth || $value > $currentMonth + 1) {
                        $fail(trans('validation.month.custom_validation', [
                            'attribute' => trans("validation.attributes.$attribute"),
                            'current_month' => $currentMonth,
                        ]));
                    }
                },
            ],
            'day' => [
                'required',
                'digits:2',
                'between:01,31',
                function ($attribute, $value, $fail) {
                    $currentDay = date('d');
                    if ($value < $currentDay) {
                        $fail(trans('validation.day.custom_validation', [
                            'attribute' => trans("validation.attributes.$attribute"),
                            'current_day' => $currentDay,
                        ]));
                    }
                },
            ],
            'time' => 'required|date_format:H:i',
            'cost' => 'required',
            'doctor_name' => 'required|string',
            'clinic_id' => 'required|exists:clinics,id',
        ];

        // Validation rules for hotel booking
        $hotelRules = [
            'year' => 'required_with:arrival_month,departure_month|integer|date_format:Y|in:' . date('Y'),
            'arrival_month' => [
                'required',
                'digits:2',
                'between:01,12',
                function ($attribute, $value, $fail) {
                    $currentMonth = date('m');
                    if ($value < $currentMonth || $value > $currentMonth + 1) {
                        $fail(trans('validation.month.custom_validation', [
                            'attribute' => trans("validation.attributes.$attribute"),
                            'current_month' => $currentMonth,
                        ]));
                    }
                },
            ],
            'arrival_day' => [
                'required',
                'digits:2',
                'between:01,31',
                function ($attribute, $value, $fail) {
                    $currentDay = date('d');
                    if ($value < $currentDay) {
                        $fail(trans('validation.day.custom_validation', [
                            'attribute' => trans("validation.attributes.$attribute"),
                            'current_day' => $currentDay,
                        ]));
                    }
                },
            ],
            'arrival_time' => 'required_with:year|date_format:H:i',
            'departure_month' => [
                'required',
                'digits:2',
                'between:01,12',
                'after_or_equal:arrival_month',
                function ($attribute, $value, $fail) {
                    $currentMonth = date('m');
                    if ($value < $currentMonth || $value > $currentMonth + 1) {
                        $fail(trans('validation.month.custom_validation', [
                            'attribute' => trans("validation.attributes.$attribute"),
                            'current_month' => $currentMonth,
                        ]));
                    }
                },
            ],
            'departure_day' => [
                'required',
                'digits:2',
                'between:01,31',
                'after_or_equal:arrival_day',
                function ($attribute, $value, $fail) {
                    $currentDay = date('d');
                    if ($value < $currentDay) {
                        $fail(trans('validation.day.custom_validation', [
                            'attribute' => trans("validation.attributes.$attribute"),
                            'current_day' => $currentDay,
                        ]));
                    }
                },
            ],
            'departure_time' => 'required_with:year|date_format:H:i',
            'adults' => 'required_with:year|integer|min:1',
            'kids' => 'required_with:year|integer|min:0',
            'total_cost' => 'required_with:year|integer',
            'room_id' => 'required_with:year|integer|exists:rooms,id',
            'booking_type' => 'required_with:year|in:Hotel',
        ];

        // Determine the booking type and merge the appropriate rules
        $rules = $request->booking_type == 'Hotel'
        ? array_merge($commonRules, $hotelRules)
        : ($request->filled('clinic_id') ? array_merge($commonRules, $clinicRules) : $commonRules);

        if ($request->provider_id && $request->booking_type && $request->booking_type != 'Hotel') {
            return $this->returnError('invalid booking type');
        }

        // Validate the request data
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $validator->errors()->all();
        }
    }

}
