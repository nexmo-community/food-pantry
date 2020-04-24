<?php
declare(strict_types=1);

namespace Pantry\Action;

use Slim\Views\Twig;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HomepageAction
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
        return $this->view->render($response, 'homepage.twig.html');
    }
}
