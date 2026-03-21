<?php

use Psr\Http\Message\ServerRequestInterface as Request;

function getDashboardData(\MongoDB\Database $db, string $userId): array
{
    $totalRecipes = $db->recipes->countDocuments(['user_id' => $userId]);

    $recentRecipesCursor = $db->recipes->find(['user_id' => $userId], ['sort' => ['_id' => -1], 'limit' => 5]);
    $recentRecipes = [];
    foreach ($recentRecipesCursor as $r) {
        $recentRecipes[] = ['id' => (string)$r['_id'], 'title' => $r['title']];
    }

    $stores = $db->stores->find(['user_id' => $userId]);
    $shoppingStats = [];
    $totalShoppingItems = 0;
    foreach ($stores as $store) {
        $storeId = (string)$store['_id'];
        $count = $db->shopping_items->countDocuments(['store_id' => $storeId, 'is_completed' => 0]);
        if ($count > 0) {
            $shoppingStats[] = ['name' => $store['name'], 'pending_count' => $count];
            $totalShoppingItems += $count;
        }
    }

    $groups = $db->task_groups->find(['user_id' => $userId])->toArray();
    $groupIds = [];
    $groupMap = [];
    foreach ($groups as $g) {
        $gstr = (string)$g['_id'];
        $groupIds[] = $gstr;
        $groupMap[$gstr] = $g['name'];
    }

    $tasks = [];
    if (!empty($groupIds)) {
        $tasks = $db->tasks->find(['group_id' => ['$in' => $groupIds], 'is_completed' => 0])->toArray();
        usort($tasks, function($a, $b) {
            $dateA = empty($a['due_date']) ? PHP_INT_MAX : strtotime($a['due_date']);
            $dateB = empty($b['due_date']) ? PHP_INT_MAX : strtotime($b['due_date']);
            return $dateA <=> $dateB;
        });
    }

    $urgentTasks = [];
    foreach (array_slice($tasks, 0, 10) as $t) {
        $urgentTasks[] = [
            'id' => (string)$t['_id'],
            'title' => $t['title'],
            'due_date' => $t['due_date'] ?? null,
            'color' => $t['color'] ?? null,
            'group_name' => $groupMap[(string)$t['group_id']] ?? '',
            'group_id' => (string)$t['group_id']
        ];
    }

    return [
        'recipes' => ['total' => $totalRecipes, 'recent' => $recentRecipes],
        'shopping' => ['total' => $totalShoppingItems, 'by_store' => $shoppingStats],
        'urgent_tasks' => $urgentTasks
    ];
}

function getStores(\MongoDB\Database $db, string $userId): array
{
    $stores = [];
    foreach ($db->stores->find(['user_id' => $userId], ['sort' => ['name' => 1]]) as $s) {
        $s['id'] = (string)$s['_id'];
        $stores[] = $s;
    }
    return $stores;
}

function getRecipes(\MongoDB\Database $db, string $userId): array
{
    $all = [];
    foreach ($db->recipes->find(['user_id' => $userId], ['sort' => ['_id' => -1]]) as $r) {
        $r['id'] = (string)$r['_id'];
        $all[] = $r;
    }

    $grouped = [
        'Śniadanie' => [],
        'Obiad' => [],
        'Kolacja' => [],
        'Deser' => [],
        'Inne' => []
    ];

    foreach ($all as $r) {
        $cat = !empty($r['category']) ? $r['category'] : 'Inne';
        if (!isset($grouped[$cat]))
            $cat = 'Inne';
        $grouped[$cat][] = $r;
    }

    // Remove empty categories
    return array_filter($grouped, function ($items) {
        return count($items) > 0;
    });
}

function getTaskGroups(\MongoDB\Database $db, string $userId): array
{
    $groups = [];
    foreach ($db->task_groups->find(['user_id' => $userId], ['sort' => ['_id' => -1]]) as $g) {
        $g['id'] = (string)$g['_id'];
        $groups[] = $g;
    }
    return $groups;
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