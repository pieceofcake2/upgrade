<?php

use Blog\Log\Engine\BlogLog;

Configure::write('Blog.perPage', 10);

CakeLog::config('blog', [
    'engine' => BlogLog::class,
    'types' => ['info'],
]);
