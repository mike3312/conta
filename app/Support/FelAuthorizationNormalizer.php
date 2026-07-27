<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class FelAuthorizationNormalizer
{
    public function normalize(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));
        $value = str_replace(['{', '}', ' ', "\t", "\r", "\n"], '', $value);
        if ($value === '') {
            return null;
        }

        $compact = str_replace('-', '', $value);
        if (preg_match('/^[A-F0-9]{32}$/', $compact)) {
            return sprintf(
                '%s-%s-%s-%s-%s',
                substr($compact, 0, 8),
                substr($compact, 8, 4),
                substr($compact, 12, 4),
                substr($compact, 16, 4),
                substr($compact, 20),
            );
        }

        return preg_match('/^[A-Z0-9][A-Z0-9-]{7,99}$/', $value) ? $value : null;
    }

    public function compact(?string $value): ?string
    {
        $normalized = $this->normalize($value);

        return $normalized ? str_replace('-', '', $normalized) : null;
    }

    public function whereMatches(Builder $query, string $column, string $value): Builder
    {
        $compact = $this->compact($value);

        return $query->whereRaw(
            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER({$column}), '-', ''), ' ', ''), '{', ''), '}', ''), CHAR(9), '') = ?",
            [$compact],
        );
    }
}
