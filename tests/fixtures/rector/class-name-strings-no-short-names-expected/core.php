<?php
use Cake\Error\ErrorHandler;
use Cake\Console\ConsoleErrorHandler;
use Cake\Error\ExceptionRenderer;
use Cake\Model\Datasource\Session\CacheSession;
use Cake\Controller\Component\Acl\DbAcl;
use Cake\Routing\Filter\AssetDispatcher;
use Cake\Routing\Filter\CacheDispatcher;
use Cake\Log\Engine\FileLog;
use Cake\Routing\Route\PluginShortRoute;
use Cake\Routing\Route\CakeRoute;

/**
 * Core configuration
 */

Configure::write('Error', [
    'handler' => [ErrorHandler::class, 'handleError'],
    'consoleHandler' => [ConsoleErrorHandler::class, 'handleError'],
    'level' => E_ALL,
]);

Configure::write('Exception', [
    'handler' => [ErrorHandler::class, 'handleException'],
    'renderer' => ExceptionRenderer::class,
    'log' => true,
]);
Configure::write('Exception.renderer', ExceptionRenderer::class);

Configure::write('Session', [
    'defaults' => 'database',
    'handler' => ['engine' => CacheSession::class, 'config' => 'session'],
]);

Configure::write('Acl.classname', DbAcl::class);

Configure::write('Dispatcher.filters', [
    AssetDispatcher::class,
    CacheDispatcher::class => ['priority' => 2],
    ['callable' => AssetDispatcher::class, 'on' => 'before'],
    ['callable' => function ($event) {
    }, 'on' => 'after'],
]);

Cache::config('default', ['engine' => 'Apc', 'duration' => 3600]);
Cache::config('short', ['engine' => 'MemcachedEngine']);
CakeLog::config('debug', ['engine' => 'File']);
CakeLog::config('errors', ['engine' => FileLog::class]);
CakeLog::config('syslog', ['engine' => 'Syslog']);

Router::connect('/posts/:slug', ['controller' => 'posts'], ['routeClass' => PluginShortRoute::class]);
Router::defaultRouteClass(CakeRoute::class);

// Other settings stay
Configure::write('App.encoding', 'UTF-8');
Configure::write('Config.language', 'eng');
