<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductionTables extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('production_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::create('production_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('production_line_id');
            $table->unsignedInteger('produced_qty');
            $table->unsignedInteger('defective_qty');
            $table->dateTime('recorded_at');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('production_line_id')->references('id')->on('production_lines')->onDelete('cascade');
            $table->index('recorded_at');
            $table->index(['recorded_at', 'product_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('production_records');
        Schema::dropIfExists('production_lines');
        Schema::dropIfExists('products');
    }
}
