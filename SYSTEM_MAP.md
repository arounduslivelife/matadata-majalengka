# Project Summary
- **Tujuan**: Dashboard Audit AI Terpadu untuk Kabupaten Majalengka (Matadata). Fokus pada audit paket pengadaan (SiRUP), transparansi Dana Desa, dan monitoring angka kemiskinan (KPM Bansos).
- **Tech Stack**:
    - **Frontend**: PHP 8.x (Raw/Flat), JavaScript (Vanilla), Leaflet.js (Map Engine), CSS3 (Glassmorphism UI).
    - **Backend Engine**: Node.js (untuk scraper dan AI audit).
    - **Database**: SQLite 3 (`database.sqlite`).
    - **AI**: Google Gemini API & Groq API.
- **Pola Arsitektur**: **Shared-Database Data-Driven Architecture**. Node.js bertugas sebagai *Data Worker* (Scraping & AI Audit), sementara PHP bertugas sebagai *Presentation Layer*.

# Core Logic Flow
Aplikasi ini tidak menggunakan Laravel, melainkan alur berbasis skrip data:
1. **Data Harvesting**: `scraper.js` -> Fetch data LPSE/SiRUP -> Simpan ke `database.sqlite`.
2. **AI Auditing**: `audit_engine.js` -> Baca paket dari DB -> Kirim ke LLM (Gemini/Groq) -> Simpan hasil audit & risk score kembali ke DB.
3. **Data Display**: `index.php` -> PDO SQLite -> Render GeoJSON (Map) + PHP Logic for Sidebar.

# Clean Tree
```text
matadata/
├── 32.10_Majalengka/       # Aset GeoJSON mentah
├── audit_engine.js         # Engine Utama Audit AI (Logic)
├── scraper.js              # Scraper Data Pengadaan
├── import_poverty.js       # Seeder Data Kemiskinan
├── import_villages.js      # Seeder Data Desa & Alokasi
├── index.php               # Entrypoint Dashboard Utama
├── database.sqlite         # Single Source of Truth (Database)
├── config.json             # Konfigurasi API Keys & Target
├── districts.geojson       # Batas Wilayah Kecamatan
└── villages.geojson        # Batas Wilayah Desa
```

# Module Map
- **UI/Dashboard (`index.php`)**: 
    - Mengelola 3 mode tampilan: `sirup`, `danadesa`, `kemiskinan`.
    - Integrasi Leaflet.js untuk visualisasi spasial.
- **Logic Engine (`audit_engine.js`)**: 
    - `auditPackage()`: Mengirim deskripsi paket ke AI untuk deteksi anomali.
    - `auto-retry logic`: Menangani rate limit API.
- **Data Scraper (`scraper.js`)**: 
    - Menangani penarikan data dari portal pengadaan.
- **Import Utility (`import_*.js`)**: 
    - Transformasi data GeoJSON dan statistik ke tabel SQLite.

# Data & Config
### Schema Inti:
- **`packages`**: Data pengadaan (nama, pagu, satker, status audit, risk_score, audit_note).
- **`villages`**: Data alokasi Dana Desa per desa.
- **`district_stats`**: Metadata kecamatan (poverty_count, KPM stats).
- **`progress.json`**: Tracking real-time proses audit AI yang sedang berjalan.

### Config:
- **`config.json`**: Menyimpan `GEMINI_API_KEY`, `GROQ_API_KEY`, dan parameter target wilayah/tahun.

# External Integrations
- **Google Gemini API**: Digunakan sebagai engine audit utama.
- **Groq API**: Digunakan sebagai fallback/alternative engine audit.
- **Leaflet & CartoDB**: Tile layer untuk peta dark-mode.

# Risks / Blind Spots
- **Manual Execution**: Proses audit AI dan scraping harus dijalankan manual via CLI (`node filename.js`), belum ada scheduler otomatis (Cron).
- **Data Matching**: Pemetaan antara nama wilayah di GeoJSON dengan nama wilayah di database sangat bergantung pada konsistensi penulisan string (case-sensitive).
- **Rate Limiting**: Ketergantungan tinggi pada API quotas external LLM.
