<?php
declare(strict_types=1);

namespace Pantry\Delivery;

use Pantry\Entity\Driver;
use Pantry\Entity\Customer;

class Delivery
{
    const STATUS_DISPATCHED = 1;

    /**
     * @var Customer
     */
    protected $customer;

    /**
     * @var Driver
     */
    protected $driver;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var int
     */
    protected $status = self::STATUS_DISPATCHED;

    public function fromArray(array $data) : void
    {
        $this->id = $data['id'] ?? null;
    }

    public function getCustomer() : ?Customer
    {
        return $this->customer;
    }

    public function getDriver() : ?Driver
    {
        return $this->driver;
    }

    public function getId() : string
    {
        return $this->id;
    }

    public function getStatus() : int
    {
        return $this->status;
    }

    public function setCustomer(Customer $customer) : self
    {
        $this->customer = $customer;
        return $this;
    }

    public function setDriver(Driver $driver) : self
    {
        $this->driver = $driver;
        return $this;
    }
}