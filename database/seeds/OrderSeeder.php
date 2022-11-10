<?php

use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        factory(App\Models\Order::class, 100)->create()->each(function ($order) {
            $order->shippingDetails()->save(factory(App\Models\ShippingDetail::class)->make());
        });
    }
}
