<?php

return [
    'disk' => env('FEL_DISK', 'local'),
    'max_files' => 50,
    'max_xml_bytes' => 5 * 1024 * 1024,
    'max_file_bytes' => 20 * 1024 * 1024,
    'max_batch_bytes' => 50 * 1024 * 1024,
    'max_documents' => 1000,
    'max_zip_entries' => 2000,
    'max_zip_uncompressed_bytes' => 100 * 1024 * 1024,
    'header_scan_rows' => 30,
];
