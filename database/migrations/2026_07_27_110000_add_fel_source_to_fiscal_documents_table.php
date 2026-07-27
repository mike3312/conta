<?php

use App\Enums\FiscalDocumentSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table) {
            $table->foreignId('fel_document_id')->nullable()->after('company_id');
            $table->string('source', 20)->default(FiscalDocumentSource::MANUAL->value)->after('fel_document_id');
            $table->string('source_reference', 255)->nullable()->after('source');
            $table->json('source_metadata')->nullable()->after('source_reference');

            $table->unique('fel_document_id', 'fiscal_documents_fel_document_unique');
            $table->foreign('fel_document_id', 'fiscal_documents_fel_document_fk')
                ->references('id')->on('fel_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table) {
            $table->dropForeign('fiscal_documents_fel_document_fk');
            $table->dropUnique('fiscal_documents_fel_document_unique');
            $table->dropColumn(['fel_document_id', 'source', 'source_reference', 'source_metadata']);
        });
    }
};
