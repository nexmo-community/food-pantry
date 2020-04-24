<?php
declare(strict_types=1);

namespace Pantry\Action\Admin;

use Nexmo\Client;
use Pantry\Delivery\Delivery;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ServerRequestInterface;

class AssignDeliveryAction
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

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $phone = $request->getQueryParams()['courier'];
    
        $stmt = $this->db->prepare('UPDATE orders SET courier_phone=:phone, status = :status WHERE id=:id');
        $stmt->execute(['id' => $args['id'], 'phone' => $phone, 'status' => Delivery::STATUS_DISPATCHED]);
    
        $this->nexmo->message()->send([
            'to' => $phone,
            'from' => getenv('NEXMO.PHONE'),
            'text' => 'You have a new delivery assigned to you'
        ]);
    
        return new EmptyResponse();
    }
}
