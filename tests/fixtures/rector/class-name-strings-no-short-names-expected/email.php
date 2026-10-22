<?php
class EmailConfig
{
    public $default = [
        'transport' => 'Mail',
        'from' => 'you@localhost',
    ];

    public $smtp = [
        'transport' => 'Smtp',
        'host' => 'localhost',
    ];
}
