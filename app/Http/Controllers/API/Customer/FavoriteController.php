<?php

namespace App\Http\Controllers\API\Customer;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FavoriteController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $perPage = $request->header('per_page', 10);

        $addresses = Favorite::where('user_id',auth()->user()->id)->with('news', 'user', 'product')->simplePaginate($perPage);

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
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:product,news',
            'item_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->returnValidationError($validator->errors()->all());
        }

        $existingFavorite = Favorite::where([
            'user_id' => $request->user_id,
            'type' => $request->type,
            'item_id' => $request->item_id
        ])->first();

        if ($existingFavorite) {
            return $this->returnSuccessMessage(trans("api.AlreadyInFavorites"));
        }
        $favorite = Favorite::create($validator->validated());

        return $this->returnSuccessMessage( trans("api.AddedToFavoriteSuccessfully") );
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $favorite = Favorite::find($id)->with('news','user')->first();

        return $this->returnData(['favorite' => $favorite ]);

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
        $favorite = Favorite::find($id)->delete();

        return $this->returnSuccessMessage( trans("api.RemovedFromFavoriteSuccessfully") );
    }
}
