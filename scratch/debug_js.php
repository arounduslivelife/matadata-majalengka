<?php
// Mock data and bypass auth to see the rendered JS block
$stats = [];
$year_totals = [];
$all_audits = [];
$village_stats = [];
$poverty_stats = [];
$pad_kecamatan_json = file_exists('data/pad_majalengka_kecamatan.json') ? file_get_contents('data/pad_majalengka_kecamatan.json') : '{}';
$pad_global_json = file_exists('data/pad_majalengka.json') ? file_get_contents('data/pad_majalengka.json') : '{}';

echo "<script>\n";
echo "window.APP_DATA = {\n";
echo "    stats: " . json_encode($stats) . ",\n";
echo "    year_totals: " . json_encode($year_totals) . ",\n";
echo "    all_audits: " . json_encode($all_audits) . ",\n";
echo "    village_stats: " . json_encode($village_stats) . ",\n";
echo "    poverty_stats: " . json_encode($poverty_stats) . ",\n";
echo "    pad_kecamatan: " . $pad_kecamatan_json . ",\n";
echo "    pad_global: " . $pad_global_json . ",\n";
echo "    gps_granted: true\n";
echo "};\n";
echo "</script>";
?>
