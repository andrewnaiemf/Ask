<?php

namespace App\Http\Controllers\API\Customer;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
class HomeController extends Controller
{


    public function index(Request $request){

        $token = $request->bearerToken(); // Retrieve the token from the header

        try {
            // Decode the token and retrieve the user data
            $user = JWTAuth::parseToken()->authenticate();
        } catch (JWTException $e) {
            // If the token is invalid or expired, $user will be null
            $user = null;
        }
        if ($user) {
            $user = User::with('city')->find($user->id);
        }

        $mostRate = $this->mostRate();
        $mainDepartments = $this->mainDepartments($user);

        return $this->returnData(['user' => $user, 'mostRate' => $mostRate, 'mainDepartments' => $mainDepartments]);

    }

    function mostRate() {

        $providers = Provider::with(['user','department','subdepartment'])->where('status', 'Accepted')->get()->filter(function ($provider) {
                        return $provider->rating > 3;
                    })->sortByDesc('rating')->values();

        return  $providers;
    }

    function mainDepartments($user){

        $cityId = null;
        if ($user) {
            $cityId = $user->city_id;
        }
        // dd($cityId );
        $departments = Department::with(['subdepartments.providers' => function ($query) use ($cityId) {
            $query->whereHas('user', function ($userQuery) use ($cityId) {
                if ($cityId) {
                    $userQuery->where('city_id', $cityId);
                }
            });
        }])->whereNull('parent_id')->get();

        return  $departments;

    }

    public function search($searchTerm, Request $request){

        $validator = Validator::make($request->all(), [

            'search_for' => 'required|In:department,provider',
        ]);

        if ($validator->fails()) {
            return $this->returnValidationError(401,$validator->errors()->all());
        }

        $perPage = $request->header('per_page', 10);

        if ($request->search_for == 'provider') {

            $data = User::where('account_type', 'provider')->with('provider')
            ->where('name', 'LIKE', '%' . $searchTerm . '%')
            ->simplePaginate($perPage);
        }else if($request->search_for == 'department'){

            $data = Department::where('name_'.app()->getLocale(), 'LIKE', '%' . $searchTerm . '%')
            ->simplePaginate($perPage);
        }
        // $services = Provider::where('service', 'LIKE', '%' . $searchTerm . '%')
        //     ->where('status', 'Accepted')
        //     ->paginate(10);

        return $this->returnData(['result' => $data]);

    }
}
