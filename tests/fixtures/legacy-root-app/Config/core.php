<?php
/**
 * This is core configuration file.
 *
 * @package       app.Config
 */

/**
 * CakePHP Debug Level:
 */
Configure::write('debug', 0);

/**
 * Configure the Error handler used to handle errors for your application.
 */
Configure::write('Error', [
    'handler' => 'ErrorHandler::handleError',
    'level' => E_ALL & ~E_DEPRECATED,
    'trace' => true,
]);

/**
 * Configure the Exception handler used for uncaught exceptions.
 */
Configure::write('Exception', [
    'handler' => 'ErrorHandler::handleException',
    'renderer' => 'ExceptionRenderer',
    'log' => true,
]);

/**
 * Application wide charset encoding
 */
Configure::write('App.encoding', 'UTF-8');

/**
 * Configure the cache used for general framework caching.
 */
Cache::config('_cake_core_', [
    'engine' => 'File',
    'prefix' => 'shop_cake_core_',
    'path' => CACHE . 'persistent' . DS,
    'duration' => '+999 days',
]);
