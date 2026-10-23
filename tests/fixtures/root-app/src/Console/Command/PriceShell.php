<?php
App::uses('AppShell', 'Console/Command');
App::uses('Price', 'Utility');

class PriceShell extends AppShell
{
    public $uses = ['Product'];

    public function main()
    {
        $this->out(Price::format(1200));
    }
}
