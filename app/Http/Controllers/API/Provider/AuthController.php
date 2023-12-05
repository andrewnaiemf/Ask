<?php

namespace App\Http\Controllers\API\Provider;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Clinic;
use App\Models\ClinicSchedule;
use App\Models\Department;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Provider;
use Astrotomic\Translatable\Locales;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Services\ScheduleService;

class AuthController extends Controller
{
    public function register(Request $request)
    {


        $validator = $this->validateRegistrationRequest($request);

        if ($validator->fails()) {
            return $this->returnError($validator->errors()->all());
        }

        $user = $this->createUser($request);
        $user->load(['provider.department','provider.subdepartment','provider.images','provider.schedule']);

        // $user['provider']['clinics'] = in_array($user->provider->subdepartment->id, ['22', '23'])
        // ? Clinic::select('id', 'name_ar', 'name_en', 'name_eu', 'icon')->get()
        // : null;

        // if ($user['provider']['clinics']) {
        //         $user['provider']['clinics']->each(function ($clinic) {
        //         $clinic->includeSchedules = false;
        //     });
        // }
        // $user['provider']['clinics'] =  in_array($user->provider->subdepartment->id, ['22', '23']) ?
        //                                     Clinic::where('id', '<>', 0)->get() :
        //                                     ($user->provider->subdepartment->id == 24 ?
        //                                     Clinic::where('id', 0)->get() : null);

        if(in_array($user->provider->subdepartment->id, ['22', '23','24'])) {
            $this->clinicSchedule($user);
        }

        if(in_array($user->provider->subdepartment->id, ['40'])) {//e-commerc
            $this->attachCategories($user);
        }

        $user->load(['provider.clinics', 'provider.categories']);

        $credentials = $request->only(['phone','password']);

        $token= JWTAuth::attempt($credentials);
        if (!$token) {
            return $this->unauthorized();
        }

        return  $this->respondWithToken($token, $user);
    }



    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|numeric',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->returnValidationError(401, $validator->errors()->all());
        }
        $remember = $request->boolean('remember_me', false);

        $credentials = request(['phone', 'password']);


        if (! $token = JWTAuth::attempt($credentials, $remember)) {
            return $this->unauthorized();
        }

        $user = User::find(auth()->user()->id);

        if ($user->account_type != 'provider') {
            return $this->unauthorized();
        }

        if ($user->provider) {
            $user_status = $user->provider->status;
            if($user_status  != 'Accepted') {
                return $this->returnError(__('api.pleaseContactWithAdministrator'));
            }
        }

        $providerId = $user->provider->id; // Assign the provider ID to $providerId

        // Check if the authenticated user is the same as the provider
        if ($user->provider->id !== $providerId || $user->account_type != 'provider') {
            return $this->returnError(__('api.unauthorized'));
        }

        $this->device_token($request->device_token, $user);

        $user->load(['provider.department',
                        'provider.subdepartment',
                        'provider.images',
                        'provider.ratings',
                        'provider.clinics.schedules.clinicScheduleDoctors',
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

        if ($user->provider->department->id == 35) {
            $providerData['schedule'] = $user->provider->hotelSchedule;
            $providerData['schedule'] = null;
        } else {
            $scheduleService = new ScheduleService();
            $workTime = $scheduleService->getProviderWorkTime($user->provider->id);
            $providerData['schedule'] =  $workTime ;
            $providerData['hotel_schedule'] = null;
        }
        return $this->respondWithToken($token, $providerData);
    }



    public function reset(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'phone' => 'required|exists:users,phone',
            'password' => 'required|confirmed|string|min:6',
            'password_confirmation' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->returnValidationError(401, $validator->errors()->all());
        }
        $user = User::where('phone', $request->phone)->first();

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return $this->returnSuccessMessage(trans("api.Password_updated_successfully"));
    }



    public function logout()
    {
        auth()->logout();
        return $this->returnSuccessMessage(trans("api.logged_out_successfully"));
    }


    protected function createUser(Request $request)
    {
        $user = User::create([
            'uuid' => strtotime("now"),
            'name' => $request->name,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'city_id' => $request->city_id
        ]);

        $this->device_token($request->device_token, $user);

        $user->update(['account_type' => 'Provider','profile' => '']);
        if($request->file('profile')) {
            $this->userProfile($request->file('profile'), $user);
        }

        $this->attachProviderData($request, $user);

        return $user;
    }



    public function refresh()
    {
        $user=User::find(auth()->user()->id);
        return $this->respondWithToken($user->refresh(), $user);
    }


    protected function respondWithToken($token, $user)
    {
        return $this->returnData(['user' => $user , 'access_token' => $token]);
    }


    private function device_token($device_token, $user)
    {

        if(!isset($user->device_token)) {
            $user->update(['device_token'=>json_encode($device_token)]);
        } else {
            $devices_token = $user->device_token;

            if(! in_array($device_token, $devices_token)) {
                array_push($devices_token, $device_token);
                $user->update(['device_token'=>json_encode($devices_token)]);
            }
        }
    }

    protected function validateRegistrationRequest(Request $request)
    {
        return Validator::make($request->all(), [
            'name' => 'required|string',
            'phone' => 'required|numeric|unique:users',
            'password' => 'required|confirmed|string|min:6',
            'password_confirmation' => 'required',
            'department_id' => 'integer|required|exists:departments,id',
            'subdepartment_id' => 'integer|required|exists:departments,id',
            'location' => 'nullable|string',
            'city_id' => 'integer|required|exists:cities,id',
            'commercial_register' => 'required|string',
            'profile' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'commercial_register_iamge' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'terms' => 'accepted',
            'device_token' => 'required|string'
        ]);
    }

    private function userProfile($profile, $user)
    {

        $path = 'Provider/' .$user->id. '/';

        $imageName = $profile->hashName();
        $profile->storeAs($path, $imageName);
        $full_path = $path.$imageName;
        $user->update(['profile'=> $full_path]);
    }

    private function attachProviderData($request, $user)
    {

        $commercial_register_iamge = $request->file('commercial_register_iamge');
        $path = 'Provider/' .$user->id. '/';

        $imageName = $commercial_register_iamge->hashName();
        $commercial_register_iamge->storeAs($path, $imageName);
        $full_path = $path.$imageName;

        $providerData = [
            'user_id' => $user->id,
            'commercial_register' => $request->commercial_register,
            'location' => $request->location,
            'department_id' => $request->department_id,
            'subdepartment_id' => $request->subdepartment_id,
            'status' =>'Accepted',
            'commercial_register_iamge' => $full_path
        ];

        $provider = Provider::create($providerData);

        return $provider;
    }

    public function clinicSchedule($user)
    {
        $subdepartmentName = Department::findOrFail($user->provider->subdepartment_id)->name_en;

        if(in_array($subdepartmentName, ['Hospitals' , 'Private clinics'])) {
            $clinics = Clinic::where('id', '<>', 100)->get();
            $user->provider->clinics()->attach($clinics);
        } elseif($subdepartmentName == 'Veterinary clinics') {
            $clinics =  Clinic::where('id', 100)->get();
            $user->provider->clinics()->attach($clinics);
        }
        return;
    }

    public function attachCategories($user)
    {
        $categories = Category::where(['department_id' => $user->provider->subdepartment_id])->get();
        $user->provider->categories()->attach($categories);
    }

}
