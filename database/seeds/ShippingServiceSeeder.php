<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShippingServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $json = File::get('database/data/shipping_services.json');
        $data = json_decode($json, true);

        foreach ($data as $obj) {
            DB::table('shipping_services')->insert([
                'id' => $obj['id'],
                'name' => $obj['name'],
                'created_at' => $obj['created_at'],
                'updated_at' => $obj['updated_at']
            ]);
        }
    }
}
