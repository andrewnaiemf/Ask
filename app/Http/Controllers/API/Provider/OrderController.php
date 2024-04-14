<?php

namespace App\Http\Controllers\API\Provider;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderOffering;
use App\Models\User;
use App\Notifications\PushNotification;
use App\Rules\ValidateStock;
use App\Traits\AskOrderTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    use AskOrderTrait;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $perPage = $request->header('per_page', 10);

        $orders = User::find(auth()->user()->id)->provider->orders()->where(['type' => 'Order'])
        ->when($request->status == 'New', function ($query) {
            return $query->whereIn('status', ['Accepted','Pending']);
        })
        ->when($request->status == 'Completed', function ($query) {
            return $query->where('status', 'Completed');
        })
        ->when($request->status == 'Rejected', function ($query) {
            return $query->where('status', 'Rejected');
        })
        ->with(['orderItems.product' => function ($query) {
            $query->withTrashed(); // Include soft-deleted products
        },
        'user',
        'address' => function ($query) {
            $query->withTrashed(); // Include soft-deleted addresses
        }
        ])->orderBy('updated_at', 'desc')
        ->simplePaginate($perPage);

        return $this->returnData($orders);
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
        $order = Order::where(['id' => $id,'type' => 'Order'])
        ->with(['orderItems.product' => function ($query) {
            $query->withTrashed(); // Include soft-deleted products
        }, 'user', 'address'])
        ->first();

        return $this->returnData($order);
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
        $order = Order::find($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|In:Accepted,Pending,Shipped,ReadyForShipping,Rejected,Delivered,Completed',
        ]);

        if ($validator->fails()) {
            return $this->returnValidationError($validator->errors()->all());
        }

        $this->updateStatus($order, $request->status);

        ///////////////////////////////////////////////notification logic
        return $this->returnSuccessMessage(__('api.orderUpdatedSuccessfully'));

    }

    private function updateStatus($order, $status){

        $orderStatus = ['Accepted', 'Pending', 'Rejected', 'Completed'];
        $shippingStatus = ['ReadyForShipping', 'Shipped', 'Delivered'];

        if (in_array($status ,$orderStatus))
        {
            $this->updateOrderStatus($order, $status);
            PushNotification::create($order->provider->user_id, $order->user_id, $order, $status.'_order');
            $this->updateOrderPaymentStatus($order, $status);
        }

        if (in_array($status ,$shippingStatus))
        {
            $this->updateOrderShippingStatus($order, $status);
            PushNotification::create($order->provider->user_id, $order->user_id, $order, $status.'_order');
        }
    }

    private function updateOrderShippingStatus($order, $status)
    {
        if($status == 'ReadyForShipping'){
            $status = 'Pending';
        }

        if ($order->status == 'Pending' && $order->shipping_method == 'CaptainAsk') {

            if ($status == 'Pending') {
                $response_status = AskOrderTrait::makeOrder($order);
                if ($response_status != 200) {
                    return $this->returnError('There is something wrong');
                }
            }

            if ($status == 'Shipped') {
                $this->updateOrderPaymentStatus($order, $status);
            }
        } elseif ($order->status == 'Pending' && $order->shipping_method != 'CaptainAsk') {
            $this->updateOrderPaymentStatus($order, $status);
        }

        $order->shipping_status = $status;
        $order->save();
    }

    private function updateOrderStatus($order, $status){
        $order->status = $status;
        $order->save();
    }

    private function updateOrderPaymentStatus($order, $status)
    {
        if ($status == 'Completed' || ($status == 'Shipped' &&  $order->shipping_method == 'Pickup')) {
            $order->payment_status = 'Paid';
        }
        $order->save();
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
