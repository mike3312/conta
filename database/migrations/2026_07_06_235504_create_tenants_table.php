<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::create('tenants', function (Blueprint $table) {
        $table->id();
        $table->uuid('uuid')->unique();

        $table->string('name', 150);
        $table->string('email')->nullable();
        $table->string('phone', 30)->nullable();

        $table->string('plan')->default('basic');
        $table->string('status')->default('ACTIVE');

        $table->timestamps();
        $table->softDeletes();

        $table->index('name');
        $table->index('status');
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
