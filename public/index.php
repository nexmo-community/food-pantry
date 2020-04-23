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
use Nexmo\Client\Exception\Request as ExceptionRequest;
use Nexmo\Message\Message;
use Nexmo\Verify\Verification;
use NexmoPHPSkeleton\Middleware\Session;
use NexmoPHPSkeleton\Middleware\UserLoader;
use Pantry\Delivery\Delivery;
use Zend\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Diactoros\Response\JsonResponse;

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
$app->get('/', function (Request $request, Response $response) {
    return $this->get('view')->render($response, 'homepage.twig.html');
});

$app->get('/admin', function (Request $request, Response $response) {
    /** @var \PDO $db */
    $db = $this->get('db');

    $stmt = $db->prepare('SELECT * FROM orders');
    $stmt->execute();

    $orders = [];
    while (($row = $stmt->fetch()) !== false) {
        $cstmt = $db->prepare('SELECT * FROM users WHERE uuid=:uuid');
        $cstmt->execute(['uuid' => $row['users_uuid']]);
        $customer = $cstmt->fetch();

        $orders[] = [
            'order' => $row,
            'customer' => $customer
        ];
    }

    return $this->get('view')->render($response, 'admin.twig.html', ['orders' => $orders]);
});

$app->map(['GET', 'POST'], '/admin/delivery/new', function(Request $request, Response $response) {
    return $this->get('view')->render($response, 'new-delivery.twig.html');
});

$app->get('/admin/delivery/{id}', function(Request $request, Response $response, array $args) {
    $db = $this->get('db');
    $phone = $request->getQueryParams()['courier'];

    $stmt = $db->prepare('UPDATE orders SET courier_phone=:phone, status = :status WHERE id=:id');
    $stmt->execute(['id' => $args['id'], 'phone' => $phone, 'status' => Delivery::STATUS_DISPATCHED]);

    $nexmo = $this->get('nexmo');
    $nexmo->message()->send([
        'to' => $phone,
        'from' => getenv('NEXMO.PHONE'),
        'text' => 'You have a new delivery assigned to you'
    ]);

    return new EmptyResponse();
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
            $this->get('flash')->addMessage('success', 'Logged in!');
            $_SESSION['user_id'] = $user['uuid'];
            return new RedirectResponse('/');
        } else {
            $this->get('flash')->addMessage('error', 'Invalid username/password');
            return new RedirectResponse('/login');
        }
    }

    return $this->get('view')->render($response, 'login.twig.html');
});

$app->get('/logout', function (Request $request, Response $response) {
    session_destroy();
    return new RedirectResponse('/');
});

$app->map(['GET', 'POST'], '/register', function (Request $request, Response $response) {
    if ($request->getMethod() === "POST") {
        $email = $request->getParsedBody()['email'];
        $password = $request->getParsedBody()['password'];
        $phone = $request->getParsedBody()['phone'];

        /** @var \PDO $db */
        $db = $this->get('db');
        $stmt = $db->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);

        $user = $stmt->fetch();
        if (!$user) {
            /** @var Client $nexmo */
            try {
                $nexmo = $this->get('nexmo');
                $ni = $nexmo->insights()->basic($phone);
            } catch (ExceptionRequest $e) {
                $this->get('flash')->addMessage('error', "Invalid phone number, please make sure to include the country code and nothing but numbers");
                return new RedirectResponse('/register');
            }
            $stmt = $db->prepare('INSERT INTO users (uuid, email, phone, password) VALUES (:uuid, :email, :phone, :password)');
            $stmt->execute(['uuid' => Uuid::uuid4()->toString(), 'email' => $email, 'phone' => $phone, 'password' => password_hash($password, PASSWORD_DEFAULT)]);

            $this->get('flash')->addMessage('success', "Registered, please log in!");
            return new RedirectResponse('/login');
        } else {
            $this->get('flash')->addMessage('error', 'Unable to register this user');
            return new RedirectResponse('/register');
        }
    }

    return $this->get('view')->render($response, 'register.twig.html');
});

$app->map(['GET', 'POST'], '/user/verify', function(Request $request, Response $response) {
    if (!isset($_SESSION['user'])) {
        return new RedirectResponse('/login');
    }

    return $this->get('view')->render($response, 'verify.twig.html');
});

$app->get('/user/verify/start', function(Request $request, Response $response) {
    if (!isset($_SESSION['user'])) {
        return new RedirectResponse('/login');
    }

    $user = $_SESSION['user'];
    /** @var Client $nexmo */
    $nexmo = $this->get('nexmo');
    $verify = $nexmo->verify()->start(new Verification($user['phone'], 'Pantry Test'));
    $_SESSION['verification_id'] = $verify->getRequestId();

    return new EmptyResponse();
});

$app->get('/user/verify/confirm', function(Request $request, Response $response) {
    if (!isset($_SESSION['user'])) {
        return new RedirectResponse('/login');
    }

    $user = $_SESSION['user'];
    $verificationId = $_SESSION['verification_id'];
    /** @var Client $nexmo */
    $nexmo = $this->get('nexmo');
    $nexmo->verify()->check(new Verification($verificationId), $request->getQueryParams()['pin']);

    $db = $this->get('db');
    $stmt = $db->prepare('UPDATE users SET verified = 1 WHERE uuid = :uuid');
    $stmt->execute(['uuid' => $user['uuid']]);
    unset($_SESSION['verification_id']);

    return new EmptyResponse();
});

