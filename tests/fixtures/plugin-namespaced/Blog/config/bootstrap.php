<?php

use Blog\Log\Engine\BlogLog;
use Cake\Core\Configure;
use Cake\Log\CakeLog;

Configure::write('Blog.perPage', 10);

CakeLog::config('blog', [
    'engine' => BlogLog::class,
    'types' => ['info'],
]);
