<?php
session_start();

use Psr\Http\Message\ResponseInterface as Response;

use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Helpers.php';

// Prepare directories
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir))
    mkdir($dataDir, 0777, true);

$uploadsDir = __DIR__ . '/uploads';
if (!is_dir($uploadsDir))
    mkdir($uploadsDir, 0777, true);
if (!is_dir($uploadsDir . '/avatars'))
    mkdir($uploadsDir . '/avatars', 0777, true);

// Database connection
$mongoUri = getenv('MONGO_URI');
$mongoClient = new MongoDB\Client($mongoUri);
$db = $mongoClient->selectDatabase(getenv('MONGO_DB_NAME'));

// Slim App
$app = AppFactory::create();

// Twig
$twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
$twig->getEnvironment()->addFunction(new \Twig\TwigFunction('__', function ($key, $replace = []) {
    return __($key, $replace);
}));
$app->add(TwigMiddleware::create($app, $twig));
$app->add($userMiddleware);
$app->addErrorMiddleware(true, true, true);

// ==================== MIDDLEWARES ====================
$userMiddleware = function (Request $request, RequestHandler $handler) use ($db, $twig) {
    if (isset($_SESSION['user_id'])) {
        $user = $db->users->findOne(['_id' => new \MongoDB\BSON\ObjectId($_SESSION['user_id'])]);
        if ($user) {
            UserContext::set($user);
            $twig->getEnvironment()->addGlobal('user', $user);
        }
    }
    return $handler->handle($request);
};

$authMiddleware = function (Request $request, RequestHandler $handler) {
    if (!isset($_SESSION['user_id'])) {
        $response = new \Slim\Psr7\Response();
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    return $handler->handle($request);
};

$adminMiddleware = function (Request $request, RequestHandler $handler) {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
        $response = new \Slim\Psr7\Response();
        return $response->withHeader('Location', '/')->withStatus(302);
    }
    return $handler->handle($request);
};

// ==================== ROUTES ====================

// Root Redirection
$app->get('/', function (Request $request, Response $response) {
    return $response->withHeader('Location', '/dashboard')->withStatus(302);
});

// Moduły Zewnętrzne i Autoryzacyjne
(require __DIR__ . '/../src/Routes/AuthRoutes.php')($app, $db);
(require __DIR__ . '/../src/Routes/AdminRoutes.php')($app, $adminMiddleware, $db);

// Moduły Chronione (Aplikacja Głównego Asystenta)
$app->group('', function (\Slim\Routing\RouteCollectorProxy $group) use ($db) {

    $group->get('/dashboard', function (Request $request, Response $response) use ($db) {
        return Twig::fromRequest($request)->render($response, 'dashboard.twig', [
            'dashboard' => getDashboardData($db, $_SESSION['user_id']),
            'active_tab' => 'dashboard'
        ]);
    });

    // Moduły domenowe wciągnięte z /src/Routes
    (require __DIR__ . '/../src/Routes/ShoppingRoutes.php')($group, $db);
    (require __DIR__ . '/../src/Routes/RecipeRoutes.php')($group, $db);
    (require __DIR__ . '/../src/Routes/TaskRoutes.php')($group, $db);
    (require __DIR__ . '/../src/Routes/SettingsRoutes.php')($group, $db);

})->add($authMiddleware);

// Run the application
$app->run();