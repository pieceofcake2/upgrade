<?php
App::uses('BaseLog', 'Log/Engine');

class AuditLog extends BaseLog
{
    public function write($type, $message)
    {
        return true;
    }
}
