<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

return function (\Slim\Routing\RouteCollectorProxy $group, PDO $pdo) {
    $group->get('/shopping', function (Request $request, Response $response) use ($pdo) {
        return Twig::fromRequest($request)->render($response, 'shopping.twig', [
            'stores' => getStores($pdo, $_SESSION['user_id']),
            'active_tab' => 'shopping'
        ]);
    });

    $group->post('/shopping/store', function (Request $request, Response $response) use ($pdo) {
        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');

        if ($name !== '') {
            $stmt = $pdo->prepare("INSERT INTO stores (name, user_id) VALUES (?, ?)");
            $stmt->execute([$name, $_SESSION['user_id']]);
        }
        return Twig::fromRequest($request)->render($response, 'partials/shopping_content.twig', [
            'stores' => getStores($pdo, $_SESSION['user_id'])
        ]);
    });

    $group->delete('/shopping/store/{id}', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['id'];
        $stmt = $pdo->prepare("SELECT id FROM stores WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        if ($store = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare("DELETE FROM stores WHERE id = ?")->execute([$id]);
        }
        return $response->withHeader('HX-Redirect', '/shopping')->withStatus(302);
    });

    $group->post('/shopping/store/{id}/edit', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['id'];
        $stmt = $pdo->prepare("SELECT id FROM stores WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        if ($store = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data = $request->getParsedBody();
            $name = trim($data['name'] ?? '');
            $pdo->prepare("UPDATE stores SET name = ? WHERE id = ?")->execute([$name, $id]);
        }
        return $response->withHeader('Location', '/shopping/' . $id)->withStatus(302);
    });

    $group->get('/shopping/{id}', function (Request $request, Response $response, $args) use ($pdo) {
        $store_id = (int) $args['id'];
        $stmt = $pdo->prepare("SELECT * FROM stores WHERE id = ? AND user_id = ?");
        $stmt->execute([$store_id, $_SESSION['user_id']]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$store)
            return $response->withHeader('Location', '/shopping')->withStatus(302);

        $stmt2 = $pdo->prepare("SELECT * FROM shopping_items WHERE store_id = ? ORDER BY is_completed ASC, id DESC");
        $stmt2->execute([$store_id]);
        return Twig::fromRequest($request)->render($response, 'shopping_view.twig', [
            'store' => $store,
            'items' => $stmt2->fetchAll(PDO::FETCH_ASSOC),
            'active_tab' => 'shopping',
            'is_detail_view' => true
        ]);
    });

    $group->get('/shopping/{id}/edit', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['id'];
        $stmt = $pdo->prepare("SELECT * FROM stores WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$store)
            return $response->withHeader('Location', '/shopping')->withStatus(302);

        return Twig::fromRequest($request)->render($response, 'shopping_edit.twig', [
            'store' => $store,
            'active_tab' => 'shopping',
            'is_detail_view' => true
        ]);
    });

    $group->post('/shopping/{store_id}/item', function (Request $request, Response $response, $args) use ($pdo) {
        $store_id = (int) $args['store_id'];
        $check = $pdo->prepare("SELECT id FROM stores WHERE id = ? AND user_id = ?");
        $check->execute([$store_id, $_SESSION['user_id']]);
        if ($check->fetch()) {
            $name = trim($request->getParsedBody()['name'] ?? '');
            if ($name !== '') {
                $pdo->prepare("INSERT INTO shopping_items (store_id, name) VALUES (?, ?)")->execute([$store_id, $name]);
            }
        }
        $stmt2 = $pdo->prepare("SELECT * FROM shopping_items WHERE store_id = ? ORDER BY is_completed ASC, id DESC");
        $stmt2->execute([$store_id]);
        return Twig::fromRequest($request)->render($response, 'partials/shopping_items.twig', ['items' => $stmt2->fetchAll(PDO::FETCH_ASSOC), 'store' => ['id' => $store_id]]);
    });

    $group->delete('/shopping/item/{id}', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['id'];
        $stmt = $pdo->prepare("SELECT si.image, si.store_id FROM shopping_items si JOIN stores s ON si.store_id = s.id WHERE si.id = ? AND s.user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        if ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare("DELETE FROM shopping_items WHERE id = ?")->execute([$id]);
            $store_id = $item['store_id'];
            $stmt3 = $pdo->prepare("SELECT * FROM shopping_items WHERE store_id = ? ORDER BY is_completed ASC, id DESC");
            $stmt3->execute([$store_id]);
            return Twig::fromRequest($request)->render($response, 'partials/shopping_items.twig', ['items' => $stmt3->fetchAll(PDO::FETCH_ASSOC), 'store' => ['id' => $store_id]]);
        }
        return $response->withStatus(404);
    });

    $group->get('/shopping/item/{id}/edit', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['id'];
        $stmt = $pdo->prepare("SELECT si.*, s.user_id FROM shopping_items si JOIN stores s ON si.store_id = s.id WHERE si.id = ? AND s.user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item)
            return $response->withHeader('Location', '/shopping')->withStatus(302);

        return Twig::fromRequest($request)->render($response, 'shopping_item_edit.twig', [
            'item' => $item,
            'active_tab' => 'shopping',
            'is_detail_view' => true
        ]);
    });

    $group->post('/shopping/item/{id}/edit', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['id'];
        $stmt = $pdo->prepare("SELECT si.image, si.store_id FROM shopping_items si JOIN stores s ON si.store_id = s.id WHERE si.id = ? AND s.user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        if ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data = $request->getParsedBody();
            $name = trim($data['name'] ?? '');

            $pdo->prepare("UPDATE shopping_items SET name = ? WHERE id = ?")->execute([$name, $id]);
            return $response->withHeader('Location', '/shopping/' . $item['store_id'])->withStatus(302);
        }
        return $response->withStatus(404);
    });

    $group->patch('/shopping/item/{id}/toggle', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['id'];
        $stmt = $pdo->prepare("SELECT si.is_completed, si.store_id FROM shopping_items si JOIN stores s ON si.store_id = s.id WHERE si.id = ? AND s.user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        if ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare("UPDATE shopping_items SET is_completed = ? WHERE id = ?")->execute([$item['is_completed'] ? 0 : 1, $id]);
            $store_id = $item['store_id'];
            $stmt3 = $pdo->prepare("SELECT * FROM shopping_items WHERE store_id = ? ORDER BY is_completed ASC, id DESC");
            $stmt3->execute([$store_id]);
            return Twig::fromRequest($request)->render($response, 'partials/shopping_items.twig', ['items' => $stmt3->fetchAll(PDO::FETCH_ASSOC), 'store' => ['id' => $store_id]]);
        }
        return $response->withStatus(404);
    });
};
