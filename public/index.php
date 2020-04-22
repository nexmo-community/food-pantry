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

$app->post('/', function (Request $request, Response $response) {
    $nexmo = new Client(
        new Basic(getenv('NEXMO_API_KEY'), getenv('NEXMO_API_SECRET')),
        ['app' => ['name' => 'php-skeleton-app', 'version' => '1.0.0']]
    );
    $body = $request->getParsedBody();

    if (!array_key_exists('to', $body)) {
        $this->get('flash')->addMessage('error', 'You must supply a number to send to');
    }

    if (!array_key_exists('from', $body)) {
        $this->get('flash')->addMessage('error', 'You must supply a number to send from, and it must be a Nexmo number');
    }

    if (!array_key_exists('text', $body)) {
        $this->get('flash')->addMessage('error', 'You must supply a message to send');
    }

    if (empty($this->get('flash')->getMessages())) {
        try {
            $sms = $nexmo->message()->send([
                'to' => $body['to'],
                'from' => $body['from'],
                'text' => $body['text'],
            ]);
            $this->get('flash')->addMessage('success', 'Message has been sent with ID ' . $sms->getMessageId());
        } catch (\Exception $e) {
            $this->get('flash')->addMessage('error', 'Message could not be sent - ' . $e->getMessage());
        }
    }

    return new RedirectResponse('/');
});

$app->map(['GET','POST'], '/webhooks/event', function (Request $request, Response $response) {
    $params = $request->getParsedBody();

    if (!$params || !count($params)) {
        $params = $request->getQueryParams();
    }

    error_log(json_encode($params));

    return $response->withStatus(204);
});

$app->run();
