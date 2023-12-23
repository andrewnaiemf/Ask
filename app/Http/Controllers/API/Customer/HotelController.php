<?php

namespace App\Http\Controllers\API\Customer;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Pagination\LengthAwarePaginator;

class HotelController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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

    public function filter(Request $request)
    {
        $validation = $this->validateFilterData($request);

        if ( $validation) {
            return $validation;
        }
        $filteredRooms = Room::filterRooms($request->all())->where('provider_id',$request->provider_id)->get();

        $filteredRooms->each(function ($room) use ($request) {
            $room->total_cost = $room->calculateTotalCost($request->all());
        });

        // Sort rooms based on suitability (adjust conditions as needed)
        $suitableRooms = $filteredRooms->filter(function ($room) {
            return $room->adults >= 4 && $room->kids >= 3;
        });

        $unsuitableRooms = $filteredRooms->filter(function ($room) {
            return !($room->adults >= 4 && $room->kids >= 3);
        });

        // Combine suitable and unsuitable rooms while preserving the order
        $sortedRooms = $suitableRooms->concat($unsuitableRooms);

        // Eager load room type and beds information
        $sortedRooms->load(['roomType', 'beds']);


        // Paginate the combined rooms
        $perPage = $request->header('per_page', 10); // You can adjust the per_page value
        $currentPage = $request->get('page', 1);

        // Convert each room object to an array
        $roomArrayData = $sortedRooms->map(function ($room) {
            return $room->toArray();
        })->toArray();

        // Create a paginator instance
        $paginator = new LengthAwarePaginator(
            array_slice($roomArrayData, ($currentPage - 1) * $perPage, $perPage),
            count($roomArrayData),
            $perPage,
            $currentPage
        );

        return $this->returnData($paginator);
    }

    public function validateFilterData ( $request ) {

        $validator = Validator::make($request->all(), [
            'adults' => 'integer',
            'kids' => 'integer',
            'year' => 'required|digits:4',
            'arrival_month' => 'required|digits:2',
            'arrival_day' => 'required|digits:2',
            'arrival_time' => 'required|date_format:H:i:s',
            'departure_month' => 'required|digits:2',
            'departure_day' => 'required|digits:2',
            'departure_time' => 'required|date_format:H:i:s',
        ]);

        $validator->sometimes(['adults', 'kids'], 'required_without_all:adults,kids', function ($input) {
            return empty($input->adults) && empty($input->kids);
        });

        if ($validator->fails()) {
            return $this->returnValidationError(401,$validator->errors()->all());
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
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

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
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
