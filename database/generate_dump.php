<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$conn = new Illuminate\Database\MySqlConnection(
    new PDO('sqlite::memory:'),
    'forge',
    '',
    ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '']
);
$conn->useDefaultSchemaGrammar();
$conn->useDefaultPostProcessor();

config([
    'database.default' => 'mysql',
    'database.connections.mysql' => [
        'driver' => 'mysql',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ],
]);

app('db')->extend('mysql', function () use ($conn) {
    return $conn;
});
app('db')->purge('mysql');

$schema = $conn->getSchemaBuilder();
app()->instance('db.schema', $schema);

$migrator = app('migrator');
$migrator->setConnection('mysql');

$repository = new class implements Illuminate\Database\Migrations\MigrationRepositoryInterface
{
    public function getRan()
    {
        return [];
    }

    public function getLast()
    {
        return [];
    }

    public function getMigrationBatches()
    {
        return [];
    }

    public function log($file, $batch) {}

    public function delete($migration) {}

    public function getNextBatchNumber()
    {
        return 1;
    }

    public function createRepository() {}

    public function repositoryExists()
    {
        return true;
    }

    public function deleteRepository() {}

    public function setSource($name) {}

    public function getMigrations($steps)
    {
        return [];
    }

    public function getLastBatchNumber()
    {
        return 1;
    }

    public function getMigrationsByBatch($batch)
    {
        return [];
    }
};

$ref = new ReflectionProperty($migrator, 'repository');
$ref->setAccessible(true);
$ref->setValue($migrator, $repository);

$queries = $conn->pretend(function () use ($migrator) {
    $migrator->run([database_path('migrations')]);
});

$migrationFiles = $migrator->getMigrationFiles([database_path('migrations')]);

$out = [];
$out[] = '-- ========================================================';
$out[] = '-- APLIKASI NOTULENSI RAPAT (LARAVEL 13) - DATABASE DUMP';
$out[] = '-- Siap di-copy-paste ke phpMyAdmin (MySQL 8.0 / MariaDB)';
$out[] = "-- Tanggal: " . date('Y-m-d H:i:s');
$out[] = '-- ========================================================\n';
$out[] = 'SET NAMES utf8mb4;';
$out[] = 'SET FOREIGN_KEY_CHECKS = 0;';
$out[] = 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";';
$out[] = 'SET time_zone = "+00:00";' . "\n";

$out[] = '-- --------------------------------------------------------';
$out[] = '-- 1. TABEL MIGRATIONS (Agar sinkron dengan Laravel Artisan)';
$out[] = '-- --------------------------------------------------------';
$out[] = 'CREATE TABLE IF NOT EXISTS `migrations` (';
$out[] = '  `id` int unsigned NOT NULL AUTO_INCREMENT,';
$out[] = '  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,';
$out[] = '  `batch` int NOT NULL,';
$out[] = '  PRIMARY KEY (`id`)';
$out[] = ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;' . "\n";

$out[] = 'INSERT INTO `migrations` (`migration`, `batch`) VALUES';
$migrationRows = [];
foreach (array_keys($migrationFiles) as $m) {
    $migrationRows[] = "('" . addslashes($m) . "', 1)";
}
$out[] = implode(",\n", $migrationRows) . ";\n";

$out[] = '-- --------------------------------------------------------';
$out[] = '-- 2. STRUKTUR TABEL (SCHEMA DDL)';
$out[] = '-- --------------------------------------------------------';
foreach ($queries as $q) {
    $out[] = $q['query'] . ';';
}

$out[] = "\n" . '-- --------------------------------------------------------';
$out[] = '-- 3. DATA AWAL (SEED DATA)';
$out[] = '-- --------------------------------------------------------' . "\n";

// Password hash for "password"
$pwdHash = '$2y$12$t2Jl/HVRdq9qpXYmcL0FC.8wdK4Vfz.ZckdO355KUktXy0UNXiATK';

$out[] = '-- Organisasi Awal';
$out[] = 'INSERT INTO `organizations` (`id`, `name`, `slug`, `address_text`, `kop_path`, `plan`, `quota_minutes`, `parent_id`, `settings`, `created_at`, `updated_at`) VALUES';
$out[] = "(1, 'SMK Negeri 1 Contoh', 'smkn1-contoh', 'Jl. Pendidikan No. 1, Kota Contoh', NULL, 'internal', 6000, NULL, '{\"leader_label\":\"Kepala Sekolah\"}', NOW(), NOW());\n";

