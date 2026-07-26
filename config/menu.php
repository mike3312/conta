<?php

return [
    [
        'title' => 'Resumen',
        'route' => 'dashboard',
        'icon' => 'bi-grid-1x2-fill',
        'active' => ['dashboard'],
    ],
    [
        'title' => 'Empresas',
        'route' => 'companies.index',
        'icon' => 'bi-buildings',
        'active' => ['companies.*'],
    ],
    [
        'title' => 'Contabilidad',
        'icon' => 'bi-calculator',
        'children' => [
            ['title' => 'Catálogo de cuentas', 'route' => 'accounts.index', 'icon' => 'bi-diagram-3', 'active' => ['accounts.*']],
            ['title' => 'Períodos contables', 'route' => 'accounting-periods.index', 'icon' => 'bi-calendar3', 'active' => ['accounting-periods.*']],
            ['title' => 'Pólizas contables', 'route' => 'journal-entries.index', 'icon' => 'bi-journal-text', 'active' => ['journal-entries.*']],
            ['title' => 'Libro Diario', 'route' => 'accounting.daily-book.index', 'icon' => 'bi-book', 'active' => ['accounting.daily-book.*']],
            ['title' => 'Libro Mayor', 'route' => 'accounting.general-ledger.index', 'icon' => 'bi-journals', 'active' => ['accounting.general-ledger.*']],
        ],
    ],
    [
        'title' => 'Documentos FEL',
        'icon' => 'bi-file-earmark-arrow-up',
        'children' => [
            ['title' => 'Importar documentos', 'route' => 'fel-imports.create', 'icon' => 'bi-cloud-arrow-up', 'active' => ['fel-imports.create']],
            ['title' => 'Bandeja de revisión', 'route' => 'fel-documents.index', 'icon' => 'bi-inbox', 'active' => ['fel-documents.*']],
            ['title' => 'Historial de importaciones', 'route' => 'fel-imports.index', 'icon' => 'bi-clock-history', 'active' => ['fel-imports.index', 'fel-imports.show']],
        ],
    ],
    [
        'title' => 'Reportes',
        'icon' => 'bi-bar-chart-line',
        'children' => [
            ['title' => 'Balance de comprobación', 'route' => 'accounting.trial-balance.index', 'icon' => 'bi-clipboard-data', 'active' => ['accounting.trial-balance.*']],
            ['title' => 'Estado de resultados', 'route' => 'accounting.income-statement.index', 'icon' => 'bi-graph-up-arrow', 'active' => ['accounting.income-statement.*']],
            ['title' => 'Balance general', 'route' => 'accounting.balance-sheet.index', 'icon' => 'bi-bank', 'active' => ['accounting.balance-sheet.*']],
        ],
    ],
];
