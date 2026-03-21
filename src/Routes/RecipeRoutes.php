<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

return function (\Slim\Routing\RouteCollectorProxy $group, PDO $pdo) {
    $category_emojis = [
        'Śniadanie' => '🍳',
        'Obiad' => '🍝',
        'Kolacja' => '🥗',
        'Deser' => '🍰',
        'Inne' => '🍴'
    ];

    $group->get('/recipes', function (Request $request, Response $response) use ($pdo, $category_emojis) {
        return Twig::fromRequest($request)->render($response, 'recipes.twig', [
            'grouped_recipes' => getRecipes($pdo, $_SESSION['user_id']),
            'category_emojis' => $category_emojis,
            'active_tab' => 'recipes'
        ]);
    });

    $group->post('/recipes', function (Request $request, Response $response) use ($pdo, $category_emojis) {
        $data = $request->getParsedBody();
        $title = trim($data['title'] ?? '');
        $instructions = trim($data['instructions'] ?? '');
        $category = trim($data['category'] ?? 'Inne');
        $image = handleUpload($request, 'image');
        
        if ($title !== '') {
            $stmt = $pdo->prepare("INSERT INTO recipes (title, instructions, category, image, user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $instructions, $category, $image, $_SESSION['user_id']]);
        }
        return Twig::fromRequest($request)->render($response, 'partials/recipes_content.twig', [
            'grouped_recipes' => getRecipes($pdo, $_SESSION['user_id']),
            'category_emojis' => $category_emojis
        ]);
    });

    $group->delete('/recipes/{id}', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['id'];
        $stmt = $pdo->prepare("SELECT image FROM recipes WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        if ($recipe = $stmt->fetch(PDO::FETCH_ASSOC)) {
            deleteUploadedFile($recipe['image']);
            $pdo->prepare("DELETE FROM recipes WHERE id = ?")->execute([$id]);
        }
        return $response->withHeader('HX-Redirect', '/grocy/recipes')->withStatus(302);
    });
    
    $group->get('/recipes/{id}/edit', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['id'];
        $stmt = $pdo->prepare("SELECT * FROM recipes WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        $recipe = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$recipe) return $response->withHeader('Location', '/grocy/recipes')->withStatus(302);
        
        return Twig::fromRequest($request)->render($response, 'recipe_edit.twig', [
            'recipe' => $recipe,
            'active_tab' => 'recipes'
        ]);
    });
    
    $group->post('/recipes/{id}/edit', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['id'];
        $stmt = $pdo->prepare("SELECT image FROM recipes WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        if ($recipe = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data = $request->getParsedBody();
            $title = trim($data['title'] ?? '');
            $instructions = trim($data['instructions'] ?? '');
            $category = trim($data['category'] ?? 'Inne');
            $newImage = handleUpload($request, 'image');

            if ($newImage) {
                deleteUploadedFile($recipe['image']);
                $pdo->prepare("UPDATE recipes SET title = ?, instructions = ?, category = ?, image = ? WHERE id = ?")->execute([$title, $instructions, $category, $newImage, $id]);
            } else {
                $pdo->prepare("UPDATE recipes SET title = ?, instructions = ?, category = ? WHERE id = ?")->execute([$title, $instructions, $category, $id]);
            }
        }
        return $response->withHeader('Location', '/grocy/recipes/' . $id)->withStatus(302);
    });

    $group->get('/recipes/{id}', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['id'];
        $stmt = $pdo->prepare("SELECT * FROM recipes WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        $recipe = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$recipe) return $response->withHeader('Location', '/grocy/recipes')->withStatus(302);

        $stmt2 = $pdo->prepare("SELECT * FROM recipe_ingredients WHERE recipe_id = ? ORDER BY id ASC");
        $stmt2->execute([$id]);
        $ingredients = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        if ($request->hasHeader('HX-Request')) {
            return Twig::fromRequest($request)->render($response, 'partials/recipe_detail.twig', [
                'recipe' => $recipe, 'ingredients' => $ingredients
            ]);
        }

        return Twig::fromRequest($request)->render($response, 'recipe_view.twig', [
            'recipe' => $recipe, 'ingredients' => $ingredients, 'active_tab' => 'recipes'
        ]);
    });

    $group->post('/recipes/{recipe_id}/ingredient', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['recipe_id'];
        $check = $pdo->prepare("SELECT id FROM recipes WHERE id = ? AND user_id = ?");
        $check->execute([$id, $_SESSION['user_id']]);
        if ($check->fetch()) {
            $name = trim($request->getParsedBody()['name'] ?? '');
            if ($name !== '') $pdo->prepare("INSERT INTO recipe_ingredients (recipe_id, name) VALUES (?, ?)")->execute([$id, $name]);
        }
        $stmt2 = $pdo->prepare("SELECT * FROM recipe_ingredients WHERE recipe_id = ? ORDER BY id ASC");
        $stmt2->execute([$id]);
        return Twig::fromRequest($request)->render($response, 'partials/recipe_ingredients.twig', ['ingredients' => $stmt2->fetchAll(PDO::FETCH_ASSOC), 'recipe' => ['id' => $id]]);
    });
};
