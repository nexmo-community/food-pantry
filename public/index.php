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
use Zend\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;

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
AppFactory::setContainer($container);

// Instantiate App
$app = AppFactory::create();

// Add middleware
$app->addErrorMiddleware(true, true, true);
$app->add(new Session());
$app->add(TwigMiddleware::createFromContainer($app));

// Add routes
$app->get('/', function (Request $request, Response $response) {
    return $this->get('view')->render($response, 'homepage.twig.html');
});

$app->get('/admin', function (Request $request, Response $response) {
    return $this->get('view')->render($response, 'admin.twig.html');
});

// THIS WILL NEED TO BECOME A GET/POST
$app->get('/new', function (Request $request, Response $response) {
    return $this->get('view')->render($response, 'new.twig.html');
});

$app->map(['GET', 'POST'], '/login', function (Request $request, Response $response) {
    if ($request->getMethod() === "POST") {
        $email = $request->getParsedBody()['email'];
        $password = $request->getParsedBody()['password'];

        /** @var \PDO $db */
        $db = $this->get('db');
        $stmt = $db->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);

        $user = $stmt->fetch();
        if ($user) {
            $this->get('flash')->addMessage('success', $password);
            return new RedirectResponse('/');
        } else {
            $this->get('flash')->addMessage('error', 'Invalid username/password');
            return new RedirectResponse('/login');
        }
    }

    return $this->get('view')->render($response, 'login.twig.html');
});

$app->map(['GET', 'POST'], '/register', function (Request $request, Response $response) {
    if ($request->getMethod() === "POST") {
        $email = $request->getParsedBody()['email'];
        $password = $request->getParsedBody()['password'];

        /** @var \PDO $db */
        $db = $this->get('db');
        $stmt = $db->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);

        $user = $stmt->fetch();
        if (!$user) {
            $stmt = $db->prepare('INSERT INTO users (uuid, email, password) VALUES (:uuid, :email, :password)');
            $stmt->execute(['uuid' => Uuid::uuid4()->toString(), 'email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT)]);

            $this->get('flash')->addMessage('success', "Registered, please log in!");
            return new RedirectResponse('/login');
        } else {
            $this->get('flash')->addMessage('error', 'Unable to register this user');
            return new RedirectResponse('/register');
        }
    }

    return $this->get('view')->render($response, 'register.twig.html');
});

$app->run();
