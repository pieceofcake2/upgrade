<?php
App::uses('BaseLog', 'Log/Engine');

class BlogLog extends BaseLog
{
    public function write($type, $message)
    {
        return true;
    }
}
