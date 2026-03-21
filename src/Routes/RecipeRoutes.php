<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

return function (\Slim\Routing\RouteCollectorProxy $group, \MongoDB\Database $db) {
    $category_emojis = [
        'Śniadanie' => '🍳',
        'Obiad' => '🍝',
        'Kolacja' => '🥗',
        'Deser' => '🍰',
        'Inne' => '🍴'
    ];

    $group->get('/recipes', function (Request $request, Response $response) use ($db, $category_emojis) {
        return Twig::fromRequest($request)->render($response, 'recipes.twig', [
            'grouped_recipes' => getRecipes($db, $_SESSION['user_id']),
            'category_emojis' => $category_emojis,
            'active_tab' => 'recipes'
        ]);
    });

    $group->post('/recipes', function (Request $request, Response $response) use ($db, $category_emojis) {
        $data = $request->getParsedBody();
        $title = trim($data['title'] ?? '');
        $instructions = trim($data['instructions'] ?? '');
        $category = trim($data['category'] ?? 'Inne');
        if ($title !== '') {
            $db->recipes->insertOne([
                'title' => $title,
                'instructions' => $instructions,
                'category' => $category,
                'user_id' => $_SESSION['user_id'],
                'ingredients' => []
            ]);
        }
        return Twig::fromRequest($request)->render($response, 'partials/recipes_content.twig', [
            'grouped_recipes' => getRecipes($db, $_SESSION['user_id']),
            'category_emojis' => $category_emojis
        ]);
    });

    $group->delete('/recipes/{id}', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['id'];
        $recipe = $db->recipes->findOne(['_id' => new \MongoDB\BSON\ObjectId($id), 'user_id' => $_SESSION['user_id']]);
        if ($recipe) {
            $db->recipes->deleteOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
        }
        return $response->withHeader('HX-Redirect', '/recipes')->withStatus(302);
    });

    $group->get('/recipes/{id}/edit', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['id'];
        $recipe = $db->recipes->findOne(['_id' => new \MongoDB\BSON\ObjectId($id), 'user_id' => $_SESSION['user_id']]);
        if (!$recipe)
            return $response->withHeader('Location', '/recipes')->withStatus(302);

        $recipe['id'] = (string)$recipe['_id'];

        return Twig::fromRequest($request)->render($response, 'recipe_edit.twig', [
            'recipe' => $recipe,
            'active_tab' => 'recipes',
            'is_detail_view' => true
        ]);
    });

    $group->post('/recipes/{id}/edit', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['id'];
        $recipe = $db->recipes->findOne(['_id' => new \MongoDB\BSON\ObjectId($id), 'user_id' => $_SESSION['user_id']]);
        if ($recipe) {
            $data = $request->getParsedBody();
            $title = trim($data['title'] ?? '');
            $instructions = trim($data['instructions'] ?? '');
            $category = trim($data['category'] ?? 'Inne');

            $db->recipes->updateOne(
                ['_id' => new \MongoDB\BSON\ObjectId($id)],
                ['$set' => [
                    'title' => $title,
                    'instructions' => $instructions,
                    'category' => $category
                ]]
            );
        }
        return $response->withHeader('Location', '/recipes/' . $id)->withStatus(302);
    });

    $group->get('/recipes/{id}', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['id'];
        $recipe = $db->recipes->findOne(['_id' => new \MongoDB\BSON\ObjectId($id), 'user_id' => $_SESSION['user_id']]);
        if (!$recipe)
            return $response->withHeader('Location', '/recipes')->withStatus(302);

        $recipe['id'] = (string)$recipe['_id'];
        $ingredients = $recipe['ingredients'] ?? [];

        if ($request->hasHeader('HX-Request')) {
            return Twig::fromRequest($request)->render($response, 'partials/recipe_detail.twig', [
                'recipe' => $recipe,
                'ingredients' => $ingredients
            ]);
        }

        return Twig::fromRequest($request)->render($response, 'recipe_view.twig', [
            'recipe' => $recipe,
            'ingredients' => $ingredients,
            'active_tab' => 'recipes',
            'is_detail_view' => true
        ]);
    });

    $group->post('/recipes/{recipe_id}/ingredient', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['recipe_id'];
        $check = $db->recipes->findOne(['_id' => new \MongoDB\BSON\ObjectId($id), 'user_id' => $_SESSION['user_id']]);
        if ($check) {
            $name = trim($request->getParsedBody()['name'] ?? '');
            if ($name !== '') {
                $db->recipes->updateOne(
                    ['_id' => new \MongoDB\BSON\ObjectId($id)],
                    ['$push' => ['ingredients' => ['id' => uniqid(), 'name' => $name, 'recipe_id' => $id]]]
                );
            }
        }
        
        $recipe = $db->recipes->findOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
        $ingredients = $recipe['ingredients'] ?? [];

        return Twig::fromRequest($request)->render($response, 'partials/recipe_ingredients.twig', [
            'ingredients' => $ingredients, 
            'recipe' => ['id' => $id]
        ]);
    });
};
