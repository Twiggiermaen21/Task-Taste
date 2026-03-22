<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

return function ($app, \MongoDB\Database $db) {
    $app->get('/login', function (Request $request, Response $response) {
        if (isset($_SESSION['user_id']))
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        return Twig::fromRequest($request)->render($response, 'auth/login.twig', ['active_tab' => 'login']);
    });

    $app->post('/login', function (Request $request, Response $response) use ($db) {
        $data = $request->getParsedBody();
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        $user = $db->users->findOne([
            '$or' => [
                ['email' => $email],
                ['username' => $email]
            ]
        ]);

        if ($user && password_verify($password, $user['password_hash'])) {
            if (($user['is_active'] ?? 1) == 0) {
                return Twig::fromRequest($request)->render($response, 'auth/login.twig', ['error' => 'Konto zostało zablokowane przez administratora.']);
            }
            $_SESSION['user_id'] = (string) $user['_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (bool) ($user['is_admin'] ?? false);
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }
        return Twig::fromRequest($request)->render($response, 'auth/login.twig', ['error' => 'Nieprawidłowy e-mail lub hasło.']);
    });

    $app->get('/register', function (Request $request, Response $response) {
        if (isset($_SESSION['user_id']))
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        return Twig::fromRequest($request)->render($response, 'auth/register.twig', ['active_tab' => 'register']);
    });

    $app->post('/register', function (Request $request, Response $response) use ($db) {
        $data = $request->getParsedBody();
        $username = trim($data['username'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($username && $email && $password) {
            $adminCount = $db->users->countDocuments([]);
            $isAdmin = ($adminCount == 0) ? 1 : 0;
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $existing = $db->users->findOne([
                    '$or' => [
                        ['email' => $email],
                        ['username' => $username]
                    ]
                ]);
                if ($existing) {
                    return Twig::fromRequest($request)->render($response, 'auth/register.twig', ['error' => 'Ten E-mail lub Nazwa użytkownika jest już zajęta.']);
                }

                $insertOneResult = $db->users->insertOne([
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => $hash,
                    'is_admin' => $isAdmin,
                    'is_active' => 1,
                    'theme_color' => '#D4F67B',
                    'language' => 'pl',
                    'avatar' => '👤',
                    'theme_mode' => 'light'
                ]);

                $_SESSION['user_id'] = (string) $insertOneResult->getInsertedId();
                $_SESSION['username'] = $username;
                $_SESSION['is_admin'] = $isAdmin;
                return $response->withHeader('Location', '/dashboard')->withStatus(302);
            } catch (\Exception $e) {
                return Twig::fromRequest($request)->render($response, 'auth/register.twig', ['error' => 'Wystąpił błąd podczas rejestracji.']);
            }
        }
        return Twig::fromRequest($request)->render($response, 'auth/register.twig', ['error' => 'Wypełnij wszystkie pola.']);
    });

    $app->get('/logout', function (Request $request, Response $response) {
        session_destroy();
        return $response->withHeader('Location', '/login')->withStatus(302);
    });
};
