<?php

namespace App\Http\Controllers\API\Provider;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\ClinicSchedule;
use App\Models\DocumentProvider;
use App\Models\HotelRating;
use App\Models\HotelSchedule;
use App\Models\Provider;
use App\Models\ProviderOffering;
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

    public function me()
    {
        $user = User::find(auth()->user()->id);

        $user_status = $user->provider->status;
        if($user_status  != 'Accepted') {
            return $this->returnError(__('api.pleaseContactWithAdministrator'));
        }

        $providerId = $user->provider->id; // Assign the provider ID to $providerId

        // Check if the authenticated user is the same as the provider
        if ($user->provider->id !== $providerId) {
            return $this->returnError(__('api.unauthorized'));
        }


        $user->load(['provider.department',
                        'provider.subdepartment',
                        'provider.images',
                        'provider.ratings',
                        'provider.clinics.schedules.clinicScheduleDoctors',
                        'provider.categories',
                        'provider.products' => function ($query) {
                            $query->notDeletedCategory();
                        },
                    ]);

        $providerData = $user->toArray();
        $providerData['provider']['clinics'] = [];

        foreach ($user->provider->clinics as $clinic) {
            $clinicData = $clinic->toArray();
            $clinicData['schedules'] = [];

            foreach ($clinic->schedules as $schedule) {
                // Check if the schedule belongs to the provider
                if ($schedule->provider_id === $providerId) {
                    $scheduleData = $schedule->toArray();
                    $scheduleData['doctors'] = $schedule->clinicScheduleDoctors;
                    unset($scheduleData['clinic_schedule_doctors']);

                    $clinicData['schedules'][] = $scheduleData;
                }
            }

            $providerData['provider']['clinics'][] = $clinicData;
        }


        if ( $user->provider->department->id == 35) {
            $providerData['hotel_schedule'] = $user->provider->hotelSchedule;
            $providerData['schedule'] = null;
        }else{
            $scheduleService = new ScheduleService();
            $workTime = $scheduleService->getProviderWorkTime($user->provider->id);
            $providerData['schedule'] =  $workTime ;
            $providerData['hotel_schedule'] = null;
        }
        return $this->returnData(['user' => $providerData]);
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
        $provider = Provider::where('user_id' , $userId)->first();
        $validation =  $this->validateUserData( $request );

        if ( $validation) {
            return $validation;
        }

        $providerData = $request->except(['city_id', 'email', 'profile', 'image']);

        $this->workDescription($userId, $request['email'] , $request['city_id']);

        $this->providerDocuments($request, $userId, $request['profile'] , $request['image']);

        if ($request['schedule'] &&  $provider->department->id != 35) {

            $this->providerSchedule($userId, $request['schedule'] ,$request['open_all_time']);

        }else if($request['schedule'] &&  $provider->department->id == 35){

            $this->providerHotelSchedule($userId, $request['schedule']);

        }

        if( $provider->department->id == 35 ){////Hotels and hotel apartments
            $request['service'] = json_encode($request['service']);

            if (isset($request['hotel_rating'])) {

                $hotel_rating = $request['hotel_rating'];
                $hotelrating = HotelRating::where('provider_id', $provider->id)->first();

                if ( $hotelrating) {
                    $hotelrating->update(['rating' => $hotel_rating]);
                }else{
                    HotelRating::create([
                        'provider_id' => $provider->id,
                        'rating' => $hotel_rating,
                    ]);
                }

            }
        }

        if( in_array($provider->subdepartment->id, ['40']) ){///e-commerce


            $offering = ProviderOffering::where('provider_id', $provider->id)->first();

            if ( $offering ) {
                $offering_data = [
                    'provider_id' => $provider->id,
                    'delivery_time' => $request->delivery_time ?? $offering->delivery_time,
                    'coupon_name' => $request->coupon_name ?? $offering->coupon_name,
                    'coupon_value' => $request->coupon_value ?? $offering->coupon_value,
                    'delivery_fees' => $request->delivery_fees ?? $offering->delivery_fees
                ];
                $offering->update($offering_data);
            }else{
                $offering_data = [
                    'provider_id' => $provider->id,
                    'delivery_time' => $request->delivery_time,
                    'coupon_name' => $request->coupon_name,
                    'coupon_value' => $request->coupon_value,
                    'delivery_fees' => $request->delivery_fees
                ];
                ProviderOffering::create($offering_data);
            }

        }

        if (!empty($providerData)) {
            $provider = Provider::where('user_id',$userId)->first();
            $provider->update($providerData);
        }

        return $this->returnSuccessMessage( trans("api.user'sdataUpdatedSuccessfully") );
    }

    public function workDescription($userId, $email, $city_id){
        $userData = [];
        if ($email) {
            $userData['email'] = $email;
        }

        if ($city_id) {
            $userData['city_id'] = $city_id;
        }

        if (!empty($userData)) {
            $user = User::find($userId);
            $user->update($userData);
        }

    }

    public function providerDocuments($request, $userId, $profile, $image){

        $user = User::find($userId);
        $path = 'Provider/' .$userId. '/';

        $this->updateProfilePicture($request, $user, $path);

        if ($image) {
            $this->updateProviderPlaceImages($user,$image);
        }

    }

    public function providerSchedule($userId, $schedules, $open_all_time){
        $provider = Provider::where('user_id',$userId)->first();


        $provider->update(['open_all_time' => $open_all_time]);
        foreach ($schedules as $id => $schedule) {
            $day = $provider->schedule()->where('day_of_week', $schedule['day_of_week'])->first();
            if($day){
                $day->update($schedule);
            }else{
                $schedule['provider_id'] = $provider->id;
                Schedule::create( $schedule);
            }
        }

    }
    public function providerHotelSchedule($userId, $schedules){
        $provider = Provider::where('user_id',$userId)->first();

        if ($provider->hotelSchedule) {
            // If a hotel schedule exists, update its attributes
            $provider->hotelSchedule->update([
                'arrival_start_time' => $schedules['arrival']['start_time'],
                'departure_end_time' => $schedules['departure']['end_time'],
            ]);
        } else {
            // If a hotel schedule doesn't exist, create a new one
            $hotelSchedule = new HotelSchedule([
                'arrival_start_time' => $schedules['arrival']['start_time'],
                'departure_end_time' => $schedules['departure']['end_time'],
            ]);
            $provider->hotelSchedule()->save($hotelSchedule);
        }
    }

    public function updateProfilePicture($request, $user, $path)
    {
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

    public function updateProviderPlaceImages($user, $images){

        $path = 'Provider/' .$user->id. '/placeImages/';

       foreach ($images as $image) {

            $imageName = $image->hashName();
            $image->storeAs($path,$imageName);
            $full_path = $path.$imageName;

            DocumentProvider::create([
                'provider_id' => $user->provider->id,
                'name' => 'describe_image',
                'path' => $full_path,
            ]);

        }
    }


    public function validateUserData ( $request ) {

        $validator = Validator::make($request->all(), [

            'info' => 'nullable|string|max:255',
            'hotel_rating' => 'nullable|integer|In:0,1,2,3,4,5',
            'service' => 'nullable|max:255',
            'city_id' => 'nullable|exists:cities,id',
            'email' => [
                'nullable',
                'email',
                Rule::unique('users')->ignore(auth()->user()->id),
            ],
            'latitude' => ['nullable','string', 'max:255', 'regex:/^[-]?((([0-8]?[0-9])(\.(\d+))?)|(90(\.0+)?))$/'],
            'longitude' => ['nullable','string', 'max:255', 'regex:/^[-]?((([0-9]?[0-9]?[0-9])(\.(\d+))?)|(1[0-7][0-9](\.\d+)?)|(180(\.0+)?))$/'],
            'facebook_link' => ['nullable','string', 'max:255', 'url'],
            'instagram_link' => ['nullable','string', 'max:255', 'url'],
            'twitter_link' => ['nullable','string', 'max:255', 'url'],
            'snapchat_link' => ['nullable','string', 'max:255', 'url'],
            'linkedin_link' => ['nullable','string', 'max:255', 'url'],
            'profile' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'image.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'is_online' => 'nullable|boolean',
            'delivery_fees' => 'nullable|integer',
            'coupon_name' => 'nullable|string|max:255',
            'coupon_value' => 'nullable|string|max:255',
            'delivery_fees' => 'nullable|string|max:255'
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
