# RSV Helmet — Laravel

Aplikasi Laravel 13 untuk RSV Helmet. PHP berjalan di Docker, aset frontend
(Blade + Tailwind + Vite) dibangun di host.

## Stack

| Komponen | Versi / lokasi |
|---|---|
| Laravel | 13.x (`laravel/framework` ^13.17) |
| PHP | 8.4 (image `php:8.4-cli`) |
| Database | MariaDB 11 (container, port host `3308`) |
| App | `http://localhost:8001` |
| Panel admin | Filament 5 di `http://localhost:8001/admin` |
| Vite dev server | `http://localhost:5174` |

## Struktur container

- `app` — PHP CLI yang menjalankan `php artisan serve` (port 8000 di container,
  dipublikasikan ke **8001**). Folder project di-bind mount ke `/var/www/html`.
- `db` — MariaDB 11, data disimpan di volume `rsv_helmet_db-data`.

## Menjalankan

```bash
# 1. Siapkan environment (sekali saja)
cp .env.example .env        # jika .env belum ada
docker compose build
docker compose up -d

# 2. Generate key & migrasi
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate

# 3. Aset frontend (di host, butuh Node 20+)
npm install
npm run dev -- --port 5174
```

Buka `http://localhost:8001`.

### Perintah harian

```bash
docker compose up -d                     # nyalakan app + db
docker compose down                      # matikan (data DB tetap)
docker compose logs -f app               # lihat log
docker compose exec app php artisan ...  # artisan di container
docker compose exec app php artisan tinker
docker compose exec app composer ...     # composer di container

npm run dev                              # Vite dev server (port 5174)
npm run build                            # build produksi ke public/build
```

### Database

Kredensial dev diambil dari `.env` (dibaca juga oleh Docker Compose):

```dotenv
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306          # port internal container
DB_DATABASE=rsv_helmet
DB_USERNAME=laravel
DB_PASSWORD=<isi di .env lokal>
```

Akses dari host (mis. DBeaver/TablePlus): `127.0.0.1:3308`.

> Password pada `.env.example` hanya contoh. Ganti `DB_PASSWORD` dan
> `DB_ROOT_PASSWORD` sebelum dipakai di luar lokal.

## Panel Admin (Filament 5)

Panel admin tersedia di `http://localhost:8001/admin`.

- Provider: `app/Providers/Filament/AdminPanelProvider.php` (path `/admin`).
- Resource/pages/widgets otomatis ditemukan dari `app/Filament/`.
- Aset panel di-*publish* ke `public/css/filament`, `public/js/filament`,
  `public/fonts/filament` (di-ignore git; dibuat ulang lewat
  `php artisan filament:upgrade` saat deploy).

### Akun admin

Akun dibuat dengan `make:filament-user`. Untuk menambah akun baru:

```bash
docker compose exec app php artisan make:filament-user
```

### Membuat CRUD

```bash
docker compose exec app php artisan make:filament-resource Barang --generate
```

### Saat deploy / produksi

```bash
docker compose exec app php artisan filament:upgrade   # publish aset + clear cache
docker compose exec app php artisan filament:optimize  # cache komponen & icon
```

> Di luar environment `local`, model `User` perlu mengimplementasikan
> `FilamentUser` (`canAccessPanel()`) agar hanya user berwenang yang bisa masuk.

## Catatan

- File yang dibuat container tetap dimiliki user host karena compose memakai
  `user: "${UID}:${GID}"`.
- `.env` tidak di-commit.
- Port dipilih agar tidak bentrok dengan project `RSV_Helmet` lama
  (8000, 3307, 5173).
