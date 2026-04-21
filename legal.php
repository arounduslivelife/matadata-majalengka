<?php
require_once 'auth.php';
// Legal page is public
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legal — Matadata Majalengka</title>
    <link rel="icon" type="image/png" href="favicon.png?v=3">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #020617; --accent: #3b82f6; --danger: #ef4444; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: #f8fafc; line-height: 1.6; }
        .bg-grid { position: fixed; inset: 0; background-image: linear-gradient(rgba(59,130,246,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(59,130,246,0.03) 1px, transparent 1px); background-size: 60px 60px; z-index: -1; }
        
        .container { max-width: 900px; margin: 0 auto; padding: 4rem 2rem; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--accent); text-decoration: none; font-weight: 600; margin-bottom: 2rem; transition: 0.3s; }
        .back-link:hover { transform: translateX(-5px); }
        
        header { margin-bottom: 4rem; text-align: center; }
        header h1 { font-size: 2.5rem; font-weight: 800; margin-bottom: 1rem; background: linear-gradient(135deg, #fff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        header p { opacity: 0.5; font-size: 1.1rem; }

        .legal-section { 
            background: rgba(15,23,42,0.6); 
            backdrop-filter: blur(20px); 
            border: 1px solid rgba(255,255,255,0.05); 
            border-radius: 24px; 
            padding: 3rem; 
            margin-bottom: 2rem;
        }
        .legal-section h2 { font-size: 1.5rem; color: var(--accent); margin-bottom: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 0.5rem; }
        .legal-section h3 { font-size: 1.1rem; margin: 1.5rem 0 0.5rem; color: #fff; }
        .legal-section p, .legal-section li { font-size: 0.95rem; opacity: 0.8; margin-bottom: 1rem; }
        .legal-section ul { margin-left: 1.5rem; margin-bottom: 1rem; }
        
        .alert { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); padding: 1.5rem; border-radius: 16px; margin: 2rem 0; }
        .alert b { color: var(--danger); display: block; margin-bottom: 5px; }
        
        footer { text-align: center; padding: 2rem; opacity: 0.3; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    
    <div class="container">
        <a href="landing.php" class="back-link">← Kembali ke Utama</a>
        
        <header>
            <h1>Syarat, Ketentuan & Kebijakan Privasi</h1>
            <p>Transparansi hari ini untuk Majalengka yang lebih baik di masa depan.</p>
        </header>

        <div class="legal-section">
            <h2>1. Syarat & Ketentuan (ToS)</h2>
            <p>Selamat datang di Matadata Majalengka. Dengan mengakses platform ini, Anda setuju untuk terikat oleh syarat dan ketentuan berikut:</p>
            
            <h3>Status Independen</h3>
            <p>Platform ini adalah **proyek independen** (open-source) dan **TIDAK** berafiliasi, disponsori, atau disetujui oleh Pemerintah Kabupaten Majalengka atau instansi pemerintah manapun. Segala opini dan analisis di sini merupakan tanggung jawab pengelola platform.</p>

            <div class="alert">
                <b>Pernyataan Penting Analisis AI</b>
                Analisis yang ditampilkan diproses oleh Kecerdasan Buatan (AI) secara probabilitas. AI memiliki kemungkinan untuk melakukan kesalahan interpretasi atau halusinasi data. Skor risiko ("High", "Absurd", dll) bersifat indikatif dan informatif, **BUKAN** merupakan bukti hukum final atau vonis bersalah atas tindak korupsi atau penyimpangan.
            </div>

            <h3>Sumber Data Publik</h3>
            <p>Data yang ditampilkan di platform ini diambil secara berkala dari sumber data publik resmi:</p>
            <ul>
                <li><strong>SiRUP LKPP</strong> (sirup.lkpp.go.id)</li>
                <li><strong>Portal TKD Kemenkeu</strong></li>
                <li><strong>Data Terpadu Kesejahteraan Sosial (DTKS)</strong></li>
                <li><strong>OpenStreetMap (OSM)</strong> untuk data infrastruktur.</li>
            </ul>
            <p>Kami tidak menjamin keakuratan 100% dari data sumber tersebut, namun kami berusaha menyajikannya secara transparan.</p>

            <h3>Batasan Tanggung Jawab</h3>
            <p>Pengelola Matadata Majalengka tidak bertanggung jawab atas tindakan yang diambil oleh pengguna atau pihak lain berdasarkan informasi dari platform ini. Penggunaan data untuk kepentingan hukum wajib disertai verifikasi ulang ke sumber aslinya.</p>
        </div>

        <div class="legal-section">
            <h2>2. Kebijakan Privasi</h2>
            <p>Kami sangat menghargai privasi Anda. Berikut adalah data yang kami kumpulkan saat Anda mengakses dashboard:</p>

            <h3>Data yang Dikumpulkan</h3>
            <ul>
                <li><strong>Profil Google:</strong> Nama, Email, dan Foto Profil (melalui Google OAuth) untuk identitas login.</li>
                <li><strong>Lokasi GPS:</strong> Koordinat geografis saat Anda memberikan izin (GPS Prompt).</li>
                <li><strong>Metrik Teknis:</strong> Alamat IP, jenis peramban (browser), dan waktu akses.</li>
            </ul>

            <h3>Tujuan Pengumpulan Data</h3>
            <p>Data tersebut dikumpulkan murni untuk tujuan <strong>Audit & Transparansi</strong>. Kami mencatat siapa yang mengakses data audit anggaran Majalengka untuk mencegah penyalahgunaan data dan menjaga akuntabilitas platform ini.</p>

            <h3>Keamanan & Retensi</h3>
            <p>Data Anda disimpan secara aman di database lokal kami dan tidak akan dijual ke pihak ketiga. Anda dapat meminta penghapusan riwayat akses Anda dengan menghubungi kami.</p>
        </div>

        <div class="legal-section">
            <h2>3. Kontak & Masukan</h2>
            <p>Jika Anda memiliki pertanyaan terkait legalitas, privasi, atau permintaan koreksi data, silakan hubungi pengelola di:</p>
            <p style="font-weight: 600; color: var(--accent);">aroundusplay@gmail.com</p>
        </div>

        <footer>
            Matadata Majalengka — &copy; 2026. Diperbarui pada 21 April 2026.
        </footer>
    </div>
</body>
</html>
