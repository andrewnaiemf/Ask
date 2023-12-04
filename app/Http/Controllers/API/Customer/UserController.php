<?php

namespace App\Http\Controllers\API\Customer;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Department;
use App\Models\DocumentProvider;
use App\Models\Order;
use App\Models\Provider;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Services\ScheduleService;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
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

    public function me(){

        $user = User::with('city')->find(auth()->user()->id);
        $user['cart'] = Order::where('type','Cart')->with('orderItems.product')->first();
        return $this->returnData(['user' => $user]);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request){
        $userId = auth()->user()->id;
        $validation =  $this->validateUserData( $request );

        if ( $validation) {
            return $validation;
        }

        $customerData = $request->except([ 'profile']);

        $this->updateProfilePicture($request);

        if (!empty($customerData)) {
            $customer = User::find($userId);
            $customer->update($customerData);
        }

        return $this->returnSuccessMessage( trans("api.user'sdataUpdatedSuccessfully") );
    }

    public function updateProfilePicture($request){

        $user = User::find(auth()->user()->id);
        $path = 'Customer/' .$user->id. '/';

        if ($request->has('profile')) {
            $profile = $request->file('profile');

            if ($profile) {
                // Update the profile picture
                $imageName = $profile->hashName();
                $profile->storeAs($path, $imageName);
                $fullPath = $path.$imageName;

                $user->update([
                    'profile' => $fullPath
                ]);
            } else {
                // Remove the profile picture
                $this->removeProfilePicture($user);
            }
        }
    }
    private function removeProfilePicture($user)
    {
        $userProfile = $user->profile;

        if ($userProfile) {
            // Remove the picture from storage
            Storage::delete($userProfile);

            // Delete the picture from the database
            $user->update([
                'profile' => ""
            ]);
        }
    }


    public function validateUserData ( $request ) {

        $validator = Validator::make($request->all(), [


            'phone' => ['nullable',
                    'numeric',
                    Rule::unique('users')->ignore(auth()->user()->id),
                ],
            'city_id' => 'nullable|exists:cities,id',
            'name' => 'nullable|string',
            'profile' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',

        ]);

        if ($validator->fails()) {
            return $this->returnValidationError(401,$validator->errors()->all());
        }
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
