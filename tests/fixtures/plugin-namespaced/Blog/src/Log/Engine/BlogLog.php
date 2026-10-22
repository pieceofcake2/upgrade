<?php

namespace Blog\Log\Engine;

use Cake\Log\Engine\BaseLog;

class BlogLog extends BaseLog
{
    public function write($type, $message)
    {
        return true;
    }
}
