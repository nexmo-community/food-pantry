<?php
declare(strict_types=1);

namespace Pantry\Entity;

class Driver
{
    /**
     * @var string
     */
    protected $name;

    public function fromArray(array $data) : void
    {
        $this->name = $data['name'] ?? null;
    }

    public function getName() : string
    {
        return $this->name;
    }
}