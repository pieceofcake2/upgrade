<?php
/**
 * This is core configuration file.
 *
 * @package       app.Config
 */

use Cake\Cache\Cache;
use Cake\Cache\Engine\FileEngine;
use Cake\Core\Configure;
use Cake\Error\ErrorHandler;
use Cake\Error\ExceptionRenderer;

/**
 * CakePHP Debug Level:
 */
Configure::write('debug', 0);

/**
 * Configure the Error handler used to handle errors for your application.
 */
Configure::write('Error', [
    'handler' => [ErrorHandler::class, 'handleError'],
    'level' => E_ALL & ~E_DEPRECATED,
    'trace' => true,
]);

/**
 * Configure the Exception handler used for uncaught exceptions.
 */
Configure::write('Exception', [
    'handler' => [ErrorHandler::class, 'handleException'],
    'renderer' => ExceptionRenderer::class,
    'log' => true,
]);

/**
 * The namespace of the classes of the application
 */
Configure::write('App.namespace', 'App');

/**
 * Application wide charset encoding
 */
Configure::write('App.encoding', 'UTF-8');

/**
 * Configure the cache used for general framework caching.
 */
Cache::config('_cake_core_', [
    'engine' => FileEngine::class,
    'prefix' => 'shop_cake_core_',
    'path' => CACHE . 'persistent' . DS,
    'duration' => '+999 days',
]);
