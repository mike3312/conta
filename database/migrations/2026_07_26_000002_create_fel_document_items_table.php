<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fel_document_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fel_document_id');
            $table->unsignedInteger('line_number');
            $table->string('goods_or_service', 20)->nullable();
            $table->decimal('quantity', 18, 6)->nullable();
            $table->text('description');
            foreach (['unit_price', 'gross_price', 'discount', 'other_discount', 'taxable_amount', 'tax_amount', 'total'] as $column) {
                $table->decimal($column, 18, 6)->default(0);
            }
            $table->string('classification')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->unique(['fel_document_id', 'line_number'], 'fel_items_document_line_uq');
            $table->foreign('fel_document_id', 'fel_items_document_fk')
                ->references('id')->on('fel_documents')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fel_document_items');
    }
};
