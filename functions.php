<?php

function getResellerEmailFrom()
{
    return 'contractor@example.com';
}

function getEmailsByPermit(int $resellerId, $event)
{
    // fakes the method
    return ['someemeil@example.com', 'someemeil2@example.com'];
}
