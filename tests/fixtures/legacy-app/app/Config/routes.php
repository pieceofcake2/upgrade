<?php
App::uses('SlugRoute', 'Routing/Route');

Router::connect('/', ['controller' => 'pages', 'action' => 'display', 'home']);
Router::connect('/posts/:slug', ['controller' => 'posts', 'action' => 'view'], ['routeClass' => 'SlugRoute']);

CakePlugin::routes();

require CAKE . 'Config' . DS . 'routes.php';
