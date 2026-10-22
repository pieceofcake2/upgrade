<?php
/**
 * The class names in the strings of the settings that name a class (CakePHP 2.13): replaces them by `::class`, with
 * use statements, e.g. `public $components = ['Session']` by `use Cake\Controller\Component\SessionComponent;` and
 * `public $components = [SessionComponent::class]`. Only the places that take a class name change, see
 * ClassNameStringRector. The names keep working, so the change is optional.
 *
 * ```
 * bin/cake upgrade rector --rules class-name-strings src tests config
 * bin/cake upgrade rector --rules class-name-strings --no-short-names src tests config
 * ```
 */

declare(strict_types=1);

use PieceOfCake2\Upgrade\Rector\ClassNameStringRector;
use Rector\Config\RectorConfig;

// Whether the short names ('Session', 'Auth', 'DebugKit.Toolbar') also change, besides the full class names
// ('AppExceptionRenderer', 'Post'). Set it to false, or run the shell with --no-short-names, to keep them.
$shortNames = getenv('UPGRADE_SHORT_NAMES') !== '0';

// App.namespace of the application, which the shell passes: 'Session' becomes App\Controller\Component\SessionComponent
// when the application has that class
$namespace = getenv('UPGRADE_APP_NAMESPACE') ?: null;

return RectorConfig::configure()
    ->withImportNames(importDocBlockNames: false, importShortClasses: false, removeUnusedImports: false)
    ->withConfiguredRule(ClassNameStringRector::class, [
        ClassNameStringRector::CLASSES => require __DIR__ . '/classes/cakephp.php',
        ClassNameStringRector::APP_NAMESPACE => $namespace,
        ClassNameStringRector::SHORT_NAMES => $shortNames,
    ]);
