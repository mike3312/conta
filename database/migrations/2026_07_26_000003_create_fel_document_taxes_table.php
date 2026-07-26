<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fel_document_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fel_document_id');
            $table->foreignId('fel_document_item_id')->nullable();
            $table->string('tax_name');
            $table->string('tax_name_normalized', 100)->index();
            $table->string('tax_code')->nullable();
            $table->string('taxable_unit_code')->nullable();
            $table->decimal('taxable_amount', 18, 6)->default(0);
            $table->decimal('tax_amount', 18, 6)->default(0);
            $table->string('source_level', 30);
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->index('fel_document_id', 'fel_taxes_document_idx');
            $table->index('fel_document_item_id', 'fel_taxes_item_idx');
            $table->foreign('fel_document_id', 'fel_taxes_document_fk')
                ->references('id')->on('fel_documents')->cascadeOnDelete();
            $table->foreign('fel_document_item_id', 'fel_taxes_item_fk')
                ->references('id')->on('fel_document_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fel_document_taxes');
    }
};
