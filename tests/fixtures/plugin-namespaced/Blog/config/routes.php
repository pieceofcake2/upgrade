<?php

use Cake\Routing\Router;

Router::connect('/blog', ['plugin' => 'blog', 'controller' => 'posts', 'action' => 'index']);
