<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

return function ($app, PDO $pdo) {
    $app->get('/login', function (Request $request, Response $response) {
        if (isset($_SESSION['user_id'])) return $response->withHeader('Location', '/grocy/dashboard')->withStatus(302);
        return Twig::fromRequest($request)->render($response, 'auth/login.twig', ['active_tab' => 'login']);
    });

    $app->post('/login', function (Request $request, Response $response) use ($pdo) {
        $data = $request->getParsedBody();
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['is_active'] == 0) {
                return Twig::fromRequest($request)->render($response, 'auth/login.twig', ['error' => 'Konto zostało zablokowane przez administratora.']);
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];
            $_SESSION['theme_color'] = $user['theme_color'];
            $_SESSION['language'] = $user['language'];
            $_SESSION['avatar'] = $user['avatar'];
            $_SESSION['theme_mode'] = $user['theme_mode'] ?? 'light';
            return $response->withHeader('Location', '/grocy/dashboard')->withStatus(302);
        }
        return Twig::fromRequest($request)->render($response, 'auth/login.twig', ['error' => 'Nieprawidłowy e-mail lub hasło.']);
    });

    $app->get('/register', function (Request $request, Response $response) {
        if (isset($_SESSION['user_id'])) return $response->withHeader('Location', '/grocy/dashboard')->withStatus(302);
        return Twig::fromRequest($request)->render($response, 'auth/register.twig', ['active_tab' => 'register']);
    });

    $app->post('/register', function (Request $request, Response $response) use ($pdo) {
        $data = $request->getParsedBody();
        $username = trim($data['username'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($username && $email && $password) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
            $stmt->execute();
            $isAdmin = ($stmt->fetchColumn() == 0) ? 1 : 0;
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, is_admin) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hash, $isAdmin]);
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['username'] = $username;
                $_SESSION['is_admin'] = $isAdmin;
                return $response->withHeader('Location', '/grocy/dashboard')->withStatus(302);
            } catch (PDOException $e) {
                return Twig::fromRequest($request)->render($response, 'auth/register.twig', ['error' => 'Ten E-mail lub Nazwa użytkownika jest już zajęta.']);
            }
        }
        return Twig::fromRequest($request)->render($response, 'auth/register.twig', ['error' => 'Wypełnij wszystkie pola.']);
    });

    $app->get('/logout', function (Request $request, Response $response) {
        session_destroy();
        return $response->withHeader('Location', '/grocy/login')->withStatus(302);
    });
};
