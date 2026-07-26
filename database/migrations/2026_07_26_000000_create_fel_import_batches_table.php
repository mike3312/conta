<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fel_import_batches')) {
            $this->repairTableLeftByFailedMigration();

            return;
        }

        Schema::create('fel_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable();
            $table->foreignId('company_id');
            $table->string('source_type', 20);
            $table->string('original_filename');
            $table->string('stored_path')->nullable();
            $table->string('original_mime_type')->nullable();
            $table->unsignedBigInteger('original_size')->nullable();
            $table->unsignedInteger('total_files')->default(0);
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('successful_records')->default(0);
            $table->unsignedInteger('enriched_records')->default(0);
            $table->unsignedInteger('duplicate_records')->default(0);
            $table->unsignedInteger('observed_records')->default(0);
            $table->unsignedInteger('failed_records')->default(0);
            $table->unsignedInteger('fuel_records')->default(0);
            $table->unsignedInteger('voided_records')->default(0);
            $table->string('status', 30);
            $table->foreignId('imported_by');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index('tenant_id', 'fel_batches_tenant_idx');
            $table->index('company_id', 'fel_batches_company_idx');
            $table->index(['company_id', 'created_at'], 'fel_batches_company_created_idx');
            $table->index('status', 'fel_batches_status_idx');
            $table->index('imported_by', 'fel_batches_imported_by_idx');
            $table->index('created_at', 'fel_batches_created_at_idx');

            $table->foreign('tenant_id', 'fel_batches_tenant_fk')
                ->references('id')->on('tenants')->nullOnDelete();
            $table->foreign('company_id', 'fel_batches_company_fk')
                ->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('imported_by', 'fel_batches_imported_by_fk')
                ->references('id')->on('users')->restrictOnDelete();
        });
    }

    /**
     * MySQL may keep a CREATE TABLE after a later ALTER TABLE fails. The
     * original migration chained index() after constrained(), which named the
     * generated foreign key "1". Repair that partial table without dropping it.
     */
    private function repairTableLeftByFailedMigration(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException(
                'fel_import_batches already exists but its migration is pending; automatic repair is only supported for MySQL.'
            );
        }

        if ($this->foreignKeyExists('1')) {
            Schema::table('fel_import_batches', function (Blueprint $table) {
                $table->dropForeign('1');
            });
        }

        $this->ensureIndex('tenant_id', 'fel_batches_tenant_idx');
        $this->ensureIndex('company_id', 'fel_batches_company_idx');
        $this->ensureCompositeIndex(['company_id', 'created_at'], 'fel_batches_company_created_idx');
        $this->ensureIndex('status', 'fel_batches_status_idx');
        $this->ensureIndex('imported_by', 'fel_batches_imported_by_idx');
        $this->ensureIndex('created_at', 'fel_batches_created_at_idx');

        if (Schema::hasIndex('fel_import_batches', '1')) {
            Schema::table('fel_import_batches', function (Blueprint $table) {
                $table->dropIndex('1');
            });
        }

        $this->ensureForeignKey('tenant_id', 'fel_batches_tenant_fk', 'tenants', 'null');
        $this->ensureForeignKey('company_id', 'fel_batches_company_fk', 'companies', 'cascade');
        $this->ensureForeignKey('imported_by', 'fel_batches_imported_by_fk', 'users', 'restrict');
    }

    private function ensureIndex(string $column, string $name): void
    {
        if (! Schema::hasIndex('fel_import_batches', $name)) {
            Schema::table('fel_import_batches', function (Blueprint $table) use ($column, $name) {
                $table->index($column, $name);
            });
        }
    }

    private function ensureCompositeIndex(array $columns, string $name): void
    {
        if (! Schema::hasIndex('fel_import_batches', $name)) {
            Schema::table('fel_import_batches', function (Blueprint $table) use ($columns, $name) {
                $table->index($columns, $name);
            });
        }
    }

    private function ensureForeignKey(string $column, string $name, string $table, string $onDelete): void
    {
        if ($this->foreignKeyForColumnExists($column)) {
            return;
        }

        Schema::table('fel_import_batches', function (Blueprint $blueprint) use ($column, $name, $table, $onDelete) {
            $foreign = $blueprint->foreign($column, $name)->references('id')->on($table);

            match ($onDelete) {
                'cascade' => $foreign->cascadeOnDelete(),
                'null' => $foreign->nullOnDelete(),
                default => $foreign->restrictOnDelete(),
            };
        });
    }

    private function foreignKeyExists(string $name): bool
    {
        return DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->whereRaw('CONSTRAINT_SCHEMA = SCHEMA()')
            ->where('TABLE_NAME', 'fel_import_batches')
            ->where('CONSTRAINT_NAME', $name)
            ->exists();
    }

    private function foreignKeyForColumnExists(string $column): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->whereRaw('TABLE_SCHEMA = SCHEMA()')
            ->where('TABLE_NAME', 'fel_import_batches')
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }

    public function down(): void
    {
        Schema::dropIfExists('fel_import_batches');
    }
};
