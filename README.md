# SIMKD Backend

**SIMKD (Sistem Informasi Manajemen Kunjungan dan Donasi)** adalah aplikasi berbasis web untuk membantu pengelolaan kunjungan dan donasi pada Panti Asuhan Dr. Lucas Manado.

Repository ini merupakan bagian **backend** dari SIMKD dan berfokus pada penyediaan API serta pengolahan data dan proses sistem di sisi server.

## What is included

Backend menangani beberapa fungsi utama SIMKD, antara lain:

- Authentication dan pengelolaan pengguna
- Manajemen kunjungan
- Manajemen donasi finansial dan barang
- Inventaris dan distribusi barang
- Pelaporan
- Transparansi informasi donasi
- Integrasi layanan pihak ketiga, termasuk pembayaran dan email

## Technology

- PHP
- Laravel
- Laravel Sanctum
- Laravel Socialite
- Eloquent ORM
- Midtrans
- PHPUnit

## Repository Structure

```text
app/          # application logic
routes/       # API routes
database/     # migrations and database resources
tests/        # automated tests
```

## Related Repository

Frontend SIMKD: https://github.com/rvln/simkd-frontend

## Project Note

SIMKD dikembangkan sebagai bagian dari proyek tugas akhir dan merupakan implementasi spesifik untuk kebutuhan sistem yang diteliti.

Dokumentasi teknis dan panduan pengembangan lengkap dikelola secara terpisah.
