<?php
/**
 * The rules of `bin/cake upgrade namespace`, which runs them on the files of an application or a plugin after it moved
 * them. The shell writes the namespaces of the files and the new names of the classes to a JSON file, the plan, and
 * passes its path in the environment variable UPGRADE_NAMESPACE_PLAN. Not a set of the `rector` command.
 */

declare(strict_types=1);

use PieceOfCake2\Upgrade\Rector\ClassNameArgumentRector;
use PieceOfCake2\Upgrade\Rector\ClassNameStringRector;
use PieceOfCake2\Upgrade\Rector\DocblockNameImportRector;
use PieceOfCake2\Upgrade\Rector\NamespacedClassNameRector;
use PieceOfCake2\Upgrade\Rector\NamespaceFromPathRector;
use PieceOfCake2\Upgrade\Rector\RemoveAppUsesRector;
use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\Name\RenameClassRector;

$file = (string)getenv('UPGRADE_NAMESPACE_PLAN');
$plan = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
if (!is_array($plan)) {
    throw new RuntimeException('Run these rules with bin/cake upgrade namespace');
}

// The classes of the application or plugin win over the classes of CakePHP of the same name, e.g. AppShell
$classes = array_merge(require dirname(__DIR__) . '/classes/cakephp.php', $plan['classes']);
// The classes of CakePHP keep their global names in config/ and templates/, as in the files of CakePHP
$renamed = $plan['renameCore'] ? $classes : $plan['classes'];

$config = RectorConfig::configure()
    ->withFileExtensions($plan['extensions'])
    ->withImportNames(importDocBlockNames: false, importShortClasses: true, removeUnusedImports: false)
    ->withRules([RemoveAppUsesRector::class])
    ->withConfiguredRule(NamespaceFromPathRector::class, [
        NamespaceFromPathRector::FILES => $plan['files'],
        NamespaceFromPathRector::ALIASES => $plan['aliases'],
    ])
    ->withConfiguredRule(ClassNameArgumentRector::class, $classes)
    ->withConfiguredRule(ClassNameStringRector::class, [
        ClassNameStringRector::CLASSES => $classes,
        ClassNameStringRector::APP_NAMESPACE => $plan['namespace'],
        ClassNameStringRector::SHORT_NAMES => $plan['shortNames'],
        ClassNameStringRector::INDEX_PATHS => $plan['indexPaths'],
    ])
    ->withConfiguredRule(NamespacedClassNameRector::class, [
        NamespacedClassNameRector::CLASSES => $classes,
        NamespacedClassNameRector::EXTENDED => require dirname(__DIR__) . '/classes/cakephp-extended.php',
        NamespacedClassNameRector::INSTANCEOF => true,
        NamespacedClassNameRector::INDEX_PATHS => $plan['indexPaths'],
    ]);
if ($renamed !== []) {
    $config->withConfiguredRule(RenameClassRector::class, $renamed);
}
// After RenameClassRector: imports the names of the docblocks as the names of the code
$config->withConfiguredRule(DocblockNameImportRector::class, [
    DocblockNameImportRector::CLASSES => array_values($classes),
    DocblockNameImportRector::GLOBALS => true,
]);

return $config;
