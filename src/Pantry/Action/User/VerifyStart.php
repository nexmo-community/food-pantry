<?php
declare(strict_types=1);

namespace Pantry\Action\User;

use Nexmo\Client;
use Nexmo\Verify\Verification;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\RedirectResponse;

class VerifyStartAction
{
    /**
     * @var Client
     */
    protected $nexmo;

    public function __construct(ContainerInterface $c)
    {
        $this->nexmo = $c->get('nexmo');
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        if (!isset($_SESSION['user'])) {
            return new RedirectResponse('/login');
        }
    
        $user = $_SESSION['user'];
        $verify = $this->nexmo->verify()->start(new Verification($user['phone'], 'Pantry Test'));
        $_SESSION['verification_id'] = $verify->getRequestId();
    
        return new EmptyResponse();
    }
}
