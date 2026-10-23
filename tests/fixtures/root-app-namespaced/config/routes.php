<?php

use Cake\Core\CakePlugin;
use Cake\Routing\Router;

Router::connect('/', ['controller' => 'pages', 'action' => 'display', 'home']);
Router::connect('/products', ['controller' => 'products', 'action' => 'index']);

CakePlugin::routes();

require CAKE . 'Config' . DS . 'routes.php';
