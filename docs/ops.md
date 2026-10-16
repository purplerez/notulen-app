# Catatan Operasional (Operations Guide)

## 1. Tabel `audit_logs` (Append-Only)
Tabel `audit_logs` dirancang secara arsitektur sebagai tabel hanya-tambah (append-only) untuk kepatuhan audit dan keamanan.

### Rekomendasi Hak Akses Database (MySQL Produksi)
Di lingkungan produksi, pengguna database yang digunakan oleh aplikasi web sebaiknya hanya diberikan hak `SELECT` dan `INSERT` pada tabel `audit_logs`:

```sql
-- Berikan hak select dan insert
GRANT SELECT, INSERT ON notulen_app.audit_logs TO 'app_user'@'%';

-- Pastikan UPDATE dan DELETE dilarang
REVOKE UPDATE, DELETE, DROP ON notulen_app.audit_logs FROM 'app_user'@'%';
FLUSH PRIVILEGES;
```

Model `App\Models\AuditLog` juga telah dilengkapi pencegahan di level aplikasi (`LogicException` saat pemanggilan update atau delete).

---

## 2. Guard Platform (Super Admin)
Super admin tidak tercatat dalam tabel `users` dan tidak terikat pada `organization_id` manapun.
Autentikasi super admin menggunakan guard `platform` dengan model `App\Models\SuperAdmin` dan tabel `super_admins`.

---

## 3. Isolasi Tenant (Global Scope)
Semua model tenant mengimplementasikan trait `App\Models\Concerns\BelongsToOrganization`.
Scope ini mengisolasi setiap query berdasarkan `organization_id` pengguna yang sedang login.

Jika perlu melewati scope (misalnya pada artisan command atau background job):
```php
Model::withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)->get();
// Atau gunakan shortcut:
Model::withoutOrganizationScope()->get();
```
