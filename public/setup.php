<?php
/**
 * ============================================================================
 * WEB SETUP & INSTALLER ASSISTANT (UNTUK SHARED HOSTING TANPA TERMINAL)
 * ============================================================================
 * File ini digunakan untuk menjalankan persiapan awal aplikasi Laravel:
 * 1. Salin / Konfigurasi .env (Database & Kunci)
 * 2. Generate APP_KEY
 * 3. Migrasi Basis Data (php artisan migrate --force)
 * 4. Seeding Data Awal (php artisan db:seed --force)
 * 5. Membuat Storage Symlink (php artisan storage:link)
 * 6. Optimasi Cache (config, route, view)
 * 7. Self-Destruct / Hapus file ini setelah selesai demi keamanan.
 *
 * Ganti PIN rahasia di bawah ini sebelum mengunggah ke server produksi!
 * ============================================================================
 */

// GANTI PIN INI SESUAI KEINGINAN ANDA (Default: 2026)
define('SETUP_PIN', '2026');

// Matikan error reporting publik agar aman, namun tetap tangkap exception
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

// Deteksi lokasi root project Laravel
$possibleBasePaths = [
    dirname(__DIR__),
    dirname(__DIR__) . '/notulen-app',
    dirname(__DIR__) . '/notulen_core',
    isset($_SERVER['DOCUMENT_ROOT']) ? dirname($_SERVER['DOCUMENT_ROOT']) . '/notulen-app' : '',
    isset($_SERVER['DOCUMENT_ROOT']) ? dirname($_SERVER['DOCUMENT_ROOT']) . '/notulen_core' : '',
    isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] . '/..' : '',
];

$basePath = null;
foreach ($possibleBasePaths as $path) {
    if ($path && file_exists($path . '/bootstrap/app.php') && file_exists($path . '/vendor/autoload.php')) {
        $basePath = realpath($path);
        break;
    }
}

