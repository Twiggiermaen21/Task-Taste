<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

return function (\Slim\Routing\RouteCollectorProxy $group, \MongoDB\Database $db) {
    $group->get('/settings', function (Request $request, Response $response) use ($db) {
        $user = $db->users->findOne(['_id' => new \MongoDB\BSON\ObjectId($_SESSION['user_id'])]);

        return Twig::fromRequest($request)->render($response, 'settings.twig', [
            'user_pref' => $user,
            'active_tab' => 'settings',
            'is_detail_view' => true
        ]);
    });

    $group->post('/settings/update', function (Request $request, Response $response) use ($db) {
        $data = $request->getParsedBody();
        $theme = $data['theme_color'] ?? '#D4F67B';
        $lang = $data['language'] ?? 'pl';
        $mode = $data['theme_mode'] ?? 'light';
        $avatarEmoji = $data['avatar'] ?? '👤';

        $db->users->updateOne(
            ['_id' => new \MongoDB\BSON\ObjectId($_SESSION['user_id'])],
            ['$set' => [
                'theme_color' => $theme,
                'language' => $lang,
                'theme_mode' => $mode,
                'avatar' => $avatarEmoji
            ]]
        );

        $_SESSION['theme_color'] = $theme;
        $_SESSION['language'] = $lang;
        $_SESSION['theme_mode'] = $mode;
        $_SESSION['avatar'] = $avatarEmoji;

        return $response->withHeader('Location', '/settings')->withStatus(302);
    });
};
