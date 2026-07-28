<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_declaration_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vat_declaration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_document_id')->constrained()->restrictOnDelete();
            $table->string('direction', 20);
            $table->string('document_type', 30);
            $table->string('review_status', 20);
            $table->string('fiscal_status', 20);
            foreach ([
                'taxable_amount', 'exempt_amount', 'non_taxable_amount', 'vat_amount',
                'creditable_vat_amount', 'non_creditable_vat_amount', 'other_taxes_amount', 'total_amount',
            ] as $column) {
                $table->decimal($column, 18, 2)->default(0);
            }
            $table->smallInteger('effect')->default(1);
            $table->boolean('included')->default(false);
            $table->string('exclusion_reason')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['vat_declaration_id', 'fiscal_document_id'], 'vat_declaration_document_unique');
            $table->index(['vat_declaration_id', 'included']);
            $table->index(['vat_declaration_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_declaration_documents');
    }
};
