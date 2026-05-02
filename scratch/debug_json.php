<?php
$db = new SQLite3('database.sqlite');
$q = $db->query("SELECT * FROM packages WHERE tahun = 2024 LIMIT 5");
$all = [];
while ($r = $q->fetchArray(SQLITE3_ASSOC)) {
    $all[] = [
        'nama' => $r['nama_paket'],
        'vendor' => $r['pemenang']
    ];
}
echo json_encode($all, JSON_PRETTY_PRINT);
?>
