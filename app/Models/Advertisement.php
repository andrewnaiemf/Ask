<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use JsonSerializable;


class Advertisement extends Model  implements JsonSerializable
{
    use HasFactory ,SoftDeletes;

    public $fillable = ['image_en','image_ar'];

    protected $appends = ['image'];

    protected $visible = ['id', 'image'];

    public function getImageAttribute()
    {
        return $this->{'image_' . app()->getLocale()};
    }

    public function jsonSerialize()
    {
        $data = parent::jsonSerialize();

        if (request()->is('api/*')) {
            $data['image'] = $this->getAttribute('image_' . app()->getLocale());
        }

        return $data;
    }

}
