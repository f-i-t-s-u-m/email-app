<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->unsignedInteger('userId')->nullable();
            $table->enum('provider', ['gmail', 'outlook', 'other']);
            $table->longText('access_token')->nullable();
            $table->integer('expires_in')->nullable();
            $table->longText('refresh_token')->nullable();
            $table->integer('created')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('accounts');
    }
}
