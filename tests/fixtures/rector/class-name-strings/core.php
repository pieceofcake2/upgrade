<?php
/**
 * Core configuration
 */

Configure::write('Error', [
    'handler' => 'ErrorHandler::handleError',
    'consoleHandler' => 'ConsoleErrorHandler::handleError',
    'level' => E_ALL,
]);

Configure::write('Exception', [
    'handler' => 'ErrorHandler::handleException',
    'renderer' => 'ExceptionRenderer',
    'log' => true,
]);
Configure::write('Exception.renderer', 'ExceptionRenderer');

Configure::write('Session', [
    'defaults' => 'database',
    'handler' => ['engine' => 'CacheSession', 'config' => 'session'],
]);

Configure::write('Acl.classname', 'DbAcl');

Configure::write('Dispatcher.filters', [
    'AssetDispatcher',
    'CacheDispatcher' => ['priority' => 2],
    ['callable' => 'AssetDispatcher', 'on' => 'before'],
    ['callable' => function ($event) {
    }, 'on' => 'after'],
]);

Cache::config('default', ['engine' => 'Apc', 'duration' => 3600]);
Cache::config('short', ['engine' => 'MemcachedEngine']);
CakeLog::config('debug', ['engine' => 'File']);
CakeLog::config('errors', ['engine' => 'FileLog']);
CakeLog::config('syslog', ['engine' => 'Syslog']);

Router::connect('/posts/:slug', ['controller' => 'posts'], ['routeClass' => 'PluginShortRoute']);
Router::defaultRouteClass('CakeRoute');

// Other settings stay
Configure::write('App.encoding', 'UTF-8');
Configure::write('Config.language', 'eng');
