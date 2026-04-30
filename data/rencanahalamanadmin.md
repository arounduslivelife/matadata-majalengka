# Blueprint Integrasi Backend AdminLTE untuk Dashboard Matadata

Dokumen ini memuat hasil analisis arsitektur data untuk ke-5 Layer di dashboard saat ini, serta *roadmap* implementasi untuk mengubahnya dari file statis menjadi sistem dinamis berbasis database dengan *backend* **AdminLTE**.

## 1. Analisis Layer Saat Ini & Sumber Datanya

Saat ini, aplikasi berjalan nyaris *serverless* (tanpa database SQL utama untuk GIS), mengandalkan PHP array yang di-*inject* dan file statis GeoJSON.

| Layer | Nama / Fungsi | Sumber Data Saat Ini | Metode Render |
|---|---|---|---|
| **Layer 1** | **Realisasi Anggaran (SIRUP / Inaproc)** | Variabel PHP `$stats` & `$year_totals` | Di-*inject* langsung via `json_encode` ke JS saat halaman diload. |
| **Layer 2** | **Dana Desa** | Variabel PHP `$village_stats` | Sama seperti Layer 1 (PHP Injection). |
| **Layer 3** | **Peta Kemiskinan** | Variabel PHP `$poverty_stats` | Sama seperti Layer 1 (PHP Injection). |
| **Layer 4** | **Infrastruktur (Aset & Proyek)** | File statis: `sarana_pendidikan.geojson`, `jembatan_kabupaten.geojson`, dll di folder `data/` | Di-*load* via `fetch()` API JS secara *asynchronous* ketika menu di-klik. |
| **Layer 5** | **Audit Intelligence (AI Vendor)** | Variabel PHP `$all_audits` | Sama seperti Layer 1 (PHP Injection). |

> **Arsitektur saat ini sangat cepat** (karena tidak ada *query database* yang berat), namun **sangat sulit dikelola** untuk jangka panjang karena admin harus mengedit file `.json`, `.geojson`, atau *hardcode* di *script* PHP jika ingin mengubah data.

---

## 2. Kelayakan Penggunaan AdminLTE (Feasibility)

**Apakah memungkinkan? SANGAT MEMUNGKINKAN!**
Menggunakan AdminLTE akan mengubah aplikasi ini dari sekadar "peta interaktif statis" menjadi **Sistem Informasi Geografis (SIG) kelas Enterprise**. 

### Keuntungan Migrasi:
1. **User Friendly**: Admin tidak perlu mengerti koding atau format GeoJSON. Cukup isi form di AdminLTE.
2. **Realtime Update**: Data yang diubah di AdminLTE akan langsung tampil di *front-end* tanpa perlu menjalankan skrip sinkronisasi.
3. **Multi-User**: Bisa membuat role (misal: Admin Pendidikan hanya bisa edit sekolah, Admin PUPR hanya bisa edit jembatan).

### Tantangan yang Harus Diatasi:
1. **Migrasi Data**: Semua file `.geojson` dan array PHP harus dimasukkan ke dalam database relasional (MySQL/MariaDB).
2. **Performa Peta**: `index.php` yang tadinya membaca GeoJSON statis, kini harus memanggil API (misal: `api/get_schools.php`) yang men-*generate* GeoJSON *on-the-fly* dari database. Ini butuh *caching* agar peta tidak lambat saat diload.

---

## 3. Desain Arsitektur Database (Usulan)

Kita perlu membuat skema database MySQL yang solid. Berikut adalah rancangan tabel utamanya:

*   **`tbl_kecamatan` & `tbl_desa`**: Menyimpan batas wilayah (untuk Layer 2 & 3).
*   **`tbl_paket_proyek`**: Menyimpan data Inaproc/SIRUP (Layer 1).
*   **`tbl_infrastruktur`**: Menyimpan titik koordinat (Lat/Lng), nama aset, dan tipe (Layer 4).
*   **`tbl_realisasi`**: Relasi *Many-to-Many* antara proyek dan infrastruktur.
*   **`tbl_vendor` & `tbl_audit`**: Menyimpan skor dan indikasi algoritma (Layer 5).

---

## 4. Rencana Implementasi (Roadmap)

Jika Anda setuju, kita bisa mengerjakannya secara bertahap agar sistem *front-end* yang ada sekarang tidak rusak.

### Fase 1: Setup Backend & Database (Persiapan)
1. *Setup* kerangka **AdminLTE** di subfolder `/admin`.
2. Buat database MySQL `db_matadata` dan desain struktur tabelnya.
3. Buat modul Login / Autentikasi untuk halaman Admin.

### Fase 2: Migrasi Layer 1-3 & Layer 5 (Data Tabular)
1. Buat fitur CRUD (Create, Read, Update, Delete) di AdminLTE untuk mengelola Proyek, Dana Desa, dan Kemiskinan.
2. Impor array PHP lama ke database.
3. Ubah `index.php` agar mengambil `$stats`, `$village_stats`, dll dari database, bukan dari *hardcode*.

### Fase 3: Migrasi Layer 4 (Data Spasial / GIS)
1. Buat halaman CRUD Infrastruktur (Sekolah & Jembatan) di AdminLTE, lengkapi dengan input *Latitude/Longitude* dan peta mini (*Leaflet.js*) untuk *picker* koordinat.
2. Impor ribuan data dari `sarana_pendidikan.geojson` ke tabel `tbl_infrastruktur`.
3. Buat *endpoint* API `api/geojson_sekolah.php` yang mengubah data tabel menjadi format GeoJSON standar.
4. Ubah fungsi `fetch('data/sarana_pendidikan.geojson')` di *front-end* untuk mengambil data dari *endpoint* API baru.
