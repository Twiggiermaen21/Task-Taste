<?php
$pdo = new PDO('sqlite:data/database.sqlite');
$res = [];
foreach(['recipes', 'tasks'] as $t) {
    $stmt = $pdo->query("PRAGMA table_info($t)");
    $res[$t] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
echo json_encode($res, JSON_PRETTY_PRINT);
