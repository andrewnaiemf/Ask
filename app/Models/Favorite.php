<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Favorite extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'item_id',
    ];

    protected $hidden =[
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    public function toArray()
    {

        $array['id'] = $this['id'];
        $array['user_id'] = $this['user_id'];
        $array['type'] = $this['type'];
        $array['news'] = $this['type'] == 'news' ? $this->news : null;
        $array['product'] = $this['type'] == 'product' ? $this->product : null;
        $array['user'] = $this['user'];

        return $array;
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function news()
    {
        return $this->belongsTo(News::class ,'item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class ,'item_id');
    }
}
