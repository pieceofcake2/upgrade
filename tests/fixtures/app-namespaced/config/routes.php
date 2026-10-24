<?php

use App\Routing\Route\SlugRoute;
use Cake\Core\CakePlugin;
use Cake\Routing\Router;

Router::connect('/', ['controller' => 'pages', 'action' => 'display', 'home']);
Router::connect('/posts/:slug', ['controller' => 'posts', 'action' => 'view'], ['routeClass' => SlugRoute::class]);

CakePlugin::routes();

// The home page: View/Pages/home.ctp
require CAKE . 'Config' . DS . 'routes.php';
