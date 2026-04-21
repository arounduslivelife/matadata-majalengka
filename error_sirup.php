<?php
$url = isset($_GET['url']) ? $_GET['url'] : 'https://sirup.inaproc.id/';
$ray_id = substr(md5(uniqid()), 0, 16);
$ip = '182.10.183.74'; // Simulated IP from screenshot
$timestamp = date('d F Y H:i:s') . ' GMT+7';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ups! Ada yang Tidak Beres di Pihak Kami</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #fff;
            color: #333;
            font-family: 'Arial', sans-serif;
            line-height: 1.5;
        }

        .container {
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            position: relative;
        }

        .header {
            display: flex;
            align-items: center;
            margin-bottom: 60px;
        }

        .header img {
            height: 60px;
            margin-right: 15px;
        }

        h1 {
            color: #c1385b;
            font-size: 3.5rem;
            margin: 20px 0;
            font-weight: bold;
            line-height: 1.1;
        }

        .message {
            font-size: 1.1rem;
            line-height: 1.6;
            color: #444;
            max-width: 700px;
            margin-bottom: 40px;
        }

        .actions-title {
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 1rem;
        }

        ul {
            padding-left: 20px;
            margin-bottom: 60px;
        }

        li {
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .details {
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.9rem;
            color: #666;
            margin-top: 50px;
            border-top: 1px solid #eee;
            padding-top: 30px;
        }

        .details div {
            margin-bottom: 8px;
        }

        .details b {
            display: inline-block;
            width: 100px;
        }

        .illustration {
            position: absolute;
            right: 20px;
            top: 300px;
            opacity: 0.2;
            pointer-events: none;
        }

        a {
            color: #c1385b;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            h1 { font-size: 2.5rem; }
            .illustration { display: none; }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <img src="https://upload.wikimedia.org/wikipedia/commons/1/14/Lambang_LKPP.svg" alt="LKPP Logo">
        </div>

        <h1>Ups! Ada yang Tidak Beres di Pihak Kami</h1>

        <div class="message">
            Mohon maaf yang sebesar-besarnya, ada kesalahan tak terduga yang terjadi di <i>server</i> kami saat memproses permintaan Anda. Tim teknis kami sudah otomatis diberitahu dan sedang bekerja keras untuk segera memperbaikinya. Ini bukan kesalahan Anda, kok!
        </div>

        <div class="actions-title">Yang bisa Anda lakukan:</div>
        <ul>
            <li><b>Coba <i>refresh</i> halaman ini</b> dalam beberapa saat (seringkali ini bisa membantu!).</li>
            <li><b>Kembali ke beranda kami</b> atau coba lagi tindakan yang sama setelah beberapa menit.</li>
            <li>Jika Anda terus melihat pesan ini, mohon bantu kami dengan <a href="#">menghubungi tim dukungan kami</a>. Jelaskan apa yang Anda coba lakukan saat pesan ini muncul. Informasi Anda sangat berharga bagi kami!</li>
        </ul>

        <div class="details">
            <div><b>Ray ID</b> : <?php echo $ray_id; ?></div>
            <div><b>Client IP</b> : <?php echo $ip; ?></div>
            <div><b>URL</b> : <span style="word-wrap: break-word;"><?php echo htmlspecialchars($url); ?></span></div>
            <div><b>Timestamp</b> : <?php echo $timestamp; ?></div>
        </div>

        <!-- Decorative plug icon matching the design -->
        <div class="illustration">
            <svg width="300" height="300" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <rect x="10" y="45" width="40" height="15" rx="5" fill="rgba(193, 56, 91, 0.1)" />
                <rect x="50" y="48" width="15" height="4" fill="rgba(193, 56, 91, 0.1)" />
                <rect x="50" y="53" width="15" height="4" fill="rgba(193, 56, 91, 0.1)" />
                <circle cx="75" cy="52.5" r="10" fill="none" stroke="rgba(193, 56, 91, 0.1)" stroke-width="3" />
            </svg>
        </div>
    </div>

</body>
</html>
