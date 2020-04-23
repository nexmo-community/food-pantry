<?php
declare(strict_types=1);

namespace NexmoPHPSkeleton\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UserLoader implements MiddlewareInterface
{
    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * @var array
     */
    protected $user;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Invoke middleware.
     *
     * @param ServerRequestInterface $request The request
     * @param RequestHandlerInterface $handler The handler
     *
     * @return ResponseInterface The response
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (isset($_SESSION['user_id']) && is_null($this->user)) {       
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE uuid=:uuid");
            $stmt->execute(['uuid' => $_SESSION['user_id']]);
            $this->user = $stmt->fetch();

            $_SESSION['user'] = $this->user;
        }

        $response = $handler->handle($request);
        return $response;
    }
}
