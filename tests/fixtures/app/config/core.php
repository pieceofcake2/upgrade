<?php
/**
 * This is core configuration file.
 *
 * Use it to configure core behavior of Cake.
 *
 * @link          https://cakephp.org CakePHP(tm) Project
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
    'renderer' => 'AppExceptionRenderer',
    'log' => true,
]);

/**
 * Application wide charset encoding
 */
Configure::write('App.encoding', 'UTF-8');

/**
 * Session configuration.
 */
Configure::write('Session', [
    'defaults' => 'php',
    'handler' => [
        'engine' => 'DatabaseSession',
    ],
]);

/**
 * The class name and database used in CakePHP's
 * access control lists.
 */
Configure::write('Acl.classname', 'DbAcl');
Configure::write('Acl.database', 'default');

$engine = 'File';

/**
 * Configure the cache used for general framework caching.
 */
Cache::config('_cake_core_', [
    'engine' => $engine,
    'prefix' => 'myapp_cake_core_',
    'path' => CACHE . 'persistent' . DS,
    'duration' => '+999 days',
]);
