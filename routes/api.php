<?php

use App\Http\Controllers\API\AdvertisementController;
use App\Http\Controllers\API\CityController;
use App\Http\Controllers\API\Customer\AddressController;
use App\Http\Controllers\API\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\API\Customer\BookingController as CustomerBookingController;
use App\Http\Controllers\API\Customer\FavoriteController;
use App\Http\Controllers\API\Customer\HomeController as CustomerHomeController;
use App\Http\Controllers\API\Customer\HotelController;
use App\Http\Controllers\API\Customer\NewsController;
use App\Http\Controllers\API\Customer\OrderController;
use App\Http\Controllers\API\Customer\ProviderController;
use App\Http\Controllers\API\Customer\UserController as CustomerUserController;
use App\Http\Controllers\API\HotelServiceController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\Provider\AuthController;
use App\Http\Controllers\API\Provider\BookingController;
use App\Http\Controllers\API\Provider\ClinicSceduleController;
use App\Http\Controllers\API\Provider\DepartmentController;
use App\Http\Controllers\API\Provider\DocumentController;
use App\Http\Controllers\API\Provider\ProductController;
use App\Http\Controllers\API\Customer\ProductController as CustomerProductController;
use App\Http\Controllers\API\Provider\OrderController as ProviderOrderController;
use App\Http\Controllers\API\Provider\RoomController;
use App\Http\Controllers\API\Provider\UserController;
use App\Http\Controllers\API\QuestionController;
use App\Http\Controllers\API\RatingController;
use App\Http\Controllers\API\SuggestionController;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

///////////////////////////////// provider /////////////////////////////

Route::group([

    'prefix' => 'auth'

], function () {



    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::post('reset_password', [AuthController::class, 'reset']);

    Route::get('cities', [CityController::class, 'index' ]);

    Route::get('departments', [DepartmentController::class, 'index' ]);

    Route::get('hotel_services', [HotelServiceController::class, 'hotel_services' ]);
    Route::get('room_type', [HotelServiceController::class, 'hotel_room_type' ]);
    Route::get('bed_type', [HotelServiceController::class, 'hotel_bed_type' ]);

    Route::get('/verify/{id}', function ($id) {

        $provider =  Provider::where('user_id', $id)->first();

        $provider->update(['status' => 'Accepted']);
    });
    Route::get('advertisements',  [AdvertisementController::class, 'index']);


});



Route::group([

    'middleware' => 'auth:api',
    'prefix' => 'auth'

], function () {

    Route::get('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    Route::get('me', [UserController::class, 'me']);
    Route::post('user', [UserController::class, 'update']);

    Route::get('document/destroy/{id}', [DocumentController::class, 'destroy']);

    Route::resource('booking', BookingController::class);

    Route::post('clinic/schedule', [ClinicSceduleController::class, 'store' ]);
    Route::get('clinic/schedule/{id}', [ClinicSceduleController::class, 'show' ]);

    Route::get('notifications', [NotificationController::class, 'index' ]);
    Route::get('notification/{id}', [NotificationController::class, 'show' ]);

    Route::get('questions', [QuestionController::class, 'index' ]);

    Route::post('suggestion', [SuggestionController::class, 'store' ]);

    Route::post('rate', [RatingController::class, 'store' ]);

    Route::resource('room', RoomController::class);
    Route::post('room/update/{id}', [RoomController::class , 'update_room']);

    Route::delete('room/{id}/image', [RoomController::class , 'deleteImage']);

    Route::resource('product', ProductController::class);
    Route::post('product/update/{id}', [ProductController::class , 'update_product']);
    Route::delete('product/{id}/image', [ProductController::class , 'deleteImage']);

    Route::resource('order', ProviderOrderController::class);


});



///////////////////////////////// customer //////////////////////////////


Route::group([

    'prefix' => 'auth/customer'

], function () {


    Route::post('register', [CustomerAuthController::class, 'register']);
    Route::post('login', [CustomerAuthController::class, 'login']);

    Route::post('reset_password', [CustomerAuthController::class, 'reset']);

});




Route::group([

    'prefix' => 'auth/customer'

], function () {

    // Authenticated routes
    Route::middleware('auth:api')->group(function () {
        Route::get('me', [CustomerUserController::class, 'me']);
        Route::post('user', [CustomerUserController::class, 'update']);
        Route::resource('address', AddressController::class);
        Route::resource('booking', CustomerBookingController::class);
        Route::resource('news', NewsController::class)->except('index');
        Route::get('askForAddNews', [NewsController::class, 'askForAddNews']);
        Route::resource('favorite', FavoriteController::class);
        Route::get('search/{word}', [CustomerHomeController::class, 'search']);
        Route::post('room/filter', [HotelController::class, 'filter']);
        Route::resource('order', OrderController::class);
        Route::post('cart', [OrderController::class, 'cart']);
        Route::delete('cart', [OrderController::class, 'deleteCart']);
        Route::post('edit/cart', [OrderController::class, 'updateCart']);
        Route::get('cart', [OrderController::class, 'showCart']);
        Route::get('product/{id}', [CustomerProductController::class , 'show']);

    });

    // Guest routes
    Route::get('news', [NewsController::class, 'index']);
    Route::get('homeScreen', [CustomerHomeController::class, 'index']);
    Route::get('provider/{id}', [ProviderController::class, 'show']);

});
