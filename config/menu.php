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
        ],
    ],
];
