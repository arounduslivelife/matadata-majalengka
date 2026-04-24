<?php
require_once 'auth.php';
// If already logged in, go straight to dashboard
if (isset($_SESSION['user_email'])) {
    header('Location: ./');
    exit;
}

$db = new SQLite3('database.sqlite');
$totalPackets = $db->querySingle("SELECT COUNT(*) FROM packages WHERE processed=1");
$totalAnomalies = $db->querySingle("SELECT COUNT(*) FROM packages WHERE processed=1 AND (risk_score='High' OR risk_score='ABSURD')");
$totalBudget = $db->querySingle("SELECT SUM(pagu) FROM packages WHERE processed=1");
$totalBudgetFormatted = $totalBudget >= 1000000000000 ? 'Rp' . number_format($totalBudget/1000000000000, 1) . ' Triliun' : 'Rp' . number_format($totalBudget/1000000000, 0) . ' Miliar';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matadata Majalengka — Public Monitoring Dashboard</title>
    <link rel="icon" type="image/png" href="favicon.png?v=3">
    <meta name="description" content="Platform transparansi anggaran publik Kabupaten Majalengka. Pengawasan pengadaan, dana desa, kemiskinan, dan infrastruktur dalam bentuk peta grafis.">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #020617; --accent: #3b82f6; --success: #10b981; --warning: #f59e0b; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: #f8fafc; overflow-x: hidden; }

        /* Animated BG */
        .bg-grid { position: fixed; inset: 0; background-image: linear-gradient(rgba(59,130,246,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(59,130,246,0.03) 1px, transparent 1px); background-size: 60px 60px; z-index: 0; }
        .bg-glow { position: fixed; border-radius: 50%; filter: blur(150px); z-index: 0; }
        .g1 { width: 600px; height: 600px; background: #3b82f6; top: -300px; right: -200px; opacity: 0.1; animation: gFloat 10s ease-in-out infinite; }
        .g2 { width: 500px; height: 500px; background: #8b5cf6; bottom: -200px; left: -200px; opacity: 0.08; animation: gFloat 12s ease-in-out infinite reverse; }
        .g3 { width: 400px; height: 400px; background: #10b981; top: 50%; left: 50%; opacity: 0.05; animation: gFloat 8s ease-in-out infinite; }
        @keyframes gFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(40px); } }

        /* Hero */
        .hero { position: relative; z-index: 1; min-height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; padding: 2rem; }
        .hero-content { max-width: 700px; animation: fadeUp 1s ease; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
        .hero-badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 16px; background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.2); border-radius: 20px; font-size: 0.75rem; color: #60a5fa; margin-bottom: 2rem; letter-spacing: 0.5px; }
        .hero-badge .dot { width: 6px; height: 6px; background: #10b981; border-radius: 50%; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
        .hero h1 { font-size: clamp(2.2rem, 5vw, 3.5rem); font-weight: 800; letter-spacing: -0.04em; line-height: 1.1; margin-bottom: 1.2rem; }
        .hero h1 span { background: linear-gradient(135deg, #3b82f6, #8b5cf6, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero p { font-size: 1.1rem; opacity: 0.5; max-width: 550px; margin: 0 auto 2.5rem; line-height: 1.6; }

        .cta-group { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .btn-primary { display: inline-flex; align-items: center; gap: 10px; padding: 15px 32px; background: var(--accent); color: white; border: none; border-radius: 16px; font-family: 'Outfit'; font-size: 1rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.3s; box-shadow: 0 8px 30px rgba(59,130,246,0.3); }
        .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 12px 40px rgba(59,130,246,0.4); }
        .btn-secondary { display: inline-flex; align-items: center; gap: 8px; padding: 15px 28px; background: rgba(255,255,255,0.05); color: #94a3b8; border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; font-family: 'Outfit'; font-size: 1rem; font-weight: 500; cursor: pointer; text-decoration: none; transition: all 0.3s; }
        .btn-secondary:hover { background: rgba(255,255,255,0.1); color: white; transform: translateY(-2px); }

        /* Stats Bar */
        .stats-bar { position: relative; z-index: 1; display: flex; justify-content: center; gap: 2rem; padding: 3rem 2rem; flex-wrap: wrap; }
        .stat { text-align: center; min-width: 150px; }
        .stat .num { font-size: 2.2rem; font-weight: 800; background: linear-gradient(135deg, #ffffff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat .lbl { font-size: 0.75rem; opacity: 0.4; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px; }

        /* Features */
        .features { position: relative; z-index: 1; max-width: 1000px; margin: 4rem auto; padding: 0 2rem; }
        .features h2 { text-align: center; font-size: 1.8rem; font-weight: 700; margin-bottom: 0.5rem; }
        .features .sub { text-align: center; opacity: 0.4; font-size: 0.9rem; margin-bottom: 3rem; }
        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; }
        .feature-card { background: rgba(15,23,42,0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; padding: 2rem; transition: all 0.3s; }
        .feature-card:hover { transform: translateY(-5px); border-color: rgba(59,130,246,0.2); box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
        .feature-card .icon { font-size: 2rem; margin-bottom: 1rem; }
        .feature-card h3 { font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem; }
        .feature-card p { font-size: 0.8rem; opacity: 0.5; line-height: 1.5; }

        /* Footer */
        .footer { position: relative; z-index: 1; text-align: center; padding: 4rem 2rem 2rem; border-top: 1px solid rgba(255,255,255,0.03); }
        .footer p { font-size: 0.75rem; opacity: 0.3; }
        .footer a { color: var(--accent); text-decoration: none; }

        .close-sawer:hover { background: rgba(255,255,255,0.1); }

        /* Scroll Indicator */
        .scroll-ind { position: absolute; bottom: 40px; left: 50%; transform: translateX(-50%); animation: bounce 2s infinite; opacity: 0.3; font-size: 0.75rem; }
        @keyframes bounce { 0%, 100% { transform: translateX(-50%) translateY(0); } 50% { transform: translateX(-50%) translateY(10px); } }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="bg-glow g1"></div>
    <div class="bg-glow g2"></div>
    <div class="bg-glow g3"></div>

    <section class="hero">
        <div class="hero-content">
            <div class="hero-badge"><span class="dot"></span> Platform Digital — Sistem Aktif</div>
            <h1>Transparansi Anggaran<br><span>Kabupaten Majalengka</span></h1>
            <p>Platform monitoring publik yang menyajikan data pengadaan barang/jasa, dana desa, sebaran kemiskinan, dan infrastruktur jalan dalam bentuk peta grafis.</p>
            <div class="cta-group">
                <a href="login" class="btn-primary">Masuk ke Dashboard</a>
                <a href="#fitur" class="btn-secondary">Pelajari Lebih Lanjut ↓</a>
            </div>
        </div>
        <div class="scroll-ind">scroll ↓</div>
    </section>

    <section class="stats-bar">
        <div class="stat"><div class="num"><?= number_format($totalPackets, 0, ',', '.') ?></div><div class="lbl">Paket Terdaftar</div></div>
        <div class="stat"><div class="num"><?= number_format($totalAnomalies, 0, ',', '.') ?></div><div class="lbl">Catatan Khusus</div></div>
        <div class="stat"><div class="num">Rp<?= $totalBudgetFormatted ?></div><div class="lbl">Total Anggaran Terpetakan</div></div>
        <div class="stat"><div class="num">4</div><div class="lbl">Layer Informasi</div></div>
    </section>

    <section class="features" id="fitur">
        <h2>Empat Layer Audit</h2>
        <p class="sub">Setiap layer memberikan perspektif berbeda untuk pengawasan anggaran yang komprehensif</p>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="icon">🔴</div>
                <h3>Audit Pengadaan</h3>
                <p>Monitoring daftar paket pengadaan dari SiRUP LKPP tahun anggaran 2025. Sistem memetakan sebaran paket untuk kemudahan pengawasan publik.</p>
            </div>
            <div class="feature-card">
                <div class="icon">🟢</div>
                <h3>Dana Desa</h3>
                <p>Transparansi alokasi Dana Desa untuk 343 desa. Bandingkan distribusi antar kecamatan dan pastikan desa Anda mendapat bagian yang adil.</p>
            </div>
            <div class="feature-card">
                <div class="icon">🟠</div>
                <h3>Peta Kemiskinan</h3>
                <p>Sebaran Keluarga Penerima Manfaat (KPM) BPNT dan PKH. Awasi apakah bantuan sosial sudah tepat sasaran di wilayah Anda.</p>
            </div>
            <div class="feature-card">
                <div class="icon">🟣</div>
                <h3>Infrastruktur Jalan</h3>
                <p>Peta kemantapan jalan desa dari OpenStreetMap & DPUTR. Identifikasi kecamatan dengan jalan paling rusak yang butuh perhatian.</p>
            </div>
        </div>
    </section>

    <section style="position:relative; z-index:1; max-width:700px; margin:4rem auto; padding:0 2rem; text-align:center;">
        <div style="background:rgba(15,23,42,0.6); backdrop-filter:blur(20px); border:1px solid rgba(255,255,255,0.05); border-radius:24px; padding:3rem;">
            <div style="font-size:2.5rem; margin-bottom:1rem;">📊</div>
            <h2 style="font-size:1.4rem; margin-bottom:0.5rem;">Sistem Pengawasan Terintegrasi</h2>
            <p style="opacity:0.5; font-size:0.85rem; line-height:1.6; margin-bottom:1.5rem;">Sistem ini merangkum data dari berbagai sumber resmi menggunakan algoritma pemetaan berbasis wilayah (kecamatan/desa) untuk memudahkan pemantauan distribusi anggaran dan realisasi pembangunan.</p>
            <a href="login" class="btn-primary" style="font-size:0.9rem; padding:12px 28px;">Mulai Mengawasi →</a>
        </div>
    </section>

    <footer class="footer">
        <p>Matadata Majalengka &copy; 2026 — <a href="https://github.com/arounduslivelife" target="_blank">Open Source Project</a><br>
        <a href="legal.php" target="_blank">Kebijakan Privasi</a> • <a href="legal.php" target="_blank">Syarat & Ketentuan</a><br>
        Data: SiRUP LKPP • Portal TKD Kemenkeu • DTKS Kemensos • OpenStreetMap</p>
    </footer>

    </script>
    </script>
</body>
</html>