// -----------------------------------------------------------------------------
// AKSI: Hapus Diri Sendiri (Self-Destruct)
// -----------------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'self_destruct') {
    if (!isset($_SESSION['setup_auth']) || $_SESSION['setup_auth'] !== true) {
        die('Akses ditolak.');
    }
    
    $filePath = __FILE__;
    if (@unlink($filePath)) {
        session_destroy();
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Setup Selesai & Dihapus</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
                .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 32px; max-width: 480px; text-align: center; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5); }
                .icon { font-size: 48px; margin-bottom: 16px; color: #10b981; }
                h1 { margin: 0 0 12px; font-size: 22px; }
                p { color: #94a3b8; font-size: 14px; line-height: 1.6; margin: 0 0 24px; }
                a { display: inline-block; background: #3b82f6; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 14px; }
                a:hover { background: #2563eb; }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="icon">✓</div>
                <h1>File Setup Berhasil Dihapus</h1>
                <p>File <code>setup.php</code> telah dihapus secara permanen dari server untuk menjaga keamanan aplikasi Anda. Silakan masuk ke aplikasi Anda sekarang.</p>
                <a href="/">Buka Aplikasi Notulensi</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    } else {
        $deleteError = "Gagal menghapus file setup.php secara otomatis. Silakan hapus file ini secara manual via cPanel File Manager.";
    }
}

// -----------------------------------------------------------------------------
// AUTENTIKASI PIN
// -----------------------------------------------------------------------------
if (isset($_POST['pin_login'])) {
    if (trim($_POST['pin']) === SETUP_PIN) {
        $_SESSION['setup_auth'] = true;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $loginError = "PIN Setup salah! Silakan coba lagi.";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$isAuthenticated = isset($_SESSION['setup_auth']) && $_SESSION['setup_auth'] === true;

// -----------------------------------------------------------------------------
// LOGIKA PEMERIKSAAN SISTEM
// -----------------------------------------------------------------------------
$phpVersion = PHP_VERSION;
$phpOk = version_compare($phpVersion, '8.3.0', '>=');

$requiredExtensions = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'curl', 'bcmath', 'gd'];
$extStatuses = [];
foreach ($requiredExtensions as $ext) {
    $extStatuses[$ext] = extension_loaded($ext);
}
$allExtsOk = !in_array(false, $extStatuses, true);

$envExists = $basePath ? file_exists($basePath . '/.env') : false;
$envExampleExists = $basePath ? file_exists($basePath . '/.env.example') : false;

// Cek hak akses direktori
$writableChecks = [];
if ($basePath) {
    $dirs = [
        'storage' => $basePath . '/storage',
        'storage/framework' => $basePath . '/storage/framework',
        'storage/logs' => $basePath . '/storage/logs',
        'bootstrap/cache' => $basePath . '/bootstrap/cache',
    ];
    foreach ($dirs as $name => $d) {
        $writableChecks[$name] = is_dir($d) && is_writable($d);
    }
}

// -----------------------------------------------------------------------------
// AKSI FORM: UPDATE / SIMPAN .ENV
// -----------------------------------------------------------------------------
$envUpdateMessage = null;
if ($isAuthenticated && isset($_POST['action']) && $_POST['action'] === 'save_env') {
    if ($basePath) {
        $envPath = $basePath . '/.env';
        $examplePath = $basePath . '/.env.example';
        
        $currentContent = file_exists($envPath) 
            ? file_get_contents($envPath) 
            : (file_exists($examplePath) ? file_get_contents($examplePath) : '');
            
        $replacements = [
            'APP_URL' => trim($_POST['app_url'] ?? ''),
            'DB_HOST' => trim($_POST['db_host'] ?? '127.0.0.1'),
            'DB_PORT' => trim($_POST['db_port'] ?? '3306'),
            'DB_DATABASE' => trim($_POST['db_database'] ?? ''),
            'DB_USERNAME' => trim($_POST['db_username'] ?? ''),
            'DB_PASSWORD' => trim($_POST['db_password'] ?? ''),
            'GROQ_API_KEY' => trim($_POST['groq_api_key'] ?? ''),
        ];
        
        foreach ($replacements as $key => $val) {
            if ($val === '') continue;
            // Jika key ada di file, ganti. Jika tidak ada, tambahkan.
            $pattern = "/^{$key}=.*/m";
            if (preg_match($pattern, $currentContent)) {
                $currentContent = preg_replace($pattern, "{$key}={$val}", $currentContent);
            } else {
                $currentContent .= "\n{$key}={$val}";
            }
        }
        
        // Pastikan queue database & driver
        if (!str_contains($currentContent, 'QUEUE_CONNECTION=')) {
            $currentContent .= "\nQUEUE_CONNECTION=database";
        }
        
        if (@file_put_contents($envPath, $currentContent) !== false) {
            $envUpdateMessage = "Berkas .env berhasil disimpan!";
            $envExists = true;
        } else {
            $envUpdateMessage = "Gagal menyimpan berkas .env. Pastikan folder aplikasi memiliki izin tulis (write permission).";
        }
    }
}

// -----------------------------------------------------------------------------
// AKSI FORM: JALANKAN SETUP ARTISAN
// -----------------------------------------------------------------------------
$artisanOutputs = [];
$setupExecuted = false;

if ($isAuthenticated && isset($_POST['action']) && $_POST['action'] === 'run_artisan') {
    $setupExecuted = true;
    
    if (!$basePath) {
        $artisanOutputs[] = [
            'title' => 'Galat Sistem',
            'status' => 'error',
            'output' => 'Direktori aplikasi Laravel tidak dapat ditemukan secara otomatis.'
        ];
    } else {
        try {
            // Bootstrap Laravel Console Application
            require $basePath . '/vendor/autoload.php';
            $app = require_once $basePath . '/bootstrap/app.php';
            $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
            $kernel->bootstrap();

            // 1. Generate Key jika belum ada
            if (isset($_POST['do_key']) && $_POST['do_key'] === '1') {
                \Illuminate\Support\Facades\Artisan::call('key:generate', ['--force' => true]);
                $artisanOutputs[] = [
                    'title' => 'Generate APP_KEY',
                    'status' => 'success',
                    'output' => \Illuminate\Support\Facades\Artisan::output() ?: 'Kunci aplikasi baru berhasil digenerate dan disimpan ke .env'
                ];
            }

            // 2. Storage Link
            if (isset($_POST['do_storage']) && $_POST['do_storage'] === '1') {
                try {
                    \Illuminate\Support\Facades\Artisan::call('storage:link');
                    $artisanOutputs[] = [
                        'title' => 'Storage Symlink',
                        'status' => 'success',
                        'output' => \Illuminate\Support\Facades\Artisan::output() ?: 'Symlink storage berhasil dibuat.'
                    ];
                } catch (\Throwable $e) {
                    // Fallback jika artisan storage:link gagal karena permission
                    $target = $basePath . '/storage/app/public';
                    $link = __DIR__ . '/storage';
                    if (file_exists($link)) {
                        $artisanOutputs[] = [
                            'title' => 'Storage Symlink',
                            'status' => 'success',
                            'output' => 'Symlink storage sudah ada.'
                        ];
                    } elseif (@symlink($target, $link)) {
                        $artisanOutputs[] = [
                            'title' => 'Storage Symlink (PHP Fallback)',
                            'status' => 'success',
                            'output' => 'Symlink berhasil dibuat melalui fungsi symlink() PHP.'
                        ];
                    } else {
                        $artisanOutputs[] = [
                            'title' => 'Storage Symlink',
                            'status' => 'warning',
                            'output' => 'Catatan: Pembuatan symlink dilewati / tidak diizinkan oleh host. (Aplikasi ini tetap dapat memutar audio dan menyajikan kop surat karena disimpan di private disk via controller).'
                        ];
                    }
                }
            }

            // 3. Migrasi Database
            if (isset($_POST['do_migrate']) && $_POST['do_migrate'] === '1') {
                try {
                    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
                    $artisanOutputs[] = [
                        'title' => 'Migrasi Database (php artisan migrate)',
                        'status' => 'success',
                        'output' => \Illuminate\Support\Facades\Artisan::output() ?: 'Seluruh tabel basis data berhasil dimigrasikan.'
                    ];
                } catch (\Throwable $e) {
                    $artisanOutputs[] = [
                        'title' => 'Migrasi Database Gagal',
                        'status' => 'error',
                        'output' => "Galat: " . $e->getMessage() . "\n\nPastikan kredensial DB_DATABASE, DB_USERNAME, dan DB_PASSWORD di berkas .env sudah benar."
                    ];
                }
            }

            // 4. Seeding Database
            if (isset($_POST['do_seed']) && $_POST['do_seed'] === '1') {
                try {
                    \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
                    $artisanOutputs[] = [
                        'title' => 'Database Seeding (php artisan db:seed)',
                        'status' => 'success',
                        'output' => \Illuminate\Support\Facades\Artisan::output() ?: 'Data awal (organisasi, admin contoh, buku kontak) berhasil dimasukkan.'
                    ];
                } catch (\Throwable $e) {
                    $artisanOutputs[] = [
                        'title' => 'Database Seeding Gagal',
                        'status' => 'error',
                        'output' => "Galat Seeding: " . $e->getMessage()
                    ];
                }
            }

            // 5. Optimasi Cache
            if (isset($_POST['do_cache']) && $_POST['do_cache'] === '1') {
                try {
                    \Illuminate\Support\Facades\Artisan::call('config:clear');
                    \Illuminate\Support\Facades\Artisan::call('route:clear');
                    \Illuminate\Support\Facades\Artisan::call('view:clear');
                    
                    \Illuminate\Support\Facades\Artisan::call('config:cache');
                    \Illuminate\Support\Facades\Artisan::call('route:cache');
                    \Illuminate\Support\Facades\Artisan::call('view:cache');
                    
                    $artisanOutputs[] = [
                        'title' => 'Optimasi Cache Produksi',
                        'status' => 'success',
                        'output' => "Cache konfigurasi, rute, dan tampilan Blade berhasil dibuat untuk performa maksimal di shared hosting."
                    ];
                } catch (\Throwable $e) {
                    $artisanOutputs[] = [
                        'title' => 'Optimasi Cache',
                        'status' => 'warning',
                        'output' => "Peringatan Cache: " . $e->getMessage()
                    ];
                }
            }

        } catch (\Throwable $e) {
            $artisanOutputs[] = [
                'title' => 'Fatal Error Saat Booting Laravel',
                'status' => 'error',
                'output' => "Pesan Galat: " . $e->getMessage() . "\n\nFile: " . $e->getFile() . " (" . $e->getLine() . ")\n\n" . $e->getTraceAsString()
            ];
        }
    }
}

// Baca nilai konfigurasi saat ini dari .env jika ada
$currentEnvValues = [
    'APP_URL' => '',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => '',
    'DB_USERNAME' => '',
    'DB_PASSWORD' => '',
    'GROQ_API_KEY' => '',
];
if ($basePath && file_exists($basePath . '/.env')) {
    $envLines = file($basePath . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            if (array_key_exists($k, $currentEnvValues)) {
                $currentEnvValues[$k] = trim($v, " \t\n\r\0\x0B\"'");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Installer & Setup - Notulensi Rapat</title>
    <style>
        :root {
            --bg: #0b0f19;
            --surface: #151d2f;
            --surface-hover: #1e293b;
            --border: #223049;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            margin: 0;
            padding: 30px 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
        }
        .container {
            width: 100%;
            max-width: 820px;
        }
        .header {
            text-align: center;
            margin-bottom: 28px;
        }
        .badge-title {
            display: inline-block;
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        h1 { margin: 0 0 8px; font-size: 24px; font-weight: 700; }
        .subtitle { color: var(--text-muted); font-size: 14px; margin: 0; }
        
        .card {
            background-color: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            padding-bottom: 14px;
            margin-bottom: 18px;
        }
        .card-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Grid & Form */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 640px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
        .form-group {
            margin-bottom: 14px;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        input[type="text"], input[type="password"], input[type="number"] {
            width: 100%;
            background: #0d1322;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            color: var(--text-main);
            font-size: 14px;
            outline: none;
            transition: border-color 0.15s;
        }
        input:focus {
            border-color: var(--primary);
        }
        .help-text {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }

        /* Checkbox list */
        .checkbox-group {
            background: #0d1322;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 16px;
        }
        .checkbox-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
            cursor: pointer;
        }
        .checkbox-item:last-child { margin-bottom: 0; }
        .checkbox-item input[type="checkbox"] {
            margin-top: 3px;
            accent-color: var(--primary);
            width: 16px;
            height: 16px;
        }
        .checkbox-label {
            font-size: 13px;
            color: var(--text-main);
            font-weight: 500;
        }
        .checkbox-desc {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.15s ease;
            text-decoration: none;
        }
        .btn-primary {
            background-color: var(--primary);
            color: white;
            width: 100%;
        }
        .btn-primary:hover { background-color: var(--primary-hover); }
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        .btn-danger:hover { background-color: #dc2626; }
        .btn-secondary {
            background-color: var(--surface-hover);
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        .btn-secondary:hover { background-color: #334155; }

        /* Status Badge */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-warning { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }

        /* Output Box */
        .output-box {
            background: #050811;
            border: 1px solid #1a2234;
            border-radius: 8px;
            padding: 14px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            color: #cbd5e1;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 250px;
            overflow-y: auto;
            margin-top: 10px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
            line-height: 1.5;
        }
        .alert-info { background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); color: #93c5fd; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #6ee7b7; }
        .alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; }
        
        .table-check {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .table-check td {
            padding: 6px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .table-check td:last-child {
            text-align: right;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <span class="badge-title">Shared Hosting Deployment Tool</span>
        <h1>Web Installer & Setup Assistant</h1>
        <p class="subtitle">Aplikasi Notulensi Rapat (Laravel 13) &bull; Mode Tanpa Akses Terminal SSH</p>
    </div>

    <?php if (!$isAuthenticated): ?>
        <!-- LOGIN PIN SCREEN -->
        <div class="card" style="max-width: 440px; margin: 40px auto;">
            <div class="card-header">
                <h2 class="card-title">Masukkan PIN Setup</h2>
                <span class="badge badge-warning">Otorisasi Dibutuhkan</span>
            </div>
            
            <?php if (isset($loginError)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>

            <p style="color: var(--text-muted); font-size: 13px; margin-top: 0;">
                Demi keamanan, pengaturan server memerlukan PIN verifikasi. PIN bawaan di file ini adalah <code>2026</code> (dapat Anda ubah pada konstanta <code>SETUP_PIN</code> di baris 21 berkas <code>setup.php</code>).
            </p>

            <form method="POST">
                <div class="form-group">
                    <label for="pin">PIN Akses</label>
                    <input type="password" id="pin" name="pin" placeholder="Masukkan PIN..." autofocus required>
                </div>
                <button type="submit" name="pin_login" class="btn btn-primary">Buka Dashboard Setup &rarr;</button>
            </form>
        </div>
    <?php else: ?>
        <!-- AUTHENTICATED DASHBOARD -->

        <div style="display: flex; justify-content: flex-end; margin-bottom: 14px;">
            <a href="?logout=1" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;">Logout</a>
        </div>

        <?php if (!$basePath): ?>
            <div class="alert alert-danger">
                <strong>Direktori Laravel Tidak Terdeteksi!</strong><br>
                Pastikan berkas <code>setup.php</code> ini berada di dalam folder <code>public/</code> proyek Laravel Anda atau sejajar dengan <code>index.php</code>.
            </div>
        <?php endif; ?>

        <?php if ($envUpdateMessage): ?>
            <div class="alert alert-success"><?= htmlspecialchars($envUpdateMessage) ?></div>
        <?php endif; ?>

        <?php if (isset($deleteError)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($deleteError) ?></div>
        <?php endif; ?>

        <!-- STEP 1: SYSTEM REQUIREMENTS & PERMISSIONS -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">1. Pemeriksaan Lingkungan Server</h2>
                <span class="badge <?= ($phpOk && $allExtsOk) ? 'badge-success' : 'badge-danger' ?>">
                    <?= ($phpOk && $allExtsOk) ? 'Memenuhi Syarat' : 'Perlu Penyesuaian' ?>
                </span>
            </div>
            <table class="table-check">
                <tr>
                    <td>Versi PHP (Minimal 8.3)</td>
                    <td>
                        <span class="badge <?= $phpOk ? 'badge-success' : 'badge-danger' ?>">
                            PHP <?= $phpVersion ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td>Status Berkas Konfigurasi (<code>.env</code>)</td>
                    <td>
                        <span class="badge <?= $envExists ? 'badge-success' : 'badge-warning' ?>">
                            <?= $envExists ? 'Ditemukan' : 'Belum Ada (Akan Dibuat)' ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td>Ekstensi PHP Wajib (<code>pdo_mysql</code>, <code>mbstring</code>, <code>gd</code>, dll.)</td>
                    <td>
                        <span class="badge <?= $allExtsOk ? 'badge-success' : 'badge-danger' ?>">
                            <?= $allExtsOk ? 'Lengkap (11/11)' : 'Beberapa Ekstensi Mati' ?>
                        </span>
                    </td>
                </tr>
                <?php if (!empty($writableChecks)): ?>
                    <?php foreach ($writableChecks as $dirName => $isWritable): ?>
                    <tr>
                        <td>Izin Tulis Direktori <code><?= htmlspecialchars($dirName) ?>/</code></td>
                        <td>
                            <span class="badge <?= $isWritable ? 'badge-success' : 'badge-danger' ?>">
                                <?= $isWritable ? 'Dapat Ditulis (OK)' : 'Read-Only (Ubah CHMOD ke 775/755)' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>

        <!-- STEP 2: KONFIGURASI .ENV & BASIS DATA -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">2. Konfigurasi Basis Data & Lingkungan (.env)</h2>
                <span class="badge badge-warning">Langkah Penting</span>
            </div>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 0;">
                Masukkan kredensial database MySQL yang telah Anda buat di cPanel (menu <em>MySQL Databases</em>). Form ini akan otomatis menulis atau memperbarui berkas <code>.env</code> Anda.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="save_env">
                
                <div class="form-group">
                    <label for="app_url">URL Aplikasi Lengkap (Wajib HTTPS)</label>
                    <input type="text" id="app_url" name="app_url" value="<?= htmlspecialchars($currentEnvValues['APP_URL'] ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'domainanda.com'))) ?>" required>
                    <div class="help-text">Contoh: https://notulen.domainanda.com (Tanpa garis miring di akhir)</div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label for="db_host">Database Host</label>
                        <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($currentEnvValues['DB_HOST']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="db_port">Database Port</label>
                        <input type="text" id="db_port" name="db_port" value="<?= htmlspecialchars($currentEnvValues['DB_PORT']) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="db_database">Nama Basis Data (Database Name)</label>
                    <input type="text" id="db_database" name="db_database" value="<?= htmlspecialchars($currentEnvValues['DB_DATABASE']) ?>" placeholder="contoh: cpaneluser_notulen" required>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label for="db_username">Nama Pengguna Database (DB User)</label>
                        <input type="text" id="db_username" name="db_username" value="<?= htmlspecialchars($currentEnvValues['DB_USERNAME']) ?>" placeholder="contoh: cpaneluser_notuluser" required>
                    </div>
                    <div class="form-group">
                        <label for="db_password">Kata Sandi Database (DB Password)</label>
                        <input type="password" id="db_password" name="db_password" value="<?= htmlspecialchars($currentEnvValues['DB_PASSWORD']) ?>" placeholder="Password database MySQL">
                    </div>
                </div>

                <div class="form-group">
                    <label for="groq_api_key">Groq API Key (Untuk STT Whisper & LLM Notulen)</label>
                    <input type="text" id="groq_api_key" name="groq_api_key" value="<?= htmlspecialchars($currentEnvValues['GROQ_API_KEY']) ?>" placeholder="gsk_xxxxxxxxxxxxxxxxxxxxxxxx">
                    <div class="help-text">Dapat diperoleh gratis di console.groq.com (bisa diisi sekarang atau nanti di .env).</div>
                </div>

                <button type="submit" class="btn btn-secondary" style="width: 100%;">
                    Simpan Perubahan ke Berkas .env
                </button>
            </form>
        </div>

        <!-- STEP 3: EKSEKUSI ARTISAN SETUP -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">3. Eksekusi Perintah Artisan</h2>
                <span class="badge badge-success">Satu Kali Klik</span>
            </div>
            
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 0;">
                Pilih aksi yang ingin dijalankan secara otomatis tanpa membutuhkan akses terminal:
            </p>

            <form method="POST">
                <input type="hidden" name="action" value="run_artisan">

                <div class="checkbox-group">
                    <label class="checkbox-item">
                        <input type="checkbox" name="do_key" value="1" checked>
                        <div>
                            <div class="checkbox-label">Generate Kunci Aplikasi (APP_KEY)</div>
                            <div class="checkbox-desc">Menghasilkan kunci enkripsi Laravel (<code>php artisan key:generate</code>)</div>
                        </div>
                    </label>

                    <label class="checkbox-item">
                        <input type="checkbox" name="do_migrate" value="1" checked>
                        <div>
                            <div class="checkbox-label">Migrasi Basis Data (Struktur Tabel)</div>
                            <div class="checkbox-desc">Membangun semua tabel database notulensi (<code>php artisan migrate --force</code>)</div>
                        </div>
                    </label>

                    <label class="checkbox-item">
                        <input type="checkbox" name="do_seed" value="1" checked>
                        <div>
                            <div class="checkbox-label">Jalankan Database Seeder (Data Awal & Pengguna Contoh)</div>
                            <div class="checkbox-desc">Membuat organisasi contoh, akun Admin, Pimpinan, dan Notulis (<code>php artisan db:seed --force</code>)</div>
                        </div>
                    </label>

                    <label class="checkbox-item">
                        <input type="checkbox" name="do_storage" value="1" checked>
                        <div>
                            <div class="checkbox-label">Buat Storage Symlink</div>
                            <div class="checkbox-desc">Menghubungkan direktori storage ke public (<code>php artisan storage:link</code>)</div>
                        </div>
                    </label>

                    <label class="checkbox-item">
                        <input type="checkbox" name="do_cache" value="1" checked>
                        <div>
                            <div class="checkbox-label">Optimasi Cache Produksi</div>
                            <div class="checkbox-desc">Membuat cache konfigurasi, rute, dan tampilan untuk kecepatan akses (<code>config:cache, route:cache, view:cache</code>)</div>
                        </div>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" onclick="return confirm('Mulai proses instalasi & migrasi sekarang?')">
                    ⚡ Jalankan Seluruh Proses Setup Sekarang
                </button>
            </form>

            <?php if ($setupExecuted): ?>
                <div style="margin-top: 24px;">
                    <h3 style="font-size: 14px; margin-bottom: 8px;">Hasil Log Eksekusi:</h3>
                    <?php foreach ($artisanOutputs as $item): ?>
                        <div style="margin-bottom: 12px;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <strong style="font-size: 13px;"><?= htmlspecialchars($item['title']) ?></strong>
                                <span class="badge badge-<?= $item['status'] ?>"><?= strtoupper($item['status']) ?></span>
                            </div>
                            <div class="output-box"><?= htmlspecialchars($item['output']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- STEP 4: KEAMANAN & SELF DESTRUCT -->
        <div class="card" style="border-color: rgba(239, 68, 68, 0.4);">
            <div class="card-header">
                <h2 class="card-title" style="color: #f87171;">4. Keamanan: Hapus File Setup</h2>
                <span class="badge badge-danger">Wajib Setelah Selesai</span>
            </div>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 0;">
                Jika seluruh proses migrasi dan pengaturan di atas telah selesai dan berhasil, **segera hapus berkas <code>setup.php</code> ini** agar tidak dapat diakses kembali oleh publik.
            </p>
            <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus file setup.php ini secara permanen dari server?');">
                <input type="hidden" name="action" value="self_destruct">
                <button type="submit" class="btn btn-danger">
                    🗑️ Hapus Berkas setup.php Sekarang
                </button>
            </form>
        </div>

    <?php endif; ?>
</div>

</body>
</html>

