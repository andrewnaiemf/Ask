<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CityDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $cities = City::all();

        foreach ($cities as $city) {
            $departments = Department::all();

            foreach ($departments as $department) {
                $identifier = Str::uuid();
                $city->departments()->attach($department, ['identifier' => $identifier]);
            }
        }

    }
}
