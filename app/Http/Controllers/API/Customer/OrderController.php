<?php

namespace App\Http\Controllers\API\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderOffering;
use App\Models\User;
use App\Rules\ValidateStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        ->with(['orderItems.product','provider.user','address'])
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
            return $this->returnError('You already have cart');
        }

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

        $order->load('orderItems');

        return $this->returnData($order);

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

        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable|exists:products,id',
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
                return $this->returnError('api.InvalidCoupon');
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
            $order->update(['type' => $request->type, 'status' => 'Accepted']);
            return $this->returnSuccessMessage('api.orderCreatedSuccessfully');
        }

        return $this->returnSuccessMessage('api.cartUpdatedSuccessfully');
    }

    public function updateShipping($request, $order)
    {

        // if ($request->shipping_method == 'OurDelivery') {
        //     $offer = ProviderOffering::where('provider_id', $order->provider_id)->first();

        //     $delivey_fees = $offer->delivey_fees;
        // }
        $order->update([
            'shipping_method' => $request->shipping_method
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
        $orderItem = $order->orderItems()->where('product_id', $request->product_id)->first();

        if ($request->qty !== 0) {
            if ($orderItem) {

                $orderItem->qty = $request->input('qty');
                $orderItem->save();

            } else {

                $product = Product::find($request->product_id);
                $newOrderItem = new OrderItem([
                    'product_id' => $product->id,
                    'qty' => $request->qty,
                    'unit_price' =>  $product->price
                ]);

                $order->orderItems()->save($newOrderItem);
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
        $order = Order::where(['user_id' => auth()->user()->id, 'type' => 'Cart'])->first();

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
                $order->save();
            }
        }
        $order->load('orderItems.product');
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
        ->with(['orderItems','user','provider','address'])->first();

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
