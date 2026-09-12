# SIMKD Backend

Backend API untuk **Sistem Informasi Manajemen Kunjungan dan Donasi (SIMKD)** Panti Asuhan Dr. Lucas Manado.

Repository ini menangani business logic, authentication & authorization, pengelolaan kunjungan, donasi, inventaris, distribusi, laporan, transparansi publik, integrasi pembayaran, email notification, dan persistence data.

> **Status:** Thesis/project implementation. Data pada repository bersifat dummy/development data dan bukan data produksi.

## Technology Stack

| Area | Technology |
|---|---|
| Language | PHP 8.3+ |
| Framework | Laravel 13 |
| Authentication | Laravel Sanctum |
| OAuth | Laravel Socialite |
| Database | Relational database via Laravel Eloquent & migrations |
| Payment | Midtrans + manual payment flow |
| Email | Resend / Symfony Mailer integration |
| PDF | DomPDF |
| Testing | PHPUnit / Laravel Test Suite |

## Architecture

Backend menggunakan pendekatan **Modular Monolith dengan Service Layer** di atas struktur Laravel.

```text
HTTP Request
    ↓
Route
    ↓
Controller / Form Request
    ↓
Service Layer
    ↓
Model / Database
    ↓
External Service (bila diperlukan)
```

Area utama berada di `app/`:

```text
app/
├── Enums/
├── Http/
├── Mail/
├── Models/
├── Providers/
└── Services/
```

Route API utama berada di `routes/api.php`, sedangkan database schema dikelola melalui migration di `database/migrations/`.

## Main Capabilities

### Authentication & Users

- Registration dan email verification
- Login/logout berbasis Sanctum
- Password recovery
- Google OAuth
- Role-based access untuk pengunjung, pengurus panti, dan kepala panti

### Visit Management

- Pengajuan kunjungan berdasarkan tanggal dan slot
- Validasi kapasitas
- Approval, rejection, dan reschedule
- Penyelesaian kunjungan dan pencatatan no-show
- Notifikasi terkait perubahan status

### Donation Management

- Donasi finansial
- Donasi barang
- Midtrans dan pembayaran manual
- Tracking code publik
- Invoice
- Webhook dan status reconciliation

### Inventory & Distribution

- Katalog kebutuhan
- Virtual stock untuk donasi barang yang belum check-in
- Check-in barang
- Pencatatan inventaris
- Distribusi barang
- Rejected log / audit trail

### Reporting & Transparency

- Laporan kunjungan
- Laporan donasi
- Informasi kebutuhan publik
- Public donation tracking
- Data yang dibatasi sesuai konteks publik

## API

API tersedia di prefix `/api`.

Kelompok endpoint utama meliputi:

```text
/api
├── auth & user
├── capacities
├── visits
├── donations
├── inventory
├── distribution
├── reports
├── validation
├── public transparency
└── admin moderation
```

Dokumentasi kontrak API dan endpoint berada pada **SIMKD Documentation**, khususnya bagian API. README ini hanya menjadi entry point agar source code dan dokumentasi tidak bercampur menjadi satu dokumen raksasa.

## Local Development

### Requirements

Pastikan tersedia:

- PHP 8.3+
- Composer
- Node.js / npm
- Database relasional yang didukung Laravel

### Installation

```bash
composer install

cp .env.example .env
php artisan key:generate

php artisan migrate
```

Sesuaikan `.env` untuk database, mail, OAuth, Midtrans, storage, dan konfigurasi aplikasi lainnya sebelum menjalankan fitur terkait.

### Run API

```bash
php artisan serve
```

Default local URL:

```text
http://localhost:8000
```

### Run Queue Worker

Fitur tertentu menggunakan queued jobs, terutama notification/integration flow. Untuk development:

```bash
php artisan queue:listen --tries=1 --timeout=0
```

### Run Tests

```bash
php artisan test
```

atau:

```bash
composer test
```

### Code Formatting

```bash
./vendor/bin/pint
```

## Environment

Jangan commit credential atau secret ke repository.

Configuration yang perlu diperhatikan antara lain:

```text
APP_*
DB_*
MAIL_*
GOOGLE_*
MIDTRANS_*
RESEND_*
FILESYSTEM_*
```

Gunakan `.env.example` sebagai baseline dan simpan secret hanya pada environment lokal/hosting yang sesuai.

## Important Domain Notes

Beberapa behavior sistem bersifat cross-domain. Contohnya, pengajuan kunjungan dapat membawa konteks donasi, dan perubahan status kunjungan tertentu dapat memengaruhi donasi terkait.

Ada pula behavior implementasi yang perlu diperhatikan saat maintenance, termasuk perbedaan aturan capacity handling pada jalur approval tertentu. Detail discrepancy dan status verifikasinya dicatat dalam dokumentasi domain/traceability, bukan disamarkan di README.

## Repository Structure

```text
.
├── app/              # application code
├── bootstrap/        # framework bootstrap
├── config/           # application configuration
├── database/         # migrations, factories, seeders
├── public/           # public assets / entry point
├── resources/        # backend-side resources
├── routes/           # API routes
├── storage/          # runtime files
├── tests/             # automated tests
├── composer.json
└── README.md
```

## Documentation

Dokumentasi teknis proyek dipisahkan dari source code dan mencakup:

- Product & business rules
- Backend architecture
- Frontend architecture
- Database
- API contract
- Testing
- Operations
- Technical decisions
- Traceability
- Maintenance & change impact

README ini berfungsi sebagai **repository-level entry point**. Detail implementasi tidak sengaja diduplikasi di sini agar ketika kode berubah, kita tidak perlu memperbaiki informasi yang sama di lima tempat berbeda.

## Related Repository

Frontend SIMKD:

- https://github.com/rvln/simkd-frontend

## License

Project-specific thesis implementation. See repository configuration and project agreement for applicable usage terms.
