<?php

namespace NW\WebService\References\Operations\Notification\Classes;


class Status
{
    public int $id;

    public static function getName(int $id): string
    {
        $a = [
            0 => 'Completed',
            1 => 'Pending',
            2 => 'Rejected',
        ];

        return $a[$id];
    }
}