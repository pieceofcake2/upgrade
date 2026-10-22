<?php

namespace App\Log\Engine;

use Cake\Log\Engine\BaseLog;

class AuditLog extends BaseLog
{
    public function write($type, $message)
    {
        return true;
    }
}