$app->map(['GET', 'POST'], '/webhooks/general', function(Request $request, Response $response) {
    $params = $request->getParsedBody();

    if (!$params || !count($params)) {
        $params = $request->getQueryParams();
    }

    if (strtolower($params['text']) === "new delivery") {
        $db = $this->get('db');
        $nexmo = $this->get('nexmo');

        $stmt = $db->prepare('SELECT * FROM users WHERE phone = :phone');
        $stmt->execute(['phone' => $params['msisdn']]);
        $user = $stmt->fetch();
    
        if ($user && $user['verified'] == 1) {
            $stmt = $db->prepare("INSERT INTO orders (users_uuid) VALUES (:uuid)");
            $stmt->execute(['uuid' => $user['uuid']]);
    
            /** @var Client $nexmo */
            $nexmo->message()->send([
                'to' => $params['msisdn'],
                'from' => getenv('NEXMO.PHONE'),
                'text' => 'Your order has been placed!'
            ]);
        } else {
            /** @var Client $nexmo */
            $nexmo->message()->send([
                'to' => $params['msisdn'],
                'from' => getenv('NEXMO.PHONE'),
                'text' => 'Unable to handle your request, please contact support'
            ]);
        }
    } else if (strtolower($params['text']) === "delivering") {
        $db = $this->get('db');
        $nexmo = $this->get('nexmo');

        $stmt = $db->prepare('SELECT * FROM orders WHERE courier_phone = :phone');
        $stmt->execute(['phone' => $params['msisdn']]);
        $order = $stmt->fetch();

        if ($order) {
            $stmt = $db->prepare('SELECT * FROM users WHERE uuid = :uuid');
            $stmt->execute(['uuid' => $order['users_uuid']]);

            $user = $stmt->fetch();
            $pin = mt_rand(10000, 99999);

            $stmt = $db->prepare('UPDATE orders SET status = :status WHERE id = :id');
            $stmt->execute(['status' => Delivery::STATUS_ENROUTE, 'id' => $order['id']]);

            $nexmo->message()->send([
                'to' => $user['phone'],
                'from' => getenv('NEXMO.PHONE'),
                'text' => 'Your delivery is on the way! The verification pin is ' . $pin
            ]);

            $nexmo->message()->send([
                'to' => $order['courier_phone'],
                'from' => getenv('NEXMO.PHONE'),
                'text' => 'Thank you! The verification pin to provide to the customer is ' . $pin
            ]);
        }
    } else if (strtolower($params['text']) === "arrived") {
        $db = $this->get('db');
        $nexmo = $this->get('nexmo');

        $stmt = $db->prepare('SELECT * FROM orders WHERE courier_phone = :phone');
        $stmt->execute(['phone' => $params['msisdn']]);
        $order = $stmt->fetch();

        if ($order) {
            $stmt = $db->prepare('SELECT * FROM users WHERE uuid = :uuid');
            $stmt->execute(['uuid' => $order['users_uuid']]);

            $user = $stmt->fetch();

            $stmt = $db->prepare('UPDATE orders SET status = :status WHERE id = :id');
            $stmt->execute(['status' => Delivery::STATUS_WAITING, 'id' => $order['id']]);

            $nexmo->message()->send([
                'to' => $user['phone'],
                'from' => getenv('NEXMO.PHONE'),
                'text' => 'Your delivery has arrived! Please make sure to verify the pin with the delivery person.'
            ]);

            $nexmo->message()->send([
                'to' => $order['courier_phone'],
                'from' => getenv('NEXMO.PHONE'),
                'text' => 'Thanks! We will let the customer know. Remember to provide them with your verification pin.'
            ]);
        }
    } else if (strtolower($params['text']) === "complete") {
        $db = $this->get('db');
        $nexmo = $this->get('nexmo');

        $stmt = $db->prepare('SELECT * FROM orders WHERE courier_phone = :phone');
        $stmt->execute(['phone' => $params['msisdn']]);
        $order = $stmt->fetch();

        if ($order) {
            $stmt = $db->prepare('SELECT * FROM users WHERE uuid = :uuid');
            $stmt->execute(['uuid' => $order['users_uuid']]);

            $user = $stmt->fetch();

            $stmt = $db->prepare('UPDATE orders SET status = :status WHERE id = :id');
            $stmt->execute(['status' => Delivery::STATUS_DELIVERED, 'id' => $order['id']]);

            $nexmo->message()->send([
                'to' => $user['phone'],
                'from' => getenv('NEXMO.PHONE'),
                'text' => 'Thank you for trusting us with your delivery!'
            ]);

            $nexmo->message()->send([
                'to' => $order['courier_phone'],
                'from' => getenv('NEXMO.PHONE'),
                'text' => 'Thanks! This delivery is marked as completed.'
            ]);
        }
    } else {
        /** @var Client $nexmo */
        $nexmo->message()->send([
            'to' => $params['msisdn'],
            'from' => getenv('NEXMO.PHONE'),
            'text' => 'Unable to handle your request, please contact support'
        ]);
    }

    return new EmptyResponse();
});

$app->run();
