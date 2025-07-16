<?php
$serverName = "192.168.100.9";
$databaseName = "BD_AD_SCE";

try {
    $pdo = new PDO("sqlsrv:Server=$serverName;Database=$databaseName");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "";
} catch (PDOException $e) {
    echo "Erreur de connexion PDO : " . $e->getMessage();
}
?>
