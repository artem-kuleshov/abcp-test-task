<?php

namespace NW\WebService\References\Operations\Notification\Classes;


abstract class ReferencesOperation
{
    abstract public function doOperation(): array;

    public function getRequest($pName)
    {
        return $_REQUEST[$pName];
    }
}