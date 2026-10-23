<?php

namespace App\Console\Command;

use App\Model\Product;
use App\Utility\Price;

class PriceShell extends AppShell
{
    public $uses = [Product::class];

    public function main()
    {
        $this->out(Price::format(1200));
    }
}
