<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Paiement automatique par mobile money (cahier §3.8 A) : prestataire agréé BCEAO. Vide = désactivé (déclaration
    // manuelle de la transaction, vérifiée par l'équipe). « simulation » : parcours de test, refusé en production.
    'paiement' => [
        'gateway' => env('PAYMENT_GATEWAY'),
    ],
    'cinetpay' => [
        'api_key' => env('CINETPAY_API_KEY'),
        'site_id' => env('CINETPAY_SITE_ID'),
        'base_url' => env('CINETPAY_BASE_URL', 'https://api-checkout.cinetpay.com'),
        'channels' => env('CINETPAY_CHANNELS', 'MOBILE_MONEY'),
    ],

    // Conversion des vidéos de témoignages (MP4 lisible partout, image d'aperçu). Absent : vidéo publiée telle quelle.
    'ffmpeg' => [
        'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
        'ffprobe' => env('FFPROBE_BINARY', 'ffprobe'),
    ],

];
