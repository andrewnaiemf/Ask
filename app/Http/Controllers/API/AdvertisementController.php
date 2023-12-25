<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Advertisement;
use App\Traits\GeneralTrait;

class AdvertisementController extends Controller
{
    use GeneralTrait;

    public function index(Request $request){

        $perPage = $request->header('per_page', 10);
        $advertisements = Advertisement::simplePaginate($perPage);

        return $this->returnData([ 'advertisements' => $advertisements ]);


    }
}
