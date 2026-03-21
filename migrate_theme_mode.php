<?php
$db = new PDO('sqlite:data/database.sqlite');
try {
    $db->exec("ALTER TABLE users ADD COLUMN theme_mode TEXT DEFAULT 'light'");
    echo "Column 'theme_mode' added successfully.\n";
} catch (Exception $e) {
    echo "Error or column already exists: " . $e->getMessage() . "\n";
}
