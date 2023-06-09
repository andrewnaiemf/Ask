<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicScheduleDoctor extends Model
{
    use HasFactory,SoftDeletes;

    protected $table = 'clinic_schedule_doctors';

    protected $fillable = [
        'clinic_schedule_id','doctor_name','doctor_cost','start_time','end_time'
    ];

    protected $hidden =[
        'deleted_at',
        'created_at',
        'updated_at',
    ];}
