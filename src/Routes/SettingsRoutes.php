<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

return function (\Slim\Routing\RouteCollectorProxy $group, PDO $pdo) {
    $group->get('/settings', function (Request $request, Response $response) use ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return Twig::fromRequest($request)->render($response, 'settings.twig', [
            'user_pref' => $user,
            'active_tab' => 'settings'
        ]);
    });

    $group->post('/settings/update', function (Request $request, Response $response) use ($pdo) {
        $data = $request->getParsedBody();
        $theme = $data['theme_color'] ?? '#D4F67B';
        $lang = $data['language'] ?? 'pl';
        $mode = $data['theme_mode'] ?? 'light';
        $avatarEmoji = $data['avatar'] ?? '👤';

        // Check for file upload
        $avatarFile = handleUpload($request, 'avatar_file');
        $finalAvatar = $avatarFile ?: $avatarEmoji;

        $stmt = $pdo->prepare("UPDATE users SET theme_color = ?, language = ?, theme_mode = ?, avatar = ? WHERE id = ?");
        $stmt->execute([$theme, $lang, $mode, $finalAvatar, $_SESSION['user_id']]);

        $_SESSION['theme_color'] = $theme;
        $_SESSION['language'] = $lang;
        $_SESSION['theme_mode'] = $mode;
        $_SESSION['avatar'] = $finalAvatar;

        return $response->withHeader('Location', '/grocy/settings')->withStatus(302);
    });
};
