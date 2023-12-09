<?php

namespace App\Http\Controllers\API\Customer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $perPage = $request->header('per_page', 10);

        $addresses = Address::where('user_id',auth()->user()->id)->simplePaginate($perPage);

        return $this->returnData($addresses);
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
        $userId = auth()->user()->id;
        $validation =  $this->validateAddressData( $request );

        if ( $validation) {
            return $validation;
        }
        $request['user_id'] = auth()->user()->id;
        $address = Address::create($request->all());

        return $this->returnSuccessMessage( trans("api.addressAddedSuccessfully") );
    }


    public function validateAddressData ( $request ) {

        $validator = Validator::make($request->all(), [


            'phone' => 'required|numeric',
            'address' => 'required|string',
            'name' => 'required|string',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'info' => 'nullable|string',

        ]);

        if ($validator->fails()) {
            return $this->returnValidationError(401,$validator->errors()->all());
        }
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
        $userId = auth()->user()->id;

        // Validate the request data
        $validation = $this->validateAddressData($request);
        if ($validation) {
            return $validation;
        }

        // Find the address by ID
        $address = Address::findOrFail($id);

        // Check if the user is the owner of the address
        if ($address->user_id != $userId) {
            return $this->returnErrorMessage(trans("api.unauthorizedAction"));
        }

        // Update the address with the new data
        $address->update($request->all());

        return $this->returnSuccessMessage(trans("api.addressUpdatedSuccessfully"));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $address = Address::findOrFail($id);
        $address->delete();
        return $this->returnSuccessMessage( trans("api.addressDeletedSuccessfully") );

    }
}
