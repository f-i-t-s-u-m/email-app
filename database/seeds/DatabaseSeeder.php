<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(UserSeeder::class);
        $this->call(VendorSeeder::class);
        $this->call(ShippingServiceSeeder::class);
        $this->call(DeliveryStatusesSeeder::class);
        $this->call(OrderSeeder::class);
        $this->call(ProductSeeder::class);
        $this->call(ItemSeeder::class);
    }
}
