<?php

namespace App\Utility;

use Cake\Utility\CakeNumber;

class Price
{
    public static function format($cents)
    {
        return CakeNumber::currency($cents / 100, 'EUR');
    }
}
