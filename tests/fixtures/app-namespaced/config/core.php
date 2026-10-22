<?php
/**
 * This is core configuration file.
 *
 * Use it to configure core behavior of Cake.
 *
 * @link          https://cakephp.org CakePHP(tm) Project
 * @package       app.Config
 */

use Cake\Error\ErrorHandler;
use App\Error\AppExceptionRenderer;
use Cake\Model\Datasource\Session\DatabaseSession;
use Cake\Controller\Component\Acl\DbAcl;

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
    'renderer' => AppExceptionRenderer::class,
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
 * Session configuration.
 */
Configure::write('Session', [
    'defaults' => 'php',
    'handler' => [
        'engine' => DatabaseSession::class,
    ],
]);

/**
 * The class name and database used in CakePHP's
 * access control lists.
 */
Configure::write('Acl.classname', DbAcl::class);
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
