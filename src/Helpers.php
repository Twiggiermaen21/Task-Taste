<?php

use Psr\Http\Message\ServerRequestInterface as Request;

function getDashboardData(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM recipes WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalRecipes = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, title FROM recipes WHERE user_id = ? ORDER BY id DESC LIMIT 5");
    $stmt->execute([$userId]);
    $recentRecipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT s.name, COUNT(si.id) as pending_count 
        FROM stores s 
        LEFT JOIN shopping_items si ON s.id = si.store_id AND si.is_completed = 0
        WHERE s.user_id = ? 
        GROUP BY s.name 
        HAVING pending_count > 0
    ");
    $stmt->execute([$userId]);
    $shoppingStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalShoppingItems = array_sum(array_column($shoppingStats, 'pending_count'));

    $stmt = $pdo->prepare("
        SELECT t.id, t.title, t.due_date, t.color, tg.name as group_name, tg.id as group_id
        FROM tasks t
        JOIN task_groups tg ON t.group_id = tg.id
        WHERE tg.user_id = ? AND t.is_completed = 0
        ORDER BY 
            CASE WHEN (t.due_date IS NULL OR t.due_date = '') THEN 1 ELSE 0 END, 
            t.due_date ASC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $urgentTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'recipes' => ['total' => $totalRecipes, 'recent' => $recentRecipes],
        'shopping' => ['total' => $totalShoppingItems, 'by_store' => $shoppingStats],
        'urgent_tasks' => $urgentTasks
    ];
}

function getStores(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT * FROM stores WHERE user_id = ? ORDER BY name ASC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecipes(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT * FROM recipes WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$userId]);
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [
        'Śniadanie' => [],
        'Obiad' => [],
        'Kolacja' => [],
        'Deser' => [],
        'Inne' => []
    ];

    foreach ($all as $r) {
        $cat = $r['category'] ?: 'Inne';
        if (!isset($grouped[$cat]))
            $cat = 'Inne';
        $grouped[$cat][] = $r;
    }

    // Remove empty categories
    return array_filter($grouped, function ($items) {
        return count($items) > 0;
    });
}

function getTaskGroups(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT * FROM task_groups WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function __($key, $replace = [])
{
    // 1. Ustawienie domyślnego języka
    if (!isset($_SESSION['language'])) {
        $_SESSION['language'] = 'pl';
    }
    $lang = $_SESSION['language'];

    // 2. Zmienna statyczna (pamięta pobrane tłumaczenia, żeby nie czytać pliku 100 razy)
    static $translations = [];

    // 3. Ładowanie pliku JSON tylko wtedy, gdy jeszcze go nie załadowaliśmy
    if (empty($translations[$lang])) {
        // Dostosuj ścieżkę do swojego projektu, jeśli folder 'lang' jest gdzie indziej
        $path = __DIR__ . "/lang/{$lang}.json";

        if (file_exists($path)) {
            $translations[$lang] = json_decode(file_get_contents($path), true);
        } else {
            $translations[$lang] = []; // Zabezpieczenie, gdy plik nie istnieje
        }
    }

    // 4. Pobranie przetłumaczonego słowa lub zwrot samego klucza (jeśli brakuje tłumaczenia)
    $result = $translations[$lang][$key] ?? $key;

    // 5. Obsługa parametrów (np. "Masz %d otwarte listy")
    if (!empty($replace)) {
        // Upewniamy się, że $replace jest tablicą, nawet jeśli przekazano pojedynczą liczbę
        return vsprintf($result, (array) $replace);
    }

    return $result;
}