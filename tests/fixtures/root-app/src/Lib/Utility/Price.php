<?php
App::uses('CakeNumber', 'Utility');

class Price
{
    public static function format($cents)
    {
        return CakeNumber::currency($cents / 100, 'EUR');
    }
}
