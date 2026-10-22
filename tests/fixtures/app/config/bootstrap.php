<?php
/**
 * This file is loaded automatically by the app/webroot/index.php file after core.php
 */

Cache::config('default', ['engine' => 'File']);

Configure::write('Dispatcher.filters', [
    'AssetDispatcher',
    'CacheDispatcher',
    'MaintenanceFilter' => ['priority' => 1],
]);

App::uses('CakeLog', 'Log');
CakeLog::config('debug', [
    'engine' => 'File',
    'types' => ['notice', 'info', 'debug'],
    'file' => 'debug',
]);
CakeLog::config('audit', [
    'engine' => 'AuditLog',
    'types' => ['info'],
]);

CakePlugin::load('Blog');
