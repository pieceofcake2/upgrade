<?php
App::uses('SlugRoute', 'Routing/Route');

Router::connect('/', ['controller' => 'pages', 'action' => 'display', 'home']);
Router::connect('/posts/:slug', ['controller' => 'posts', 'action' => 'view'], ['routeClass' => 'SlugRoute']);

CakePlugin::routes();

// The home page: View/Pages/home.ctp
require CAKE . 'Config' . DS . 'routes.php';
