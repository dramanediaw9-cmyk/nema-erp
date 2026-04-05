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
];
