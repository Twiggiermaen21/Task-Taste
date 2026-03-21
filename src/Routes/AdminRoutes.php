<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

return function ($app, \Closure $adminMiddleware, PDO $pdo) {
    $app->group('/admin', function (\Slim\Routing\RouteCollectorProxy $group) use ($pdo) {
        $group->get('', function (Request $request, Response $response) use ($pdo) {
            $users = $pdo->query("SELECT id, username, email, is_admin, is_active FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
            return Twig::fromRequest($request)->render($response, 'admin/dashboard.twig', ['users' => $users, 'active_tab' => 'admin']);
        });

        $group->patch('/user/{id}/toggle-status', function (Request $request, Response $response, $args) use ($pdo) {
            $id = (int) $args['id'];
            if ($id !== $_SESSION['user_id']) {
                $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
                $stmt->execute([$id]);
                if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$u['is_active'] ? 0 : 1, $id]);
                }
            }
            $users = $pdo->query("SELECT id, username, email, is_admin, is_active FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
            return Twig::fromRequest($request)->render($response, 'admin/partials/users_list.twig', ['users' => $users]);
        });

        $group->delete('/user/{id}', function (Request $request, Response $response, $args) use ($pdo) {
            $id = (int) $args['id'];
            if ($id !== $_SESSION['user_id']) {
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            }
            $users = $pdo->query("SELECT id, username, email, is_admin, is_active FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
            return Twig::fromRequest($request)->render($response, 'admin/partials/users_list.twig', ['users' => $users]);
        });
    })->add($adminMiddleware);
};