$out[] = '-- Pengguna Organisasi (Password: password)';
$out[] = 'INSERT INTO `users` (`id`, `organization_id`, `name`, `email`, `password`, `role`, `is_active`, `remember_token`, `created_at`, `updated_at`) VALUES';
$out[] = "(1, 1, 'Admin SMKN1', 'admin@smkn1.test', '" . $pwdHash . "', 'admin', 1, NULL, NOW(), NOW()),";
$out[] = "(2, 1, 'Kepala Sekolah (Pimpinan)', 'pimpinan@smkn1.test', '" . $pwdHash . "', 'pimpinan', 1, NULL, NOW(), NOW()),";
$out[] = "(3, 1, 'Notulis Satu', 'notulis1@smkn1.test', '" . $pwdHash . "', 'notulis', 1, NULL, NOW(), NOW()),";
$out[] = "(4, 1, 'Notulis Dua', 'notulis2@smkn1.test', '" . $pwdHash . "', 'notulis', 1, NULL, NOW(), NOW());\n";

$out[] = '-- Super Admin Platform (Guard: platform, Password: password)';
$out[] = 'INSERT INTO `super_admins` (`id`, `name`, `email`, `password`, `totp_secret`, `totp_enabled`, `totp_confirmed_at`, `remember_token`, `created_at`, `updated_at`) VALUES';
$out[] = "(1, 'Super Admin Platform', 'admin@platform.test', '" . $pwdHash . "', NULL, 0, NULL, NULL, NOW(), NOW());\n";

$out[] = '-- Penandatangan Organisasi';
$out[] = 'INSERT INTO `organization_signatories` (`id`, `organization_id`, `name`, `position`, `role_label`, `sort`, `created_at`, `updated_at`) VALUES';
$out[] = "(1, 1, 'Drs. H. Mulyadi, M.Pd.', 'Kepala Sekolah', 'Mengetahui', 1, NOW(), NOW()),";
$out[] = "(2, 1, 'Budi Santoso, S.Pd.', 'Waka Kurikulum', 'Ketua Rapat', 2, NOW(), NOW()),";
$out[] = "(3, 1, 'Notulis Satu', 'Staf Tata Usaha', 'Notulis', 3, NOW(), NOW());\n";

$out[] = '-- Buku Kontak Organisasi';
$out[] = 'INSERT INTO `org_contacts` (`id`, `organization_id`, `name`, `position`, `phone_e164`, `created_at`, `updated_at`) VALUES';
$out[] = "(1, 1, 'Budi Santoso, S.Pd.', 'Waka Kurikulum', '6281234567801', NOW(), NOW()),";
$out[] = "(2, 1, 'Siti Aminah, M.Pd.', 'Waka Kesiswaan', '6281234567802', NOW(), NOW()),";
$out[] = "(3, 1, 'Ir. Hendro Kusumo', 'Ketua Komite Sekolah', '6281234567803', NOW(), NOW());\n";

$out[] = '-- Tarif Baseline Layanan (Price Rates)';
$out[] = 'INSERT INTO `price_rates` (`id`, `provider`, `model`, `unit`, `price_usd`, `effective_from`, `created_at`, `updated_at`) VALUES';
$out[] = "(1, 'groq', 'whisper-large-v3-turbo', 'detik_audio', 0.00005000, '2026-01-01 00:00:00', NOW(), NOW()),";
$out[] = "(2, 'groq', 'llama-3.3-70b-versatile', 'token', 0.00000080, '2026-01-01 00:00:00', NOW(), NOW()),";
$out[] = "(3, 'local_disk', 'audio_storage', 'gb_bulan', 0.02300000, '2026-01-01 00:00:00', NOW(), NOW());\n";

$out[] = '-- Kurs Mata Uang Default (FX Rate USD -> IDR)';
$out[] = 'INSERT INTO `fx_rates` (`id`, `date`, `usd_idr`, `created_at`, `updated_at`) VALUES';
$out[] = "(1, '" . date('Y-m-d') . "', 16000.00, NOW(), NOW());\n";

$out[] = 'SET FOREIGN_KEY_CHECKS = 1;';

$sqlContent = implode("\n", $out);
file_put_contents(base_path('database/install_notulen_app.sql'), $sqlContent);
file_put_contents(public_path('database.sql'), $sqlContent);
echo "SUCCESS: Generated database/install_notulen_app.sql and public/database.sql (" . strlen($sqlContent) . " bytes)\n";

