<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->string('vendor_order_no');
            $table->string('address')->nullable();
            $table->string('arriving_date')->nullable();
            $table->timestamp('ordered_date')->nullable();
            $table->string('items_total_price')->nullable();
            $table->string('shipping_price')->nullable();
            $table->string('tax')->nullable();
            $table->string('total_price')->nullable();
            $table->foreignId('shipping_service_id')->nullable()->constrained('shipping_services');
            $table->string('shipping_tracking_id')->nullable();
            $table->timestamps();
            $table->index('shipping_tracking_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
