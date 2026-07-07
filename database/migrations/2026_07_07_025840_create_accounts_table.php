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
    Schema::create('accounts', function (Blueprint $table) {
        $table->id();

        $table->foreignId('company_id')
            ->constrained()
            ->cascadeOnDelete();

        $table->foreignId('parent_id')
            ->nullable()
            ->constrained('accounts')
            ->nullOnDelete();

        $table->string('code', 50);
        $table->string('name');

        $table->string('account_type', 30);
        $table->string('nature', 20);

        $table->boolean('allows_entries')->default(false);
        $table->unsignedTinyInteger('level')->default(1);
        $table->boolean('is_active')->default(true);

        $table->timestamps();

        $table->unique(['company_id', 'code']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
