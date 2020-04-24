<?php
declare(strict_types=1);

namespace Pantry\Action\Webhook;

use Nexmo\Client;
use Pantry\Delivery\Delivery;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ServerRequestInterface;

class GeneralAction
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
        $params = $request->getParsedBody();

        if (!$params || !count($params)) {
            $params = $request->getQueryParams();
        }

        if (strtolower($params['text']) === "new delivery") {
            $stmt = $this->db->prepare('SELECT * FROM users WHERE phone = :phone');
            $stmt->execute(['phone' => $params['msisdn']]);
            $user = $stmt->fetch();
        
            if ($user && $user['verified'] == 1) {
                $stmt = $this->db->prepare("INSERT INTO orders (users_uuid) VALUES (:uuid)");
                $stmt->execute(['uuid' => $user['uuid']]);
        
                /** @var Client $this->nexmo */
                $this->nexmo->message()->send([
                    'to' => $params['msisdn'],
                    'from' => getenv('NEXMO.PHONE'),
                    'text' => 'Your order has been placed!'
                ]);
            } else {
                /** @var Client $this->nexmo */
                $this->nexmo->message()->send([
                    'to' => $params['msisdn'],
                    'from' => getenv('NEXMO.PHONE'),
                    'text' => 'Unable to handle your request, please contact support'
                ]);
            }
        } else if (strtolower($params['text']) === "delivering") {
            $stmt = $this->db->prepare('SELECT * FROM orders WHERE courier_phone = :phone');
            $stmt->execute(['phone' => $params['msisdn']]);
            $order = $stmt->fetch();

            if ($order) {
                $stmt = $this->db->prepare('SELECT * FROM users WHERE uuid = :uuid');
                $stmt->execute(['uuid' => $order['users_uuid']]);

                $user = $stmt->fetch();
                $pin = mt_rand(10000, 99999);

                $stmt = $this->db->prepare('UPDATE orders SET status = :status WHERE id = :id');
                $stmt->execute(['status' => Delivery::STATUS_ENROUTE, 'id' => $order['id']]);

                $this->nexmo->message()->send([
                    'to' => $user['phone'],
                    'from' => getenv('NEXMO.PHONE'),
                    'text' => 'Your delivery is on the way! The verification pin is ' . $pin
                ]);

                $this->nexmo->message()->send([
                    'to' => $order['courier_phone'],
                    'from' => getenv('NEXMO.PHONE'),
                    'text' => 'Thank you! The verification pin to provide to the customer is ' . $pin
                ]);
            }
        } else if (strtolower($params['text']) === "arrived") {
            $stmt = $this->db->prepare('SELECT * FROM orders WHERE courier_phone = :phone');
            $stmt->execute(['phone' => $params['msisdn']]);
            $order = $stmt->fetch();

            if ($order) {
                $stmt = $this->db->prepare('SELECT * FROM users WHERE uuid = :uuid');
                $stmt->execute(['uuid' => $order['users_uuid']]);

                $user = $stmt->fetch();

                $stmt = $this->db->prepare('UPDATE orders SET status = :status WHERE id = :id');
                $stmt->execute(['status' => Delivery::STATUS_WAITING, 'id' => $order['id']]);

                $this->nexmo->message()->send([
                    'to' => $user['phone'],
                    'from' => getenv('NEXMO.PHONE'),
                    'text' => 'Your delivery has arrived! Please make sure to verify the pin with the delivery person.'
                ]);

                $this->nexmo->message()->send([
                    'to' => $order['courier_phone'],
                    'from' => getenv('NEXMO.PHONE'),
                    'text' => 'Thanks! We will let the customer know. Remember to provide them with your verification pin.'
                ]);
            }
        } else if (strtolower($params['text']) === "complete") {
            $stmt = $this->db->prepare('SELECT * FROM orders WHERE courier_phone = :phone');
            $stmt->execute(['phone' => $params['msisdn']]);
            $order = $stmt->fetch();

            if ($order) {
                $stmt = $this->db->prepare('SELECT * FROM users WHERE uuid = :uuid');
                $stmt->execute(['uuid' => $order['users_uuid']]);

                $user = $stmt->fetch();

                $stmt = $this->db->prepare('UPDATE orders SET status = :status WHERE id = :id');
                $stmt->execute(['status' => Delivery::STATUS_DELIVERED, 'id' => $order['id']]);

                $this->nexmo->message()->send([
                    'to' => $user['phone'],
                    'from' => getenv('NEXMO.PHONE'),
                    'text' => 'Thank you for trusting us with your delivery!'
                ]);

                $this->nexmo->message()->send([
                    'to' => $order['courier_phone'],
                    'from' => getenv('NEXMO.PHONE'),
                    'text' => 'Thanks! This delivery is marked as completed.'
                ]);
            }
        } else {
            /** @var Client $this->nexmo */
            $this->nexmo->message()->send([
                'to' => $params['msisdn'],
                'from' => getenv('NEXMO.PHONE'),
                'text' => 'Unable to handle your request, please contact support'
            ]);
        }

        return new EmptyResponse();
    }
}
