# 🧠 MEMORI PROJECT: Matadata Majalengka Dashboard
**Terakhir Diperbarui:** 2026-04-28 15:05 (WIB)
**Status Utama:** Fase Injeksi Data Infrastruktur (Sekolah)

---

## ✅ 1. YANG SUDAH SELESAI (COMPLETED)
- **Optimasi Visual Dashboard**:
  - Implementasi logika "Dimmed Marker" (warna abu-abu) untuk sekolah/jembatan yang tidak memiliki paket anggaran di tahun terpilih.
  - Implementasi "Pulse Animation" (hijau neon) untuk sekolah/jembatan yang sedang ada pengerjaan aktif.
- **Environment Setup**:
  - Pemasangan Node.js v24 dan Playwright di sistem lokal.
  - Konfigurasi bypass execution policy Windows untuk menjalankan script automasi.
- **Scrapping Tahap 1**:
  - Melakukan penyisiran awal dan berhasil meningkatkan data dari **274 sekolah** menjadi **562 sekolah**.
- **Analisis Kesenjangan Data**:
  - Mengidentifikasi sekolah-sekolah yang terlewat di area pelosok/pegunungan (Contoh: SDN Argalingga 3).

---

## 🏗️ 2. YANG SEDANG DIKERJAKAN (IN PROGRESS)
- **Ultra Deep Scrapping (V2)**:
  - Berhasil menjalankan script `deep_school_scraper.js` dengan strategi **5x5 Grid Search**.
  - Berhasil mengumpulkan **1.702 titik koordinat** sekolah dari 26 kecamatan.
  - Status: SELESAI.

---

## ✅ 3. YANG SUDAH SELESAI (JUST FINISHED)
- **Master Data Injection (Layer 4)**:
  - Menginjeksi data dari `found_schools_ultra.json` ke `data/sarana_pendidikan.geojson`.
  - Meningkatkan populasi sekolah di dashboard dari **274** menjadi **1.881** titik.
  - Melakukan normalisasi nama dan preservasi data paket anggaran yang sudah ada.

---

## 📋 4. YANG AKAN DIKERJAKAN (NEXT STEPS)
1. **Pembersihan Nama (Sanitasi)**:
   - Membuat script untuk merapikan nama sekolah yang redundan atau memiliki format tidak standar hasil scraping.
2. **Final Dashboard Sync**:
   - Memastikan dashboard menampilkan jumlah populasi sekolah yang baru secara akurat dan responsif.
3. **Audit Keadilan Anggaran (Layer 4 Analysis)**:
   - Menjalankan analisis "Heatmap of Neglect" menggunakan dataset 1.881 sekolah vs paket anggaran 2025/2026.

---

## 🧭 5. VISI & STRATEGI LAYER 4 (SPATIAL & AUDIT INTELLIGENCE)
Data hasil scrapping "Ultra Deep" akan diolah pada Layer 4 untuk mencapai **Akurasi Forensik** melalui:

- **Discrepancy Engine**: 
  - Membandingkan daftar sekolah di APBD dengan koordinat fisik hasil scraping.
  - Mendeteksi potensi "Red Flag" jika ada anggaran pada lokasi yang secara fisik tidak ditemukan.
- **Heatmap of Neglect (Peta Pengabaian)**:
  - Visualisasi sekolah-sekolah yang "Gelap" (tanpa anggaran) dalam durasi waktu lama (misal: 5 tahun terakhir).
  - Memberikan peringatan otomatis bagi sekolah yang butuh perhatian mendesak.
- **Spatial Correlation (Keadilan Anggaran)**:
  - Menghitung apakah distribusi anggaran timpang antara sekolah pusat kota vs sekolah pelosok/pegunungan.
  - Memberikan Skor Ketimpangan Infrastruktur per Kecamatan.
- **Virtual Site Visit Integration**:
  - Menghubungkan koordinat presisi ke Google Street View untuk audit kondisi fisik secara virtual dan real-time.

---
**Catatan Penting:** 
*Jangan hapus folder `data/found_schools_ultra.json` selama proses scrapping berlangsung karena file tersebut menyimpan progress "Resume" jika terjadi interupsi.*
