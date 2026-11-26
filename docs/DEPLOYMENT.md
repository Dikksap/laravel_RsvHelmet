# Panduan Deployment & CI/CD RSV Helmet

Dokumentasi ini menjelaskan konfigurasi Docker siap produksi (*production-ready*) dan alur kerja CI/CD menggunakan **GitHub Actions** dan **GitHub Container Registry (`ghcr.io`)**.

---

## 1. Arsitektur Komponen

- **Image Registry**: `ghcr.io/dikksap/laravel_rsvhelmet`
- **Container App**: Nginx + PHP 8.4-FPM berbasis Alpine yang diatur oleh Supervisor dalam 1 container mandiri.
- **Port Production**: `8001:80` (Aplikasi diakses pada `http://192.168.1.168:8001/admin`).
- **Container Database**: MariaDB 11 pada network terisolasi dengan volume persisten `rsv_helmet_db_data`.
- **Volume Upload**: `rsv_helmet_app_storage` di-mount ke `/var/www/html/storage/app/public` untuk menyimpan berkas unggahan publik (misalnya gambar barang pada `items/`). Simlink `public/storage` dibuat otomatis oleh `entrypoint.sh` ketika container start; menghapus volume ini akan menghilangkan seluruh gambar barang.

---

## 2. Alur Kerja Otomatis (CI/CD GitHub Actions)

File workflow berada di [`.github/workflows/ci-cd.yml`](../.github/workflows/ci-cd.yml).

Setiap kali Anda melakukan `git push` ke branch `master`:
1. **Job `test`**:
   - Menjalankan linting kode via **Laravel Pint**.
   - Menjalankan **PHPUnit Test** menggunakan database in-memory SQLite.
2. **Job `build-and-push`**:
   - Jika pengujian lolos, GitHub Actions otomatis melakukan login ke `ghcr.io` menggunakan `${{ secrets.GITHUB_TOKEN }}`.
   - Melakukan multi-stage build:
     - Tahap Node: `npm ci && npm run build` (aset Vite).
     - Tahap Composer: dependensi PHP produksi teroptimasi.
     - Tahap Runtime: PHP 8.4-FPM + Nginx.
   - Mendorong image baru dengan tag `latest` dan tag commit SHA pendek (`ghcr.io/dikksap/laravel_rsvhelmet:latest`).
3. **Job `deploy`** *(Opsional)*:
   - Jika GitHub Secret `SSH_HOST`, `SSH_USER`, dan `SSH_KEY` ditambahkan di repo GitHub, GitHub Actions akan melakukan SSH ke server dan menjalankan perintah update otomatis.

---

## 3. Pengaturan Visibilitas Package di GitHub

Saat image pertama kali di-push oleh GitHub Actions ke `ghcr.io`:
1. Buka profil GitHub Anda -> tab **Packages** (`https://github.com/Dikksap?tab=packages`).
2. Pilih package **`laravel_rsvhelmet`**.
3. Masuk ke **Package Settings**.
4. Di bagian bawah (*Danger Zone*), ubah visibilitas menjadi **Public** agar server dapat melakukan `docker pull` tanpa perlu konfigurasi token berulang.
*(Jika dibiarkan Private, server harus login sekali via: `echo $PAT | docker login ghcr.io -u Dikksap --password-stdin`)*.

---

## 4. Setup Awal di Server (192.168.1.168)

Di server `rsv@192.168.1.168`:
Lokasi direktori produksi berada di: `/opt/web/RSV_Helmet_Laravel`

File yang diperlukan di server:
1. `docker-compose.prod.yml`
2. `.env` (berisi konfigurasi produksi, seperti `APP_KEY`, password database, dll)
3. `deploy.sh` (skrip pembantu untuk pembaruan)

### Perintah Operasional di Server:

```bash
# Masuk ke direktori aplikasi
cd /opt/web/RSV_Helmet_Laravel

# Menjalankan pembaruan / deploy image terbaru:
./deploy.sh

# Melihat status container:
docker compose -f docker-compose.prod.yml ps

# Melihat log aplikasi:
docker compose -f docker-compose.prod.yml logs -f app

# Menjalankan artisan di container produksi:
docker compose -f docker-compose.prod.yml exec -T app php artisan [perintah]
```
