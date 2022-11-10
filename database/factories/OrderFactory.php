<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Order;
use Faker\Generator as Faker;

$factory->define(Order::class, function (Faker $faker) {
    return [
        'user_id' => App\Models\User::all()->random()->id,
        'vendor_id' => App\Models\Vendor::all()->random()->id,
        'vendor_order_no' => $faker->numerify('#######'),
        'address' => $faker->address,
        'arriving_date' => $faker->dateTimeBetween($startDate = '-1 years', $endDate = '+2 months'),
        'ordered_date' => $faker->dateTimeBetween($startDate = '-1 years', $endDate = 'now'),
        'items_total_price' => $faker->numberBetween($min = 500, $max = 1000),
        'shipping_price' => $faker->numberBetween($min = 10, $max = 150),
        'tax' => $faker->numberBetween($min = 5, $max = 100),
        'total_price' => $faker->numberBetween($min = 600, $max = 1500),
        'shipping_service_id' => App\Models\ShippingService::all()->random()->id,
        'shipping_tracking_id' => $faker->numerify('######'),
        'mail_id' => 0
    ];
});
