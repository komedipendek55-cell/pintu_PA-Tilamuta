<?php
return [
    'app_name'      => 'PINTU',
    'app_full_name' => 'Portal Informasi Perkara Terpadu',
    'instansi'      => 'Pengadilan Agama Tilamuta',
    'base_url'      => '/pintu/',
    'timezone'      => 'Asia/Makassar',
    'session_name'  => 'PINTU_SESS',
    'session_lifetime' => 7200,
    'upload' => [
        'icon_dir'     => __DIR__ . '/../uploads/icons/',
        'icon_url'     => 'uploads/icons/',
        'max_size'     => 200 * 1024,
        'allowed_mime' => ['image/png','image/jpeg','image/webp','image/svg+xml'],
        'allowed_ext'  => ['png','jpg','jpeg','webp','svg'],
    ],
    'logo_upload' => [
        'dir'          => __DIR__ . '/../uploads/logo/',
        'url'          => 'uploads/logo/',
        'max_size'     => 500 * 1024,
        'allowed_mime' => ['image/png','image/jpeg','image/webp'],
        'allowed_ext'  => ['png','jpg','jpeg','webp'],
    ],
	
	'popup_upload' => [
    'dir'          => __DIR__ . '/../uploads/popup/',
    'url'          => 'uploads/popup/',
    'max_size'     => 200 * 1024,   // 200 KB sesuai permintaan
    'allowed_mime' => ['image/png','image/jpeg','image/webp'],
    'allowed_ext'  => ['png','jpg','jpeg','webp'],
	],
	
	'sipp_sync' => [
    // URL endpoint api_sync.php di website SIPP (PA Tilamuta)
    'api_url'     => 'https://pa-tilamuta.go.id/api-patilamuta1/api_sync.php',
    // Token rahasia — HARUS sama dengan $secret_token di api_sync.php
    'api_token'   => 'P471lamu74ye$_TOKEN_RAHASIA_ANDA_YANG_SANGAT_PANJANG',
    // Jumlah data per halaman (samakan dengan limit di api_sync.php)
    'batch_limit' => 100,
    // Timeout request ke API (detik)
    'timeout'     => 20,
	],
	
	'auto_sync_daily' => [
    'enabled' => true,   // set false untuk mematikan auto-sync publik
	],
	
    'debug' => true,
	
];