<?php
declare(strict_types=1);

namespace Pantry\Action;

use Slim\Views\Twig;
use Slim\Flash\Messages;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\RedirectResponse;

class LoginAction
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
     * @var Twig
     */
    protected $view;

    public function __construct(ContainerInterface $c)
    {
        $this->db = $c->get('db');
        $this->flash = $c->get('flash');
        $this->view = $c->get('view');
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        if ($request->getMethod() === "POST") {
            $email = $request->getParsedBody()['email'];
            $password = $request->getParsedBody()['password'];
    
            $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
    
            $user = $stmt->fetch();
            if ($user) {
                if (password_verify($password, $user['password'])) {
                    $this->flash->addMessage('success', 'Logged in!');
                    $_SESSION['user_id'] = $user['uuid'];
                    return new RedirectResponse('/');
                } else {
                    $this->flash->addMessage('error', 'Invalid username/password');
                    return new RedirectResponse('/login');
                }
            } else {
                $this->flash->addMessage('error', 'Invalid username/password');
                return new RedirectResponse('/login');
            }
        }
    
        return $this->view->render($response, 'login.twig.html');
    }
}
