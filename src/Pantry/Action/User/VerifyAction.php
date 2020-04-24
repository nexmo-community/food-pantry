<?php
declare(strict_types=1);

namespace Pantry\Action\User;

use Slim\Views\Twig;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\RedirectResponse;

class VerifyAction
{
    /**
     * @var Twig
     */
    protected $view;

    public function __construct(ContainerInterface $c)
    {
        $this->view = $c->get('view');
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        if (!isset($_SESSION['user'])) {
            return new RedirectResponse('/login');
        }
    
        return $this->view->render($response, 'verify.twig.html');
    }
}
