<?php
require 'auth.php';
$user = ['gps_granted' => false];
$stats = [];
$year_totals = [];
$all_audits = [];
$village_stats = [];
$poverty_stats = [];
$pad_kecamatan_json = '{}';
$pad_global_json = '{}';

$js = 'window.APP_DATA = { stats: ' . json_encode($stats) . ', year_totals: ' . json_encode($year_totals) . ', all_audits: ' . json_encode($all_audits) . ', village_stats: ' . json_encode($village_stats) . ', poverty_stats: ' . json_encode($poverty_stats) . ', pad_kecamatan: ' . $pad_kecamatan_json . ', pad_global: ' . $pad_global_json . ', gps_granted: ' . ($user['gps_granted'] ? 'true' : 'false') . '};';

file_put_contents('scratch/test_js2.js', $js);
echo "Done";
