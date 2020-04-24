<?php
declare(strict_types=1);

namespace Pantry\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\RedirectResponse;

class LogoutAction
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        session_destroy();
        return new RedirectResponse('/');
    }
}
