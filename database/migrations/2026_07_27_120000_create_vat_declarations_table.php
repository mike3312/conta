<?php

use App\Enums\VatDeclarationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('accounting_period_id')->constrained()->restrictOnDelete();
            $table->string('tax_regime', 50)->default('GENERAL');
            $table->string('status', 30)->default(VatDeclarationStatus::DRAFT->value);

            foreach ([
                'sales_taxable_amount', 'sales_exempt_amount', 'sales_non_taxable_amount',
                'sales_vat_amount', 'sales_other_taxes_amount', 'purchase_taxable_amount',
                'purchase_exempt_amount', 'purchase_non_taxable_amount', 'purchase_vat_amount',
                'purchase_creditable_vat_amount', 'purchase_non_creditable_vat_amount',
                'purchase_other_taxes_amount', 'previous_credit_balance', 'vat_withholdings',
                'vat_perceptions', 'manual_debit_adjustments', 'manual_credit_adjustments',
                'net_vat_debit', 'net_vat_credit', 'vat_payable', 'credit_balance', 'amount_paid',
            ] as $column) {
                $table->decimal($column, 18, 2)->default(0);
            }

            $table->text('notes')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ready_at')->nullable();
            $table->foreignId('ready_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('filed_at')->nullable();
            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sat_form_number', 100)->nullable();
            $table->string('sat_access_number', 100)->nullable();
            $table->string('sat_payment_slip_number', 100)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'accounting_period_id'], 'vat_declarations_company_period_unique');
            $table->index(['company_id', 'status']);
            $table->index('calculated_at');
            $table->index('filed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_declarations');
    }
};
