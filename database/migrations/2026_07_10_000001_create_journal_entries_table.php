<?php

use App\Enums\JournalEntryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('accounting_period_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('number')->nullable();
            $table->date('entry_date');
            $table->string('description');
            $table->string('reference')->nullable();
            $table->string('status', 20)->default(JournalEntryStatus::DRAFT->value);

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('posted_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('posted_at')->nullable();

            $table->foreignId('voided_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['company_id', 'accounting_period_id', 'number'],
                'journal_entries_company_period_number_unique'
            );
            $table->index(['company_id', 'entry_date']);
            $table->index(['company_id', 'status']);
            $table->index(['accounting_period_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
