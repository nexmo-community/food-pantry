<?php
declare(strict_types=1);

namespace Pantry\Action\User;

use Nexmo\Verify\Verification;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\RedirectResponse;

class VerifyConfirmAction
{
    /**
     * @var \PDO
     */
    protected $db;

    /**
     * @var Client
     */
    protected $nexmo;

    public function __construct(ContainerInterface $c)
    {
        $this->db = $c->get('db');
        $this->nexmo = $c->get('nexmo');
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        if (!isset($_SESSION['user'])) {
            return new RedirectResponse('/login');
        }
    
        $user = $_SESSION['user'];
        $verificationId = $_SESSION['verification_id'];
        $this->nexmo->verify()->check(new Verification($verificationId), $request->getQueryParams()['pin']);
    
        $stmt = $this->db->prepare('UPDATE users SET verified = 1 WHERE uuid = :uuid');
        $stmt->execute(['uuid' => $user['uuid']]);
        unset($_SESSION['verification_id']);
    
        return new EmptyResponse();
    }
}
