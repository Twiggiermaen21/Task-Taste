<?php

use Psr\Http\Message\ServerRequestInterface as Request;

function handleUpload(Request $request, string $inputName): ?string {
    $uploadedFiles = $request->getUploadedFiles();
    if (isset($uploadedFiles[$inputName])) {
        $file = $uploadedFiles[$inputName];
        if ($file->getError() === UPLOAD_ERR_OK) {
            $filename = uniqid('img_') . '_' . time() . '.jpg';
            $file->moveTo(__DIR__ . '/../public/uploads/' . $filename);
            return $filename;
        }
    }
    return null;
}

function deleteUploadedFile(?string $filename) {
    if ($filename) {
        $path = __DIR__ . '/../public/uploads/' . $filename;
        if (file_exists($path)) {
            unlink($path);
        }
    }
}

function getDashboardData(PDO $pdo, int $userId): array {
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
        WHERE tg.user_id = ? AND t.is_completed = 0 AND t.due_date != '' AND t.due_date IS NOT NULL
        ORDER BY t.due_date ASC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $urgentTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'recipes' => ['total' => $totalRecipes, 'recent' => $recentRecipes],
        'shopping' => ['total' => $totalShoppingItems, 'by_store' => $shoppingStats],
        'urgent_tasks' => $urgentTasks
    ];
}

function getStores(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT * FROM stores WHERE user_id = ? ORDER BY name ASC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecipes(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT * FROM recipes WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTaskGroups(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT * FROM task_groups WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
