<?php
declare(strict_types=1);

namespace Pantry\Delivery;

use Pantry\Entity\Customer;
use Pantry\Entity\Driver;
use Ramsey\Uuid\Uuid;

class DeliveryService
{
    public function startDelivery(Driver $driver, Customer $customer) : Delivery
    {
        $delivery = new Delivery();
        $delivery->fromArray(['id' => Uuid::uuid4()->toString()]);
        $delivery->setDriver($driver);
        $delivery->setCustomer($customer);

        return $delivery;
    }
}