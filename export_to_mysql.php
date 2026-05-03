<?php
$sqlite = new SQLite3('database.sqlite');
$mysql_file = 'matadata_export.sql';

$handle = fopen($mysql_file, 'w');
fwrite($handle, "SET NAMES utf8mb4;\n");
fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

foreach (['realizations', 'villages', 'district_stats'] as $name) {
    fwrite($handle, "DROP TABLE IF EXISTS `$name`;\n");
    
    // Get column info from SQLite
    $cols_query = $sqlite->query("PRAGMA table_info($name)");
    $cols = [];
    $create_cols = [];
    while($c = $cols_query->fetchArray(SQLITE3_ASSOC)) { 
        $cols[] = "`".$c['name']."`";
        $type = "TEXT";
        if (strpos(strtolower($c['type']), 'int') !== false) $type = "INT(11)";
        elseif (strpos(strtolower($c['type']), 'float') !== false || strpos(strtolower($c['type']), 'double') !== false) $type = "DOUBLE";
        elseif (in_array($c['name'], ['kecamatan', 'nm_kecamatan', 'nm_kelurahan', 'status', 'vendor'])) $type = "VARCHAR(255)";
        
        $col_def = "`".$c['name']."` $type" . ($c['pk'] ? " NOT NULL AUTO_INCREMENT" : " DEFAULT NULL");
        $create_cols[] = $col_def;
    }
    
    $create_sql = "CREATE TABLE `$name` (\n  " . implode(",\n  ", $create_cols) . ",\n  PRIMARY KEY (`id`)";
    if ($name === 'realizations') {
        $create_sql .= ",\n  KEY `idx_kec_tahun` (`kecamatan`,`tahun`),\n  KEY `idx_tahun` (`tahun`)";
    }
    $create_sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
    fwrite($handle, $create_sql);
    
    $results = $sqlite->query("SELECT * FROM $name");
    $cols_query = $sqlite->query("PRAGMA table_info($name)");
    $cols = [];
    while($c = $cols_query->fetchArray(SQLITE3_ASSOC)) { $cols[] = "`".$c['name']."`"; }
    
    $count = 0;
    $first_in_batch = true;
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        if ($count % 500 == 0) {
            if ($count > 0) fwrite($handle, ";\n\n");
            fwrite($handle, "INSERT INTO `$name` (" . implode(", ", $cols) . ") VALUES \n");
            $first_in_batch = true;
        }

        if (!$first_in_batch) fwrite($handle, ",\n");
        
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
        $first_in_batch = false;
        $count++;
    }
    fwrite($handle, ";\n\n");
}

fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;");
fclose($handle);
echo "Export Success: matadata_export.sql created with all tables.\n";
