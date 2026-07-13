<?php

return [
    [
        'title' => 'Dashboard',
        'route' => 'dashboard',
        'icon' => 'bi-speedometer2',
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
            [
                'title' => 'Catálogo de cuentas',
                'route' => 'accounts.index',
                'icon' => 'bi-diagram-3',
                'active' => ['accounts.*'],
            ],
            [
                'title' => 'Períodos contables',
                'route' => 'accounting-periods.index',
                'icon' => 'bi-calendar3',
                'active' => ['accounting-periods.*'],
            ],
            [
                'title' => 'Pólizas contables',
                'route' => 'journal-entries.index',
                'icon' => 'bi-journal-text',
                'active' => ['journal-entries.*'],
            ],
            [
                'title' => 'Libro Diario',
                'route' => 'accounting.daily-book.index',
                'icon' => 'bi-book',
                'active' => ['accounting.daily-book.*'],
            ],
            [
                'title' => 'Libro Mayor',
                'route' => 'accounting.general-ledger.index',
                'icon' => 'bi-journals',
                'active' => ['accounting.general-ledger.*'],
            ],
            [
                'title' => 'Balance de Comprobación',
                'route' => 'accounting.trial-balance.index',
                'icon' => 'bi-clipboard-data',
                'active' => ['accounting.trial-balance.*'],
            ],
            [
                'title' => 'Estado de Resultados',
                'route' => 'accounting.income-statement.index',
                'icon' => 'bi-graph-up-arrow',
                'active' => ['accounting.income-statement.*'],
            ],
            [
                'title' => 'Balance General',
                'route' => 'accounting.balance-sheet.index',
                'icon' => 'bi-bank',
                'active' => ['accounting.balance-sheet.*'],
            ],
        ],
    ],
];
