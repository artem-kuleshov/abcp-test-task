<?php

namespace NW\WebService\References\Operations\Notification\Classes;


class Seller extends Contractor
{
    public static function getById(int $resellerId): self
    {
        return new self($resellerId); // fakes the getById method
    }
}