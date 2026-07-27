<?php

use App\Enums\FiscalDocumentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('accounting_period_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('direction', 20);
            $table->string('document_type', 30);
            $table->string('tax_category', 30);
            $table->date('document_date');
            $table->date('emission_date')->nullable();
            $table->date('received_date')->nullable();
            $table->string('series', 50)->nullable();
            $table->string('document_number', 100)->nullable();
            $table->string('authorization_uuid', 255)->nullable();
            $table->string('third_party_tax_id', 30)->nullable();
            $table->string('third_party_name');
            $table->text('third_party_address')->nullable();
            $table->string('currency', 10)->default('GTQ');
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->decimal('exempt_amount', 15, 2)->default(0);
            $table->decimal('non_taxable_amount', 15, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('other_taxes_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->boolean('grants_tax_credit')->default(false);
            $table->boolean('is_small_taxpayer')->default(false);
            $table->string('status', 20)->default(FiscalDocumentStatus::ACTIVE->value);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'document_date']);
            $table->index(['company_id', 'direction']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'document_number']);
            $table->index(['company_id', 'third_party_tax_id']);
            $table->index('accounting_period_id');
            $table->index('journal_entry_id');
            $table->unique(['company_id', 'authorization_uuid'], 'fiscal_documents_company_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_documents');
    }
};
