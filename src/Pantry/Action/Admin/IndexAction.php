<?php
declare(strict_types=1);

namespace Pantry\Action\Admin;

use Slim\Views\Twig;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class IndexAction
{
    /**
     * @var \PDO
     */
    protected $db;

    /**
     * @var Twig
     */
    protected $view;

    public function __construct(ContainerInterface $c)
    {
        $this->db = $c->get('db');
        $this->view = $c->get('view');
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        $stmt = $this->db->prepare('SELECT * FROM orders');
        $stmt->execute();

        $orders = [];
        while (($row = $stmt->fetch()) !== false) {
            $cstmt = $this->db->prepare('SELECT * FROM users WHERE uuid=:uuid');
            $cstmt->execute(['uuid' => $row['users_uuid']]);
            $customer = $cstmt->fetch();

            $orders[] = [
                'order' => $row,
                'customer' => $customer
            ];
        }

        return $this->view->render($response, 'admin.twig.html', ['orders' => $orders]);
    }
}
