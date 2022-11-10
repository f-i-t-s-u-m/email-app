<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds
     *
     * @return void
     */
    public function run()
    {
        $json = File::get('database/data/products.json');
        $data = json_decode($json, true);

        foreach ($data as $obj) {
            DB::table('products')->insert([
                'id' => $obj['id'],
                'name' => $obj['name']
            ]);
        }
    }
}
