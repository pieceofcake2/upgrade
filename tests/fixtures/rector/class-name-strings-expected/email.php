<?php

use Cake\Network\Email\MailTransport;
use Cake\Network\Email\SmtpTransport;

class EmailConfig
{
    public $default = [
        'transport' => MailTransport::class,
        'from' => 'you@localhost',
    ];

    public $smtp = [
        'transport' => SmtpTransport::class,
        'host' => 'localhost',
    ];
}
