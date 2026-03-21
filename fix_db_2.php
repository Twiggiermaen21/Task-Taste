<?php
$dbPath = __DIR__ . '/data/database.sqlite';
if (!file_exists($dbPath)) {
    die("Brak bazy.\n");
}
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->exec("ALTER TABLE shopping_items ADD COLUMN image TEXT");
    echo "Dodano 'image' do shopping_items.\n";
} catch (Exception $e) { echo "Info: " . $e->getMessage() . "\n"; }

try {
    $pdo->exec("ALTER TABLE recipes ADD COLUMN category TEXT");
    echo "Dodano 'category' do recipes.\n";
} catch (Exception $e) { echo "Info: " . $e->getMessage() . "\n"; }
echo "Gotowe.\n";
