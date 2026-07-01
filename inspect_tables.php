<?php
require 'C:\xampp\htdocs\crime-reporting-system\db.php';
try {
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_NUM);
    foreach ($tables as $table) {
        echo $table[0] . "\n";
    }
} catch (PDOException $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
?>
