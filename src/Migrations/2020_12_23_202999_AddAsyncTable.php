<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAsyncTable extends Migration
{
    public function up()
    {
        Schema::create('async', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('pid')->nullable();
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->string('status');
            $table->longText('payload');
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('async');
    }
}
