<?php

use Illuminate\Database\Seeder;

class DeliveryStatusesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        App\Models\DeliveryStatus::create([
            'status_en' => 'Ordered',
            'status_de' => 'Bestellt',
        ]);

        App\Models\DeliveryStatus::create([
            'status_en' => 'On Transit',
            'status_de' => 'In Lieferung',
        ]);

        App\Models\DeliveryStatus::create([
            'status_en' => 'Delivered',
            'status_de' => 'Geliefert',
        ]);


    }
}
