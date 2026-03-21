<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

return function ($app, \Closure $adminMiddleware, \MongoDB\Database $db) {
    function mapUsers(\MongoDB\Database $db) {
        $users = [];
        foreach ($db->users->find([], ['sort' => ['_id' => 1]]) as $u) {
            $u['id'] = (string)$u['_id'];
            $users[] = $u;
        }
        return $users;
    }

    $app->group('/admin', function (\Slim\Routing\RouteCollectorProxy $group) use ($db) {
        $group->get('', function (Request $request, Response $response) use ($db) {
            return Twig::fromRequest($request)->render($response, 'admin/dashboard.twig', ['users' => mapUsers($db), 'active_tab' => 'admin']);
        });

        $group->patch('/user/{id}/toggle-status', function (Request $request, Response $response, $args) use ($db) {
            $id = $args['id'];
            if ($id !== $_SESSION['user_id']) {
                $u = $db->users->findOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
                if ($u) {
                    $newStatus = ($u['is_active'] ?? 1) ? 0 : 1;
                    $db->users->updateOne(['_id' => new \MongoDB\BSON\ObjectId($id)], ['$set' => ['is_active' => $newStatus]]);
                }
            }
            return Twig::fromRequest($request)->render($response, 'admin/partials/users_list.twig', ['users' => mapUsers($db)]);
        });

        $group->delete('/user/{id}', function (Request $request, Response $response, $args) use ($db) {
            $id = $args['id'];
            if ($id !== $_SESSION['user_id']) {
                $db->users->deleteOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
            }
            return Twig::fromRequest($request)->render($response, 'admin/partials/users_list.twig', ['users' => mapUsers($db)]);
        });
    })->add($adminMiddleware);
};
