<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fel_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable();
            $table->foreignId('company_id');
            $table->foreignId('fel_import_batch_id')->nullable();
            $table->string('authorization_uuid', 100);
            $table->string('authorization_uuid_original', 150)->nullable();
            $table->string('series', 100)->nullable();
            $table->string('document_number', 100)->nullable();
            $table->string('dte_type', 30);
            $table->string('currency', 10)->default('GTQ');
            $table->string('source_type', 20)->index();
            $table->string('data_level', 20);
            $table->string('operation_type', 20)->index();
            $table->string('classification', 30)->index();
            $table->string('status', 20)->index();
            $table->string('fiscal_status', 20)->index();
            $table->dateTime('issued_at')->index();
            $table->dateTime('voided_at')->nullable();
            $table->string('issuer_tax_id', 50)->nullable()->index();
            $table->string('issuer_name')->nullable();
            $table->string('issuer_commercial_name')->nullable();
            $table->string('issuer_tax_regime')->nullable();
            $table->string('issuer_establishment_code', 100)->nullable();
            $table->text('issuer_address')->nullable();
            $table->string('issuer_municipality')->nullable();
            $table->string('issuer_department')->nullable();
            $table->string('issuer_country')->nullable();
            $table->string('receiver_tax_id', 50)->nullable()->index();
            $table->string('receiver_name')->nullable();
            $table->text('receiver_address')->nullable();
            $table->string('certifier_tax_id', 50)->nullable();
            $table->string('certifier_name')->nullable();
            $table->dateTime('certified_at')->nullable();
            foreach (['subtotal', 'taxable_total', 'grand_total'] as $column) {
                $table->decimal($column, 18, 6)->nullable();
            }
            foreach (['discount_total', 'tax_total', 'other_tax_total'] as $column) {
                $table->decimal($column, 18, 6)->default(0);
            }
            $table->boolean('requires_tax_review')->default(false)->index();
            $table->boolean('requires_accounting_review')->default(true);
            $table->string('xml_version', 30)->nullable();
            $table->string('fel_version', 30)->nullable();
            $table->unsignedSmallInteger('signature_count')->default(0);
            $table->json('phrases')->nullable();
            $table->json('complements')->nullable();
            $table->json('metadata')->nullable();
            $table->string('xml_path')->nullable();
            $table->char('xml_hash', 64)->nullable();
            $table->foreignId('imported_by');
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('observation')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('journal_entry_id')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'authorization_uuid'], 'fel_documents_company_uuid_uq');
            $table->index('tenant_id', 'fel_documents_tenant_idx');
            $table->index('company_id', 'fel_documents_company_idx');
            $table->index(['company_id', 'issued_at'], 'fel_documents_company_issued_idx');
            $table->index('fel_import_batch_id', 'fel_documents_batch_idx');
            $table->index('imported_by', 'fel_documents_imported_by_idx');
            $table->index('reviewed_by', 'fel_documents_reviewed_by_idx');
            $table->index('journal_entry_id', 'fel_documents_journal_entry_idx');
            $table->index('dte_type', 'fel_documents_dte_type_idx');

            $table->foreign('tenant_id', 'fel_documents_tenant_fk')
                ->references('id')->on('tenants')->nullOnDelete();
            $table->foreign('company_id', 'fel_documents_company_fk')
                ->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('fel_import_batch_id', 'fel_documents_batch_fk')
                ->references('id')->on('fel_import_batches')->nullOnDelete();
            $table->foreign('imported_by', 'fel_documents_imported_by_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('reviewed_by', 'fel_documents_reviewed_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('journal_entry_id', 'fel_documents_journal_entry_fk')
                ->references('id')->on('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fel_documents');
    }
};
