<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\ShippingDetail;
use Faker\Generator as Faker;

$factory->define(ShippingDetail::class, function (Faker $faker) {
    $status = [
        'Reached',
        'Left'
    ];
    return [
        'order_id' => App\Models\Order::all()->random()->id,
        'place' => $faker->address,
        'time' => $faker->dateTimeBetween($startDate = '-1 years', $endDate = '+2 months'),
        'status' =>  $status[rand(0, 1)],
    ];
});
