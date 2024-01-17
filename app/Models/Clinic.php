<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Astrotomic\Translatable\Locales;
class Clinic extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_ar','name_en','name_eu'
    ];

    protected $hidden =[
        'created_at',
        'updated_at',
    ];

    protected $append = ['name'];

    public function getNameAttribute(){

        $lang = app(Locales::class)->current();
        return  $this->{'name_'.$lang};
    }

    public function toArray()
    {

        $lang = app(Locales::class)->current();

        $array['id'] = $this['id'];
        $array['name'] = $this->{'name_'.$lang};
        $array['icon'] = $this['icon'] ?? '';
        // $array['providers'] = $this['providers'];
        // $array['schedules'] = $this->schedules;

        // Retrieve the provider attached to the clinic
        $providerId = $this->pivot ? $this->pivot->provider_id : null; // Get the provider ID from the pivot table
        $provider = $this->providers->where('id', $providerId)->first();
        // Check if the provider is available and has clinic schedules
        if ($provider && $provider->clinicSchedules && $provider->clinicSchedules->isNotEmpty()) {
            $providerSchedules = $provider->clinicSchedules->where('clinic_id', $this->id)->values();
            $array['schedules'] = $providerSchedules;
        } else {
            $array['schedules'] = null;
        }
        return $array;
    }

    public function providers()
    {
        return $this->belongsToMany(Provider::class, 'provider_clinic', 'clinic_id', 'provider_id');
    }

    public function schedules()
    {
        return $this->hasMany(ClinicSchedule::class, 'clinic_id');
    }

}
