<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

return function (\Slim\Routing\RouteCollectorProxy $group, \MongoDB\Database $db) {
    function getShoppingItems(\MongoDB\Database $db, string $storeId) {
        $items = [];
        foreach ($db->shopping_items->find(['store_id' => $storeId]) as $item) {
            $item_arr = (array)$item;
            $item_arr['id'] = (string)$item['_id'];
            $items[] = $item_arr;
        }
        usort($items, function($a, $b) {
            if (($a['is_completed'] ?? 0) !== ($b['is_completed'] ?? 0)) {
                return ($a['is_completed'] ?? 0) <=> ($b['is_completed'] ?? 0);
            }
            return strcmp($b['id'], $a['id']);
        });
        return $items;
    }

    $group->get('/shopping', function (Request $request, Response $response) use ($db) {
        return Twig::fromRequest($request)->render($response, 'shopping.twig', [
            'stores' => getStores($db, $_SESSION['user_id']),
            'active_tab' => 'shopping'
        ]);
    });

    $group->post('/shopping/store', function (Request $request, Response $response) use ($db) {
        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');

        if ($name !== '') {
            $db->stores->insertOne(['name' => $name, 'user_id' => $_SESSION['user_id']]);
        }
        return Twig::fromRequest($request)->render($response, 'partials/shopping_content.twig', [
            'stores' => getStores($db, $_SESSION['user_id'])
        ]);
    });

    $group->delete('/shopping/store/{id}', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['id'];
        $store = $db->stores->findOne(['_id' => new \MongoDB\BSON\ObjectId($id), 'user_id' => $_SESSION['user_id']]);
        if ($store) {
            $db->stores->deleteOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
            $db->shopping_items->deleteMany(['store_id' => $id]);
        }
        return $response->withHeader('HX-Redirect', '/shopping')->withStatus(302);
    });

    $group->post('/shopping/store/{id}/edit', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['id'];
        $store = $db->stores->findOne(['_id' => new \MongoDB\BSON\ObjectId($id), 'user_id' => $_SESSION['user_id']]);
        if ($store) {
            $data = $request->getParsedBody();
            $name = trim($data['name'] ?? '');
            $db->stores->updateOne(['_id' => new \MongoDB\BSON\ObjectId($id)], ['$set' => ['name' => $name]]);
        }
        return $response->withHeader('Location', '/shopping/' . $id)->withStatus(302);
    });

    $group->get('/shopping/{id}', function (Request $request, Response $response, $args) use ($db) {
        $store_id = $args['id'];
        $store = $db->stores->findOne(['_id' => new \MongoDB\BSON\ObjectId($store_id), 'user_id' => $_SESSION['user_id']]);
        if (!$store)
            return $response->withHeader('Location', '/shopping')->withStatus(302);

        $store_arr = (array)$store;
        $store_arr['id'] = (string)$store['_id'];

        return Twig::fromRequest($request)->render($response, 'shopping_view.twig', [
            'store' => $store_arr,
            'items' => getShoppingItems($db, $store_id),
            'active_tab' => 'shopping',
            'is_detail_view' => true
        ]);
    });

    $group->get('/shopping/{id}/edit', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['id'];
        $store = $db->stores->findOne(['_id' => new \MongoDB\BSON\ObjectId($id), 'user_id' => $_SESSION['user_id']]);
        if (!$store)
            return $response->withHeader('Location', '/shopping')->withStatus(302);

        $store_arr = (array)$store;
        $store_arr['id'] = (string)$store['_id'];

        return Twig::fromRequest($request)->render($response, 'shopping_edit.twig', [
            'store' => $store_arr,
            'active_tab' => 'shopping',
            'is_detail_view' => true
        ]);
    });

    $group->post('/shopping/{store_id}/item', function (Request $request, Response $response, $args) use ($db) {
        $store_id = $args['store_id'];
        $store = $db->stores->findOne(['_id' => new \MongoDB\BSON\ObjectId($store_id), 'user_id' => $_SESSION['user_id']]);
        if ($store) {
            $name = trim($request->getParsedBody()['name'] ?? '');
            if ($name !== '') {
                $db->shopping_items->insertOne(['store_id' => $store_id, 'name' => $name, 'is_completed' => 0]);
            }
        }
        return Twig::fromRequest($request)->render($response, 'partials/shopping_items.twig', [
            'items' => getShoppingItems($db, $store_id), 
            'store' => ['id' => $store_id]
        ]);
    });

    $group->delete('/shopping/item/{id}', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['id'];
        $item = $db->shopping_items->findOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
        if ($item) {
            $store_id = $item['store_id'];
            $store = $db->stores->findOne(['_id' => new \MongoDB\BSON\ObjectId($store_id), 'user_id' => $_SESSION['user_id']]);
            if ($store) {
                $db->shopping_items->deleteOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
                return Twig::fromRequest($request)->render($response, 'partials/shopping_items.twig', [
                    'items' => getShoppingItems($db, $store_id), 
                    'store' => ['id' => $store_id]
                ]);
            }
        }
        return $response->withStatus(404);
    });

    $group->get('/shopping/item/{id}/edit', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['id'];
        $item = $db->shopping_items->findOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
        if ($item) {
            $store = $db->stores->findOne(['_id' => new \MongoDB\BSON\ObjectId($item['store_id']), 'user_id' => $_SESSION['user_id']]);
            if ($store) {
                $item_arr = (array)$item;
                $item_arr['id'] = (string)$item['_id'];
                $item_arr['user_id'] = $_SESSION['user_id'];
                
                return Twig::fromRequest($request)->render($response, 'shopping_item_edit.twig', [
                    'item' => $item_arr,
                    'active_tab' => 'shopping',
                    'is_detail_view' => true
                ]);
            }
        }
        return $response->withHeader('Location', '/shopping')->withStatus(302);
    });

    $group->post('/shopping/item/{id}/edit', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['id'];
        $item = $db->shopping_items->findOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
        if ($item) {
            $store_id = $item['store_id'];
            $store = $db->stores->findOne(['_id' => new \MongoDB\BSON\ObjectId($store_id), 'user_id' => $_SESSION['user_id']]);
            if ($store) {
                $data = $request->getParsedBody();
                $name = trim($data['name'] ?? '');

                $db->shopping_items->updateOne(['_id' => new \MongoDB\BSON\ObjectId($id)], ['$set' => ['name' => $name]]);
                return $response->withHeader('Location', '/shopping/' . $store_id)->withStatus(302);
            }
        }
        return $response->withStatus(404);
    });

    $group->patch('/shopping/item/{id}/toggle', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['id'];
        $item = $db->shopping_items->findOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
        if ($item) {
            $store_id = $item['store_id'];
            $store = $db->stores->findOne(['_id' => new \MongoDB\BSON\ObjectId($store_id), 'user_id' => $_SESSION['user_id']]);
            if ($store) {
                $newStatus = ($item['is_completed'] ?? 0) ? 0 : 1;
                $db->shopping_items->updateOne(['_id' => new \MongoDB\BSON\ObjectId($id)], ['$set' => ['is_completed' => $newStatus]]);
                
                return Twig::fromRequest($request)->render($response, 'partials/shopping_items.twig', [
                    'items' => getShoppingItems($db, $store_id), 
                    'store' => ['id' => $store_id]
                ]);
            }
        }
        return $response->withStatus(404);
    });
};
