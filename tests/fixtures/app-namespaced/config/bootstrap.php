<?php
/**
 * This file is loaded automatically by the app/webroot/index.php file after core.php
 */

use App\Log\Engine\AuditLog;
use App\Routing\Filter\MaintenanceFilter;
use Cake\Cache\Cache;
use Cake\Cache\Engine\FileEngine;
use Cake\Core\CakePlugin;
use Cake\Core\Configure;
use Cake\Log\CakeLog;
use Cake\Log\Engine\FileLog;
use Cake\Routing\Filter\AssetDispatcher;
use Cake\Routing\Filter\CacheDispatcher;

Cache::config('default', ['engine' => FileEngine::class]);

Configure::write('Dispatcher.filters', [
    AssetDispatcher::class,
    CacheDispatcher::class,
    MaintenanceFilter::class => ['priority' => 1],
]);
CakeLog::config('debug', [
    'engine' => FileLog::class,
    'types' => ['notice', 'info', 'debug'],
    'file' => 'debug',
]);
CakeLog::config('audit', [
    'engine' => AuditLog::class,
    'types' => ['info'],
]);

CakePlugin::load('Blog');
