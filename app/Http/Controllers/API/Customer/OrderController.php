<?php

namespace App\Http\Controllers\API\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderOffering;
use App\Models\User;
use App\Notifications\PushNotification;
use App\Rules\ValidateStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $perPage = $request->header('per_page', 10);

        $orders = User::find(auth()->user()->id)->orders()->where(['type' => 'Order'])
        ->when($request->status == 'New', function ($query) {
            return $query->whereIn('status', ['Accepted','Pending']);
        })
        ->unless($request->status == 'New', function ($query) {
            return $query->whereNotIn('status', ['Accepted','Pending']);
        })
        ->with(['orderItems.product' => function ($query) {
            $query->withTrashed(); // Include soft-deleted products
        },'provider' => function ($query) {
            // Include soft-deleted providers
            $query->withTrashed();
            // Include the associated user, but only include soft-deleted users
            $query->with(['user' => function ($userQuery) {
                $userQuery->withTrashed();
            }]);
        }], 'address')
        ->orderBy('updated_at', 'desc')
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

    public function cart(Request $request)
    {

        $validation = $this->validateCartData($request);

        if ($validation) {
            return $validation;
        }

        $order = Order::where(['user_id' => auth()->user()->id,'type' => 'Cart'])->first();
        if ($order) {

            return response()->json([
                'status' => false,
                'msg' =>__('haveCart'),
                'have_cart' => true
            ], 422);
        }

       $order = $this->createNewCart($request);
       $order->load('orderItems');

        return $this->returnData($order);

    }

    public function createNewCart($request){

        $order = Order::create([
            'user_id' => auth()->user()->id,
            'provider_id' => $request->provider_id,
            'type' => "Cart",
            'address_id' => null,
            'sub_total_price' => null,
            'coupon_amount' => null,
            'total_amount' => null,
            'payment_status' => 'Pending',
            'shipping_status' => null,
            'shipping_method' =>null,
        ]);


        $product = Product::find($request->product_id);
        $orderItem = new OrderItem([
            'product_id' => $product->id,
            'qty' => $request->qty,
            'unit_price' =>  $product->price
        ]);
        $order->orderItems()->save($orderItem);

        $price = $product->price * $request->qty;
        $order->update([
            'total_amount' => $price,
            'sub_total_price' => $price
        ]);

        return $order;

    }

    public function validateCartData($request)
    {

        $validator = Validator::make($request->all(), [
            'provider_id' => 'required|exists:providers,id',
            // 'address_id' => 'required|exists:addresses,id',
            // 'sub_total_price' => 'required|numeric|min:0',
            // 'coupon_amount' => 'numeric|min:0',
            // 'total_amount' => 'required|numeric|min:0',
            // 'shipping_method' => 'required|in:CaptainAsk,OurDelivery',
            'product_id' => 'required|exists:products,id',
            'qty' => [
                'required',
                'integer',
                'min:1',
                new ValidateStock(), // Use the custom validation rule here
            ],
            // 'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->returnValidationError($validator->errors()->all());
        }
    }

    public function updateCart(Request $request)
    {

        $order = Order::where(['user_id' => auth()->user()->id,'type' => 'Cart'])->first();
        $provider = Provider::find( $order->provider_id );
        $provider_products_id = $provider->products->pluck('id')->toArray();

        if($request->force_edit && $request->product_id){
            array_push($provider_products_id, $request->product_id);
        }
        $validator = Validator::make($request->all(), [
            'product_id' => [
                'nullable',
                'exists:products,id',
                function ($attribute, $value, $fail) use ($provider_products_id) {
                    if (!in_array($value, $provider_products_id)) {
                        $fail( __('api.cantMakeOrderFromDifferentProvider'));
                    }
                },
            ],
            'qty' => [
                'nullable',
                'integer',
                new ValidateStock(),
            ],
            'shipping_method' => 'nullable|In:Pickup,OurDelivery',
            'address_id' => 'nullable|exists:addresses,id',
            'type' => 'nullable|In:Order',

        ]);

        if ($validator->fails()) {
            return $this->returnValidationError($validator->errors()->all());
        }

        if (isset($request->qty)) {
            $this->updateQty($request, $order);
        }

        if ($request->coupon) {
            $validCoupon = $this->applyCoupon($request->coupon, $order);
            if (!$validCoupon) {
                return $this->returnError( __('api.InvalidCoupon'));
            }
        }

        if ($request->shipping_method) {
            $this->updateShipping($request, $order);
        }

        if ($request->address_id) {
            $order->address_id = $request->address_id;
        }



        // Recalculate the sub_total_price for the order based on updated order items
        $order->sub_total_price = $order->orderItems->sum(function ($item) {
            return $item->qty * $item->unit_price;
        });

        // Recalculate the total_amount
        $order->total_amount = $order->sub_total_price - $order->coupon_amount;
        if (isset($request->shipping_method) && $request->shipping_method == 'OurDelivery') {
            $order->total_amount += $order->delivery_fees;
        }

        $order->save();

        if ($request->type) {
            $checkStock = $this->chechStock($order);
            if (!$checkStock) {
                return $this->returnSuccessMessage( __('api.someProductIsNotAvailableNow'));
            }
            $order->update(['type' => $request->type, 'status' => 'Accepted']);
            $this->decreaseStock($order);
            PushNotification::create($order->user_id ,$order->provider->user_id ,$order ,'new_order');

            return $this->returnSuccessMessage('api.orderCreatedSuccessfully');
        }

        return $this->returnSuccessMessage( __('api.cartUpdatedSuccessfully'));
    }

    public function updateShipping($request, $order)
    {

        $delivey_fees = 0;
        if ($request->shipping_method == 'Pickup' && $order->shipping_method && $order->shipping_method != 'Pickup') {
            $offer = ProviderOffering::where('provider_id', $order->provider_id)->first();

            $delivey_fees = $offer->delivey_fees;
        }
        $order->update([
            'shipping_method' => $request->shipping_method,
            'total_amount' => $order->total_amount - $delivey_fees,
        ]);
    }

    public function applyCoupon($coupon, $order)
    {
        $offer = ProviderOffering::where('provider_id', $order->provider_id)->first();

        if ($offer && $offer->coupon_name == $coupon) {
            $total_amount = $order->total_amount - $offer->coupon_value;
            if (!$order->coupon_amount) {
                $order->update([
                    'coupon_amount' => $offer->coupon_value,
                ]);
                return true;
            }

        } else {
            return false;
        }

    }


    public function updateQty($request, $order)
    {
        if ($request->force_edit) {
            $provider_id = Product::find($request->product_id)->provider_id;
            $request->provider_id = $provider_id;
            $order->delete();
            return $order = $this->createNewCart($request);
        }

        if($request->orderItemId){
            $orderItem = $order->orderItems()->where('id', $request->orderItemId)->first();
        }

        if($request->product_id) {
            $orderItem = $order->orderItems()->where('product_id', $request->product_id)->first();
        }

        if ($request->qty !== 0) {

            if ($orderItem && !$request->is_add_again) {

                $orderItem->qty = $request->input('qty');
                $orderItem->save();

            } else {

                $product = Product::find($request->product_id);
                $orderItem = new OrderItem([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'qty' => $request->qty,
                    'unit_price' =>  $product->price
                ]);
                $orderItem->save();
            }

        } else {
            if (isset($orderItem)) {
                $orderItem->delete();
            }
        }

        if (count($order->orderItems) == 0) {
            $order->delete();
        }
    }

    public function showCart()
    {
        $order = Order::where(['user_id' => auth()->user()->id, 'type' => 'Cart'])->with('orderItems.product')->first();

        if ($order) {
            // Remove order items where the product is not available or stock is less than quantity
            foreach ($order->orderItems as $item) {
                if (!$item->product || $item->product->stock < $item->qty) {
                    $item->delete();
                }
            }

            $orderItems = OrderItem::where(['order_id' => $order->id])->get();

            if (count($orderItems) == 0) {
                $order->delete();
                $order = null;
            }else{
                $sub_total_price = 0;
                foreach ( $orderItems as $item) {
                    $sub_total_price +=  $item->qty * $item->unit_price;
                }
                $order->sub_total_price = $sub_total_price;
                // Recalculate the total_amount
                $order->total_amount = $order->sub_total_price - $order->coupon_amount;

                if (isset($order->shipping_method) && $order->shipping_method == 'OurDelivery') {
                    $order->total_amount += $order->delivery_fees;
                }

                $order->save();
            }
        }
        return $this->returnData($order);
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
        },
        'provider',
        'user',
        'address' => function ($query) {
            $query->withTrashed(); // Include soft-deleted addresses
        }
        ])->first();

        return $this->returnData($order);
    }

    public function decreaseStock($order) {
        $orderItems = $order->orderItems;

        foreach ($orderItems as $item) {
            $itemQty = $item->qty;
            $product = $item->product;

            if ($product) {
                // Ensure stock does not go below zero
                $product->stock = max(0, $product->stock - $itemQty);
                $product->save();
            }
        }

    }

    public function chechStock($order){
        $orderItems = $order->orderItems;

        foreach ($orderItems as $item) {
            $itemQty = $item->qty;
            $product = $item->product;

            if ($product && $product->stock < $itemQty) {
                return false; // Return false if stock is less than item qty
            }
        }

        return true; // Return true if stock is sufficient for all items
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
     * @return \Illuminate\Http\Response
     */
    public function deleteCart()
    {
        $order = Order::where(['user_id' => auth()->user()->id, 'type' => 'Cart'])->with('orderItems.product')->first();

        if ($order) {
            foreach ($order->orderItems as $item) {
                $item->delete();
            }
        }

        $order->delete();
        return $this->returnSuccessMessage( __('api.cartDeletedSuccessfully'));
    }
}
