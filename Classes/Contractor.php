<?php

namespace NW\WebService\References\Operations\Notification\Classes;


class Contractor
{
    const TYPE_CUSTOMER = 0;
    
    public $id;
    public $type;
    public $name;

    public static function getById(int $clientId): self
    {
        return new self($clientId); // fakes the getById method
    }

    public function getFullName(): string
    {
        return $this->name . ' ' . $this->id;
    }
}