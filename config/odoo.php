<?php

return [
    'timeout' => (int) env('ODOO_RPC_TIMEOUT', 45),
    'connect_timeout' => (int) env('ODOO_RPC_CONNECT_TIMEOUT', 10),
    'batch_size' => (int) env('ODOO_IMPORT_BATCH_SIZE', 250),
    'max_batch_size' => (int) env('ODOO_IMPORT_MAX_BATCH_SIZE', 1000),
    'max_image_bytes' => (int) env('ODOO_IMPORT_MAX_IMAGE_BYTES', 8 * 1024 * 1024),
    'queue' => env('ODOO_IMPORT_QUEUE', 'imports'),
    'image_disk' => env('ODOO_IMAGE_DISK', env('PRODUCT_MEDIA_DISK', 'public')),
];
