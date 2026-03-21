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
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $grouped = [
        'Śniadanie' => [],
        'Obiad' => [],
        'Kolacja' => [],
        'Deser' => [],
        'Inne' => []
    ];
    
    foreach($all as $r) {
        $cat = $r['category'] ?: 'Inne';
        if (!isset($grouped[$cat])) $cat = 'Inne';
        $grouped[$cat][] = $r;
    }
    
    // Remove empty categories
    return array_filter($grouped, function($items) { return count($items) > 0; });
}

function getTaskGroups(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT * FROM task_groups WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function __($key) {
    if (!isset($_SESSION['language'])) {
        $_SESSION['language'] = 'pl';
    }
    $lang = $_SESSION['language'];
    $translations = [
        'pl' => [
            'dashboard' => 'Pulpit',
            'shopping_list' => 'Lista Zakupów',
            'recipes' => 'Przepisy',
            'tasks' => 'Zadania',
            'settings' => 'Ustawienia',
            'add' => 'Dodaj',
            'edit' => 'Edytuj',
            'delete' => 'Usuń',
            'logout' => 'Wyloguj',
            'my_stats' => 'Moje Statystyki',
            'urgent_tasks' => 'Najpilniejsze Zadania',
            'welcome' => 'Witaj',
            'what_to_buy' => 'Co kupujesz?',
            'new_discount' => 'Nowy dyskont...',
            'your_market' => 'Twój Rynek',
            'save_changes' => 'Zapisz Zmiany',
            'open_lists' => 'Masz %d otwarte listy',
            'items' => 'Przedmiotów',
            'pending_gaps' => 'Zaległe braki',
            'my_account' => 'Moje Konto',
            'preferences' => 'Preferencje',
            'theme_color' => 'Kolor przewodni (Akcent)',
            'app_lang' => 'Język aplikacji',
            'profile_icon' => 'Ikona profilu',
            'choose_emoji' => 'Wybierz emoji:',
            'upload_own' => 'Lub wgraj własne zdjęcie:',
            'security_zone' => 'Strefa Bezpieczeństwa',
            'logout_desc' => 'Wylogowanie spowoduje zakończenie bieżącej sesji na tym urządzeniu.',
            'back' => 'Wstecz',
            'your_recipes' => 'Twoje Przepisy',
            'recipes_desc' => 'Smaczne pomysły na wyciągnięcie ręki.',
            'recipe_title_placeholder' => 'Tytuł dania np. Spaghetti...',
            'instructions_placeholder' => 'Krótki opis lub instrukcja (opcjonalnie)...',
            'save_to_db' => 'Zapisz do Bazy',
            'your_market_desc' => 'Zarządzaj swoimi listami zakupowymi.',
            'new_store_placeholder' => 'Nowy dyskont np. Lidl...',
            'no_recipes' => 'Brak przepisów',
            'no_recipes_desc' => 'Książka jest pusta. Pora coś ugotować!',
            'catalog' => 'Katalog',
            'go_inside' => 'Wejdź wgłąb',
            'no_stores' => 'Brak sklepów',
            'add_first_store' => 'Dodaj swój pierwszy sklep używając paska wyżej.',
            'no_tasks' => 'Masz czyste konto!',
            'no_tasks_desc' => 'Żadnych zaległości.',
            'appearance_mode' => 'Tryb Wyświetlania',
            'light_mode' => 'Jasny',
            'dark_mode' => 'Ciemny',
            'login' => 'Logowanie',
            'register' => 'Rejestracja',
            'email' => 'Adres E-mail',
            'password' => 'Hasło',
            'no_account' => 'Nie masz konta?',
            'have_account' => 'Masz już konto?',
            'sign_in' => 'Zaloguj się',
            'sign_up' => 'Zarejestruj się',
            'username' => 'Nazwa użytkownika',
            'confirm_password' => 'Potwierdź Hasło',
            'to_complete' => 'Do skompletowania',
            'sign_in_desc' => 'Zaloguj się do swojego konta',
            'register_desc' => 'Utwórz swoje konto w aplikacji',
            'confirm_delete' => 'Czy na pewno chcesz to usunąć?',
            'empty_basket' => 'Pusty koszyk',
            'no_items_buy' => 'Brak elementów do kupienia.',
            'my_account_title' => 'Moje Konto',
            'preferences_title' => 'Preferencje',
            'theme_color_label' => 'Kolor przewodni (Akcent)',
            'edit_product' => 'Edytuj Produkt',
            'modify_item' => 'Modyfikuj Przedmiot',
            'product_name' => 'Nazwa Produktu',
            'product_image' => 'Zdjęcie Produktu',
            'confirm_changes' => 'Zatwierdź Zmiany',
            'edit_recipe' => 'Edytuj Przepis',
            'modify_recipe' => 'Modyfikuj Przepis',
            'recipe_title_label' => 'Tytuł potrawy',
            'instructions_label' => 'Sposób przygotowania',
            'meal_type' => 'Rodzaj Posiłku',
            'recipe_image_label' => 'Zdjęcie Dania',
            'update_recipe' => 'Aktualizuj Przepis',
            'edit_task' => 'Edytuj Zadanie',
            'modify_task' => 'Modyfikuj Zadanie',
            'task_title_label' => 'Tytuł Zlecenia',
            'details_label' => 'Szczegóły / Opis',
            'due_date_label' => 'Termin',
            'priority_label' => 'Priorytet (kolor)',
            'attachment_label' => 'Załącznik Graficzny',
            'update_btn' => 'Aktualizuj',
            'edit_store' => 'Edytuj Sklep',
            'modify_store' => 'Modyfikuj Sklep',
            'store_name' => 'Nazwa Sklepu',
            'update_store' => 'Zaktualizuj Skrót'
        ],
        'en' => [
            'dashboard' => 'Dashboard',
            'shopping_list' => 'Shopping List',
            'recipes' => 'Recipes',
            'tasks' => 'Tasks',
            'settings' => 'Settings',
            'add' => 'Add',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'logout' => 'Log Out',
            'my_stats' => 'My Stats',
            'urgent_tasks' => 'Urgent Tasks',
            'welcome' => 'Welcome',
            'what_to_buy' => 'What to buy?',
            'new_discount' => 'New store...',
            'your_market' => 'Your Market',
            'save_changes' => 'Save Changes',
            'open_lists' => 'You have %d open lists',
            'items' => 'Items',
            'pending_gaps' => 'Pending Gaps',
            'my_account' => 'My Account',
            'preferences' => 'Preferences',
            'theme_color' => 'Theme Color (Accent)',
            'app_lang' => 'App Language',
            'profile_icon' => 'Profile Icon',
            'choose_emoji' => 'Choose emoji:',
            'upload_own' => 'Or upload your own picture:',
            'security_zone' => 'Security Zone',
            'logout_desc' => 'Logging out will end your current session on this device.',
            'back' => 'Back',
            'your_recipes' => 'Your Recipes',
            'recipes_desc' => 'Tasty ideas at your fingertips.',
            'recipe_title_placeholder' => 'Dish title e.g. Spaghetti...',
            'instructions_placeholder' => 'Short description or instructions (optional)...',
            'save_to_db' => 'Save to Database',
            'your_market_desc' => 'Manage your shopping lists.',
            'new_store_placeholder' => 'New store e.g. Lidl...',
            'no_recipes' => 'No recipes',
            'no_recipes_desc' => 'The book is empty. Time to cook something!',
            'catalog' => 'Catalog',
            'go_inside' => 'Go inside',
            'no_stores' => 'No stores',
            'add_first_store' => 'Add your first store using the bar above.',
            'no_tasks' => 'You are all caught up!',
            'no_tasks_desc' => 'No pending tasks.',
            'appearance_mode' => 'Appearance Mode',
            'light_mode' => 'Light',
            'dark_mode' => 'Dark',
            'login' => 'Login',
            'register' => 'Register',
            'email' => 'Email Address',
            'password' => 'Password',
            'no_account' => "Don't have an account?",
            'have_account' => 'Already have an account?',
            'sign_in' => 'Sign In',
            'sign_up' => 'Sign Up',
            'username' => 'Username',
            'confirm_password' => 'Confirm Password'
        ]
    ];
    return $translations[$lang][$key] ?? $key;
}
