<?php

use DI\Container;
use Nexmo\Client;
use Dotenv\Dotenv;
use Slim\Views\Twig;
use Slim\Flash\Messages;
use Slim\Factory\AppFactory;
use Slim\Views\TwigMiddleware;
use Knlv\Slim\Views\TwigMessages;
use Nexmo\Client\Credentials\Basic;
use NexmoPHPSkeleton\Middleware\Session;
use NexmoPHPSkeleton\Middleware\UserLoader;
use Pantry\Action\Admin\AssignDeliveryAction;
use Pantry\Action\Admin\IndexAction;
use Pantry\Action\HomepageAction;
use Pantry\Action\LoginAction;
use Pantry\Action\LogoutAction;
use Pantry\Action\RegisterAction;
use Pantry\Action\User\VerifyAction;
use Pantry\Action\User\VerifyStartAction;
use Pantry\Action\User\VerifyConfirmAction;
use Pantry\Action\Webhook\GeneralAction;
use Slim\Routing\RouteCollectorProxy;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$container = new Container();
$container->set('flash', function() {
    session_start();
    return new Messages();
});
$container->set('view', function() use ($container) {
    $twig = Twig::create(__DIR__ . '/../views');
    $twig->addExtension(new TwigMessages($container->get('flash')));
    return $twig;
});
$container->set('db', function() use ($container) {
    $pdo = new \PDO(getenv('DB.DSN'));
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    return $pdo;
});
$container->set('nexmo', function() use ($container) {
    $nexmo = new Client(
        new Basic(getenv('NEXMO_API_KEY'), getenv('NEXMO_API_SECRET')),
        ['app' => ['name' => 'php-skeleton-app', 'version' => '1.0.0']]
    );

    return $nexmo;
});
AppFactory::setContainer($container);

// Instantiate App
$app = AppFactory::create();

// Add middleware
$app->addErrorMiddleware(true, true, true);
$app->add(new Session());
$app->add(new UserLoader($app->getContainer()->get('db')));
$app->add(TwigMiddleware::createFromContainer($app));

// Add routes
$app->get('/', HomepageAction::class)->setName('homepage');
$app->map(['GET', 'POST'], '/login', LoginAction::class)->setName('login');
$app->get('/logout', LogoutAction::class)->setName('logout');
$app->map(['GET', 'POST'], '/register', RegisterAction::class)->setName('register-user');
$app->map(['GET', 'POST'], '/webhooks/general', GeneralAction::class)->setName('webhooks-general');

$app->group('/admin', function (RouteCollectorProxy $group) {
    $group->get('/', IndexAction::class)->setName('admin-index');
    $group->get('/delivery/{id}', AssignDeliveryAction::class)->setName('admin-assign-delivery');
});

$app->group('/user', function (RouteCollectorProxy $group) {
    $group->map(['GET', 'POST'], '/verify', VerifyAction::class)->setName('user-verify');
    $group->get('/verify/start', VerifyStartAction::class)->setName('user->verify->start');
    $group->get('/verify/confirm', VerifyConfirmAction::class)->setName('user-verify-confirm');
});

$app->run();
