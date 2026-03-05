<?php
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=adminlab_polkes",
        "adminlab_api",
        "FF{D]}J1!]Pj"
    );
    echo "DB CONNECT OK";
} catch (PDOException $e) {
    echo "DB FAIL: " . $e->getMessage();
}
