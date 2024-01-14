<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Provider;
use App\Models\Rating;
use App\Models\User;
use App\Notifications\PushNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RatingController extends Controller
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
        $prvider_user_id = Provider::findOrFail($request->provider_id)->user_id;
        $request['provider_id'] = $prvider_user_id;

        $validator=Validator::make($request->all(), [
            'rate' => 'required|numeric|between:0,5',
            'feedback' => 'nullable',
            'provider_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return $this->returnValidationError($validator->errors()->all());
        }


        $rate = Rating::create([
            'user_id' =>  $request->provider_id,
            'rate' => $request->rate,
            'feedback' => $request->feedback,
            'rated_user_id' => auth()->user()->id
        ]);

        PushNotification::create($rate->rated_user_id,$rate->user_id ,$rate ,'rating');

        return $this->returnSuccessMessage( trans("api.ratingSetSuccessfully") );

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
