<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePTMOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ptm_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('billable');
            $table->nullableMorphs('confirmatable');
            $table->json('actions')->default('[]');
            $table->boolean('executed')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ptm_orders');
    }
}