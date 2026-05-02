<?php
$sqlite = new SQLite3('database.sqlite');
$mysql_file = 'matadata_export.sql';

$handle = fopen($mysql_file, 'w');
fwrite($handle, "SET NAMES utf8mb4;\n");
fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

// Create Table Structure for MySQL
$tables = [
    'realizations' => "CREATE TABLE `realizations` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `kecamatan` varchar(255) DEFAULT NULL,
      `nama_paket` text DEFAULT NULL,
      `total_nilai` double DEFAULT NULL,
      `risk_score` varchar(50) DEFAULT '0',
      `audit_note` text DEFAULT NULL,
      `satker` varchar(255) DEFAULT NULL,
      `vendor` varchar(255) DEFAULT NULL,
      `status` varchar(100) DEFAULT NULL,
      `tahun` int(11) DEFAULT NULL,
      `lat` double DEFAULT NULL,
      `lng` double DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_kec_tahun` (`kecamatan`,`tahun`),
      KEY `idx_tahun` (`tahun`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'villages' => "CREATE TABLE `villages` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `nm_kecamatan` varchar(255) DEFAULT NULL,
      `nm_kelurahan` varchar(255) DEFAULT NULL,
      `budget_2025` double DEFAULT NULL,
      `risk_score` varchar(50) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'district_stats' => "CREATE TABLE `district_stats` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `nm_kecamatan` varchar(255) DEFAULT NULL,
      `poverty_count` int(11) DEFAULT NULL,
      `kpm_pkh` int(11) DEFAULT NULL,
      `kpm_bpnt` int(11) DEFAULT NULL,
      `road_firmness_pct` double DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($tables as $name => $sql) {
    fwrite($handle, "DROP TABLE IF EXISTS `$name`;\n$sql\n\n");
    
    $results = $sqlite->query("SELECT * FROM $name");
    $cols_query = $sqlite->query("PRAGMA table_info($name)");
    $cols = [];
    while($c = $cols_query->fetchArray(SQLITE3_ASSOC)) { $cols[] = "`".$c['name']."`"; }
    
    fwrite($handle, "INSERT INTO `$name` (" . implode(", ", $cols) . ") VALUES \n");

    $first = true;
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        if (!$first) fwrite($handle, ",\n");
        $values = [];
        foreach ($row as $val) {
            if ($val === null) $values[] = "NULL";
            elseif (is_numeric($val)) $values[] = $val;
            else {
                $escaped = str_replace("\\", "\\\\", $val);
                $escaped = str_replace("'", "\'", $escaped);
                $values[] = "'" . $escaped . "'";
            }
        }
        fwrite($handle, "(" . implode(", ", $values) . ")");
        $first = false;
    }
    fwrite($handle, ";\n\n");
}

fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;");
fclose($handle);
echo "Export Success: matadata_export.sql created with all tables.\n";
