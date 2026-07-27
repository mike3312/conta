<?php

namespace App\Services\Fiscal;

class FiscalSyncResult
{
    public function __construct(
        public readonly string $status,
        public readonly string $action,
        public readonly ?int $fiscalDocumentId = null,
        public readonly array $warnings = [],
        public readonly array $errors = [],
    ) {}

    public function created(): bool
    {
        return $this->action === 'CREATED';
    }

    public function updated(): bool
    {
        return $this->action === 'UPDATED';
    }

    public function skipped(): bool
    {
        return in_array($this->action, ['SKIPPED', 'OBSERVED', 'CONFLICT'], true);
    }

    public function observed(): bool
    {
        return $this->action === 'OBSERVED';
    }

    public function conflict(): bool
    {
        return $this->action === 'CONFLICT';
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'action' => $this->action,
            'fiscal_document_id' => $this->fiscalDocumentId,
            'created' => $this->created(),
            'updated' => $this->updated(),
            'skipped' => $this->skipped(),
            'observed' => $this->observed(),
            'conflict' => $this->conflict(),
            'warnings' => $this->warnings,
            'errors' => $this->errors,
        ];
    }
}
