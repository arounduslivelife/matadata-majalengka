# Project Summary
- **Tujuan**: Dashboard monitoring & transparansi anggaran Kabupaten Majalengka (**Matadata**). Layer: realisasi belanja (Inaproc), Dana Desa, kemiskinan/KPM, infrastruktur, PAD, audit pengadaan AI.
- **Tech Stack**:
    - **Frontend**: PHP 8.x, JavaScript (Vanilla), Leaflet.js, CSS3 (glassmorphism).
    - **Backend presentasi**: PHP (`index.php`, API `get_*.php`) via **PDO MySQL** (`db.php`).
    - **Data workers**: Node.js (scraper, import, audit AI) — mayoritas skrip baru menulis ke **MySQL** (`config.json` → `db.*`).
    - **AI**: Google Gemini & Groq (lihat `config.json`).
- **Pola arsitektur**: **Shared-database, data-driven**. Node/CLI mengisi & mengaudit data; PHP + JS menampilkan peta dan sidebar.

# Database (penting — baca ini dulu)

| Peran | Teknologi | File / koneksi | Dipakai oleh |
|--------|-----------|-----------------|--------------|
| **Utama (produksi)** | **MySQL/MariaDB** | `db.php` ← `config.json` (`db.host`, `db.name`, …) | `index.php`, `get_audits.php`, `admin.php`, `admin_import_realisasi.php`, scraper Dana Desa (`scrape_jaga_desa*.js`), dll. |
| **Legacy — statistik landing** | SQLite 3 | `database.sqlite` (lokal, tidak di-repo) | `landing.php` (hitung paket/anomali/pagu di halaman depan) |
| **Legacy — log kunjungan** | SQLite 3 | `audit_trail.sqlite` (di-ignore git) | `log_visit.php`, `visitors.php`, `setup_visitors.php` |
| **Legacy — ETL lama** | SQLite 3 | `database.sqlite` via `better-sqlite3` | Skrip `import_*.js`, `populate_budgets.js`, dll. (arsip/migrasi) |
| **Migrasi sekali jalan** | — | `export_to_mysql.php` | SQLite → MySQL |

> **Catatan:** Dokumentasi lama pernah menyebut `database.sqlite` sebagai *single source of truth*. Itu **sudah tidak berlaku** untuk dashboard. Sumber kebenaran operasional dashboard = **MySQL**, terutama tabel **`realizations`** untuk layer Realisasi.

# Core Logic Flow
1. **Data harvesting**: scraper/import (Node atau admin CSV) → **MySQL** (`realizations`, data desa, dll.).
2. **AI auditing** (opsional): `audit_engine.js` / `process_audits.php` → baca/tulis paket + `risk_score`, `audit_note`.
3. **Tampilan**: `index.php` memuat peta; `get_audits.php` (JSON) + GeoJSON statis → `assets/js/main.js` + `assets/js/insights.js`.

# Clean Tree (ringkas)
```text
matadata/
├── index.php                 # Dashboard utama (MySQL)
├── db.php                    # Koneksi PDO MySQL
├── config.json               # API keys, db.*, ADMIN_EMAILS
├── get_audits.php            # API realisasi → tabel realizations
├── admin.php                 # Panel admin
├── admin_import_realisasi.php
├── includes/
│   ├── sidebar.php           # Sidebar + tab insight (Ringkas/Highlight/Vendor/Kec)
│   └── RealisasiImporter.php
├── assets/js/
│   ├── main.js               # Peta, mode layer, pencarian, detail paket
│   └── insights.js           # Tab insight realisasi
├── landing.php               # Halaman login/marketing (masih SQLite legacy)
├── log_visit.php             # Log GPS kunjungan → audit_trail.sqlite
├── visitors.php              # Admin log pengunjung (SQLite)
├── audit_engine.js           # Audit AI (periksa target DB di skrip)
├── scrape_jaga_desa*.js      # Scraper Dana Desa → MySQL
├── import_*.js               # Legacy: banyak yang masih database.sqlite
├── export_to_mysql.php       # Migrasi SQLite → MySQL
├── districts.geojson
├── villages.geojson
└── data/                     # CSV/GeoJSON sumber impor
```

# Module Map
- **Dashboard (`index.php`)**:
    - Mode: `sirup` (realisasi), `danadesa`, `kemiskinan`, `infrastruktur`, `pad`, `audit`.
    - Sidebar realisasi: tab **Ringkas | Highlight | Vendor | Kecamatan** (`insights.js`).
    - Pencarian paket/kecamatan, detail paket, drill-down vendor.
- **Admin (`admin.php`)**:
    - Import CSV realisasi (format Inaproc) ke MySQL.
- **API data**:
    - `get_audits.php` — paket realisasi untuk peta & sidebar.
    - `get_village_detail(s).php` — Dana Desa.
- **Audit AI (`audit_engine.js`, `process_audits.php`)**:
    - Deteksi anomali, skor risiko, catatan audit.
- **Scraper / import**:
    - Skrip baru: MySQL via `config.json`.
    - Skrip lama `import_*.js`: masih mendokumentasikan `database.sqlite` di header — anggap **arsip**, jangan dianggap jalur produksi.

# Data & Config
### Skema inti (MySQL — tidak lengkap)
- **`realizations`**: realisasi belanja (nama paket, pagu, kecamatan, vendor, satker, status, tahun, lat/lng, risk_score, audit_note).
- Tabel desa/kemiskinan/PAD: lihat migrasi & skrip setup (`setup_db_dana_desa.js`, dll.).

### Config
- **`config.json`**: `db.*`, kunci API AI, `GOOGLE_*`, `ADMIN_EMAILS`.

### File legacy (SQLite, opsional di server)
- **`database.sqlite`**: dipakai `landing.php` + skrip import lama.
- **`audit_trail.sqlite`**: log kunjungan pengguna dashboard.

# External Integrations
- Google OAuth (`auth.php`, `callback.php`).
- Gemini / Groq untuk audit.
- Leaflet + tile (Carto/dark).

# Risks / Blind Spots
- **Dokumentasi vs kode**: beberapa skrip CLI masih hardcode SQLite; jalur **web utama** sudah MySQL.
- **`landing.php`**: jika `database.sqlite` tidak ada, statistik landing bisa gagal — dashboard tetap bisa jalan.
- **Matching wilayah**: nama di GeoJSON vs database harus konsisten.
- **Rate limit** API LLM untuk audit batch.
- **Cron**: audit/scrape belum terjadwal otomatis di semua environment.

# Roadmap dokumentasi & data (opsional)
- [ ] Satukan log kunjungan (`log_visit.php`) ke MySQL.
- [ ] `landing.php` baca statistik dari MySQL.
- [ ] Tandai atau pindahkan skrip `import_*.js` legacy ke folder `archive/`.
