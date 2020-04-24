<?php
declare(strict_types=1);

namespace Pantry\Action;

use Nexmo\Client;
use Nexmo\Client\Exception\Request;
use Slim\Views\Twig;
use Ramsey\Uuid\Uuid;
use Slim\Flash\Messages;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\RedirectResponse;

class RegisterAction
{
    /**
     * @var \PDO
     */
    protected $db;

    /**
     * @var Messages
     */
    protected $flash;

    /**
     * @var Client
     */
    protected $nexmo;

    /**
     * @var Twig
     */
    protected $view;

    public function __construct(ContainerInterface $c)
    {
        $this->db = $c->get('db');
        $this->flash = $c->get('flash');
        $this->nexmo = $c->get('nexmo');
        $this->view = $c->get('view');
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        if ($request->getMethod() === "POST") {
            $email = $request->getParsedBody()['email'];
            $password = $request->getParsedBody()['password'];
            $phone = $request->getParsedBody()['phone'];
    
            /** @var \PDO $this->db */
            $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
    
            $user = $stmt->fetch();
            if (!$user) {
                /** @var Client $this->nexmo */
                try {
                    $ni = $this->nexmo->insights()->basic($phone);
                } catch (Request $e) {
                    $this->flash->addMessage('error', "Invalid phone number, please make sure to include the country code and nothing but numbers");
                    return new RedirectResponse('/register');
                }
                $stmt = $this->db->prepare('INSERT INTO users (uuid, email, phone, password) VALUES (:uuid, :email, :phone, :password)');
                $stmt->execute(['uuid' => Uuid::uuid4()->toString(), 'email' => $email, 'phone' => $phone, 'password' => password_hash($password, PASSWORD_DEFAULT)]);
    
                $this->flash->addMessage('success', "Registered, please log in!");
                return new RedirectResponse('/login');
            } else {
                $this->flash->addMessage('error', 'Unable to register this user');
                return new RedirectResponse('/register');
            }
        }
    
        return $this->view->render($response, 'register.twig.html');
    }
}
