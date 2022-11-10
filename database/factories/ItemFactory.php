<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Item;
use Faker\Generator as Faker;

$factory->define(Item::class, function (Faker $faker) {
    return [
        'order_id' => App\Models\Order::all()->random()->id,
        'product' => App\Models\Product::all()->random()->name,
        'quantity' => $faker->numberBetween($min = 1, $max = 6),
        'price' => $faker->numberBetween($min = 20, $max = 500),
        'amount' => $faker->numberBetween($min = 50, $max = 2000)
    ];
});
