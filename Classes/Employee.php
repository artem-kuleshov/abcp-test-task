<?php

namespace NW\WebService\References\Operations\Notification\Classes;


class Employee extends Contractor
{
    public static function getById(int $expertId): self
    {
        return new self($expertId); // fakes the getById method
    }
}