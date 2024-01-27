<?php

namespace App\Models;

use Astrotomic\Translatable\Locales;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'name_ar', 'name_en', 'name_eu', 'parent_id','icon'
    ];

    protected $append = ['name', 'identifier'];

    public function getNameAttribute(){

        $lang = app(Locales::class)->current();
        return  $this->{'name_'.$lang};
    }



    public function getIdentifierAttribute()
    {
        return $this->cities->pluck('pivot.identifier')->first();
    }

    protected $hidden = [
		'deleted_at',
        'updated_at',
        'created_at'
	];

    public function toArray()
{
    $lang = app(Locales::class)->current();

    $array['id'] = $this['id'];
    $array['name'] = $this->{'name_'.$lang};
    $array['icon'] = $this['icon'];
    $array['identifier'] = $this->identifier;
    if ($this->relationLoaded('subdepartments')) {
        $array['subdepartments'] = $this->subdepartments->map(function ($subdepartment) {
            // Load the providers relationship if it's not already loaded
            if (!$subdepartment->relationLoaded('providers')) {
                $subdepartment->load('providers');
            }

            $subdepartmentProviders = $subdepartment->providers->toArray();

            return array_merge($subdepartment->toArray(), ['providers' => $subdepartmentProviders]);
        })->toArray();
    }
    if ($this->relationLoaded('parent')) {
        $array['parent'] = $this->parent;
    }




    return $array;
}


    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function subdepartments()
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    public function providers()
    {
        return $this->hasManyThrough(
            Provider::class,
            Department::class,
            'id', // Foreign key on the departments table
            'subdepartment_id', // Foreign key on the providers table
            'id', // Local key on the current model (departments table)
            'id' // Local key on the intermediate model (providers table)
        )->where('status', 'Accepted');
    }

    public function cities()
    {
        return $this->belongsToMany(City::class, 'city_department')->withPivot('identifier');
    }
}
