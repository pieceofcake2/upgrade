<?php
/**
 * This file is loaded automatically by webroot/index.php after core.php
 */

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
]);

CakeLog::config('error', [
    'engine' => FileLog::class,
    'types' => ['warning', 'error', 'critical', 'alert', 'emergency'],
    'file' => 'error',
]);

CakePlugin::load(['Tags', 'Admin']);
