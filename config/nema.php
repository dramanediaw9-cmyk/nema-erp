<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Product Media Disk
    |--------------------------------------------------------------------------
    |
    | Ce disque est utilise pour stocker et servir les images produit. En
    | local, on garde "public". En production cloud, on peut pointer vers
    | "s3" ou un disque compatible objet.
    |
    */

    'product_media_disk' => env('PRODUCT_MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Document Attachment Disk
    |--------------------------------------------------------------------------
    |
    | Ce disque est utilise pour les pieces jointes de collaboration
    | documentaire. Il peut rester en "public" en local et basculer vers
    | "s3" ou equivalent en production.
    |
    */

    'document_attachment_disk' => env('DOCUMENT_ATTACHMENT_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Demo Credential Hint
    |--------------------------------------------------------------------------
    |
    | Par defaut, l ecran de connexion ne doit pas exposer de compte demo.
    | On peut le reactiver explicitement pour un environnement de demo guidee.
    |
    */

    'expose_demo_credentials' => (bool) env('NEMA_EXPOSE_DEMO_CREDENTIALS', false),

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | Ces options pilotent les en-tetes HTTP de durcissement ajoutes en
    | middleware global.
    |
    */

    'security_headers' => [
        'enabled' => (bool) env('SECURITY_HEADERS_ENABLED', true),
        'hsts_max_age' => (int) env('SECURITY_HEADERS_HSTS_MAX_AGE', 31536000),
    ],
];
