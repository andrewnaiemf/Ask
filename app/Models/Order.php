<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'provider_id',
        'address_id',
        'sub_total_price',
        'coupon_amount',
        'total_amount',
        'type',
        'status',
        'payment_status',
        'shipping_status',
        'shipping_method',
    ];

    protected $hidden =[
        // 'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected $appends = ['delivery_fees'];

    public function getDeliveryFeesAttribute()
    {
        if($this->shipping_method == 'OurDelivery') {
            $provider = $this->provider;
            $delivery_fees = $provider ? $provider->offering->delivery_fees : null;

            return $delivery_fees ?? "0";
        }
        return "0";
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

}
