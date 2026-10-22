<?php
Configure::write('Blog.perPage', 10);

CakeLog::config('blog', [
    'engine' => 'Blog.BlogLog',
    'types' => ['info'],
]);
