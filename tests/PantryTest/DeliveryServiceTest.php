<?php

namespace PantryTest;

use Ramsey\Uuid\Uuid;
use Pantry\Entity\Driver;
use Pantry\Entity\Customer;
use Pantry\Delivery\Delivery;
use PHPUnit\Framework\TestCase;
use Pantry\Delivery\DeliveryService;

class DeliveryServiceTest extends TestCase
{
    public function testCanStartDelivery()
    {
        $service = new DeliveryService();
        $driver = new Driver();
        $driver->fromArray(['name' => 'Bob Denver']);
        $customer = new Customer();
        $customer->fromArray(['name' => 'Jane Doe']);
        
        $jobInfo = $service->startDelivery($driver, $customer);

        $this->assertTrue(Uuid::isValid($jobInfo->getId()));
        $this->assertSame($customer->getName(), $jobInfo->getCustomer()->getName());
        $this->assertSame($driver->getName(), $jobInfo->getDriver()->getName());
        $this->assertSame(Delivery::STATUS_DISPATCHED, $jobInfo->getStatus());
    }
}