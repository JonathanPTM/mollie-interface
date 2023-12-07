<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePtmSubscriptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ptm_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->text('subscribed_on');
            $table->foreignId('plan_id');
            $table->morphs('billable');
            $table->string('mollie_subscription_id')->nullable();
            $table->decimal('tax_percentage', 6, 4)->default(0);
            $table->datetime('ends_at')->nullable();
            $table->integer('cycle')->default(1);
            $table->datetime('cycle_started_at');
            $table->datetime('cycle_ends_at')->nullable();
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
        Schema::dropIfExists('ptm_subscriptions');
    }
}
