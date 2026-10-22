<?php
/**
 * Upgrade Shell
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @package       Cake.Console.Command
 * @since         CakePHP(tm) v 2.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use Composer\InstalledVersions;
use PieceOfCake2\Upgrade\Migration\ComposerJson;
use PieceOfCake2\Upgrade\Migration\CoreConfig;
use PieceOfCake2\Upgrade\Migration\EntryFiles;
use PieceOfCake2\Upgrade\Migration\HeaderFormatter;
use PieceOfCake2\Upgrade\Migration\LayoutPlan;
use PieceOfCake2\Upgrade\Migration\Mover;
use PieceOfCake2\Upgrade\Migration\NamespacePlan;
use PieceOfCake2\Upgrade\Migration\Paths;
use PieceOfCake2\Upgrade\Rector\Report;

App::uses('AppShell', 'Console/Command');
App::uses('Folder', 'Utility');
App::uses('CakePlugin', 'Core');

/**
 * A shell class to help developers upgrade applications to CakePHP 2.0
 *
 * @package       Cake.Console.Command
 */
class UpgradeShell extends AppShell
{
    /**
     * Files
     *
     * @var array
     */
    protected $_files = [];

    /**
     * Paths
     *
     * @var array
     */
    protected $_paths = [];

    /**
     * Map
     *
     * @var array
     */
    protected $_map = [
        'Controller' => 'Controller',
        'Component' => 'Controller/Component',
        'Model' => 'Model',
        'Behavior' => 'Model/Behavior',
        'Datasource' => 'Model/Datasource',
        'Dbo' => 'Model/Datasource/Database',
        'View' => 'View',
        'Helper' => 'View/Helper',
        'Shell' => 'Console/Command',
        'Task' => 'Console/Command/Task',
        'Case' => 'Test/Case',
        'Fixture' => 'Test/Fixture',
        'Error' => 'Lib/Error',
    ];

    /**
     * Shell startup, prints info message about dry run.
     *
     * @return void
     */
    public function startup()
    {
        parent::startup();
        if (!empty($this->params['dry-run'])) {
            $this->out(__d('cake_console', '<warning>Dry-run mode enabled!</warning>'), 1, Shell::QUIET);
        }
        if (!empty($this->params['git']) && !is_dir('.git')) {
            $this->out(__d('cake_console', '<warning>No git repository detected!</warning>'), 1, Shell::QUIET);
        }
    }

    /**
     * Run all upgrade steps one at a time
     *
     * @return void
     */
    public function all()
    {
        foreach ($this->OptionParser->subcommands() as $command) {
            $name = $command->name();
            // rector, layout and namespace are not steps of the upgrade from CakePHP 1.3
            if (in_array($name, ['all', 'rector', 'layout', 'namespace'], true)) {
                continue;
            }
            $this->out(__d('cake_console', 'Running %s', $name));
            $this->$name();
        }
    }

    /**
     * Runs a set of Rector rules of `config/rector/` on the paths of the arguments, e.g.
     * `rector --rules variadic-arguments src tests`
     *
     * @return int|null The exit code of Rector
     */
    public function rector()
    {
        $rules = (string)$this->params['rules'];
        $available = $this->_rectorRules();
        if (!in_array($rules, $available, true)) {
            $this->error(__d('cake_console', 'Unknown rules: %s. Available: %s', $rules, implode(', ', $available)));

            return null;
        }
        if (empty($this->args)) {
            $this->error(__d('cake_console', 'Give the paths to process, e.g. src tests.'));

            return null;
        }
        $command = [
            PHP_BINARY,
            InstalledVersions::getInstallPath('rector/rector') . DS . 'bin' . DS . 'rector',
            'process',
            ...$this->args,
            '--config',
            $this->_rectorConfig($rules),
        ];
        if (!empty($this->params['dry-run'])) {
            $command[] = '--dry-run';
        }
        $namespace = Configure::read('App.namespace');

        return $this->_runRectorWithReport($command, [
            'UPGRADE_SHORT_NAMES' => empty($this->params['no-short-names']) ? '1' : '0',
            'UPGRADE_APP_NAMESPACE' => is_string($namespace) ? $namespace : '',
        ]);
    }

    /**
     * Runs Rector with environment variables for the configuration of the rules, then prints the places that the
     * rules list for a review
     *
     * @param array<string> $command Command and its arguments
     * @param array<string, string> $env Environment variables
     * @return int Exit code
     */
    protected function _runRectorWithReport(array $command, array $env)
    {
        $report = (string)tempnam(TMP, 'upgrade-report-');
        $env[Report::ENV] = $report;
        foreach ($env as $name => $value) {
            putenv($name . '=' . $value);
        }
        try {
            $code = $this->_runRector($command);
        } finally {
            foreach (array_keys($env) as $name) {
                putenv($name);
            }
        }
        $lines = is_file($report) ? array_unique(array_filter(explode("\n", (string)file_get_contents($report)))) : [];
        if (is_file($report)) {
            unlink($report);
        }
        if ($lines !== []) {
            sort($lines);
            $this->out(__d('cake_console', '<warning>Review these places:</warning>'));
            foreach ($lines as $line) {
                $this->out('  ' . $line);
            }
        }

        return $code;
    }

    /**
     * The sets of Rector rules of the plugin: the files of `config/rector/`
     *
     * @return array<string>
     */
    protected function _rectorRules()
    {
        $rules = array_map(static fn(string $file): string => basename($file, '.php'), glob($this->_rectorConfig('*')) ?: []);
        sort($rules);

        return $rules;
    }

    /**
     * The configuration file of a set of Rector rules
     *
     * @param string $rules Name of the set
     * @return string
     */
    protected function _rectorConfig($rules)
    {
        return dirname(__DIR__, 2) . DS . 'config' . DS . 'rector' . DS . $rules . '.php';
    }

    /**
     * Runs Rector
     *
     * @param array<string> $command Command and its arguments
     * @return int Exit code
     */
    protected function _runRector(array $command)
    {
        passthru(implode(' ', array_map('escapeshellarg', $command)), $code);

        return $code;
    }

    /**
     * Moves an application from the CakePHP 2 layout (`app/Config/`, `app/Controller/`, `app/View/`, ...) to the
     * layout of pieceofcake2/app (`config/`, `src/`, `templates/`, `resources/locales/`, `tests/`, `webroot/`), or the
     * configuration, templates, translations and tests of the plugin given with `--plugin`. Run from the root of
     * the project. Not part of all.
     *
     * @return void
     */
    public function layout()
    {
        $plugin = $this->params['plugin'] ?? null;
        $root = $plugin ? $this->_pluginPath((string)$plugin) : $this->_projectRoot();
        if ($root === null) {
            return;
        }
        $plan = $plugin ? LayoutPlan::forPlugin($root) : LayoutPlan::forApplication($root);
        $dryRun = !empty($this->params['dry-run']);
        $mover = new Mover($root);
        $entryFiles = $plan->entryFiles;
        $nothing = $plan->moves === [] && ($entryFiles === null || ($entryFiles->files === [] && $entryFiles->directories === []));
        if ($nothing) {
            $this->out(__d('cake_console', 'Nothing to move: %s is in the layout of pieceofcake2/app.', $plugin ?: $root));
        } elseif (!$dryRun && !$mover->usesGit()) {
            $this->out(__d('cake_console', '<warning>Not a Git repository: the files are moved with rename(), so Git does not see them as moves.</warning>'));
        }
        $this->_move($plan, $mover, $dryRun, $plugin ? ['View', 'Locale', 'Test', 'templates'] : ['app', 'templates']);
        if ($entryFiles !== null) {
            $this->_createEntryFiles($root, $entryFiles, $mover, $dryRun);
        }
        $composer = $root . DS . 'composer.json';
        if (is_file($composer) && $plan->paths !== []) {
            $json = new ComposerJson((string)file_get_contents($composer));
            $this->_moveComposerPaths($json, $plan->paths, 'composer.json');
            if (!$dryRun && $json->isChanged()) {
                file_put_contents($composer, $json->contents());
            }
        }
        $this->_notices(array_merge($mover->warnings(), $plan->notices));
        if (!$nothing && !$dryRun) {
            $this->out(__d('cake_console', 'Next, run composer dump-autoload and the tests. Then `bin/cake upgrade namespace%s` moves the classes into a namespace.', $plugin ? ' --plugin ' . $plugin : ''));
        }
    }

    /**
     * Makes the moves of the layout, then removes the directories that they left empty
     *
     * @param \PieceOfCake2\Upgrade\Migration\LayoutPlan $plan The plan
     * @param \PieceOfCake2\Upgrade\Migration\Mover $mover Moves the files
     * @param bool $dryRun Whether to only show the moves
     * @param array<string> $sources The directories that the moves empty, relative to the root
     * @return void
     */
    protected function _move(LayoutPlan $plan, Mover $mover, $dryRun, array $sources)
    {
        foreach ($plan->moves as [$from, $to]) {
            $this->out(__d('cake_console', 'Move %s to %s', $mover->relative($from), $mover->relative($to)));
            if (!$dryRun) {
                $mover->move($from, $to);
            }
        }
        if ($dryRun || $plan->moves === []) {
            return;
        }
        foreach ($sources as $dir) {
            $mover->removeEmpty($plan->root . DS . $dir);
        }
    }

    /**
     * Creates the missing entry files and directories of the layout of pieceofcake2/app, and removes the entry files
     * of the CakePHP 2 layout that they replace
     *
     * @param string $root The root of the project
     * @param \PieceOfCake2\Upgrade\Migration\EntryFiles $entryFiles The entry files
     * @param \PieceOfCake2\Upgrade\Migration\Mover $mover Moves and removes the files
     * @param bool $dryRun Whether to only show the changes
     * @return void
     */
    protected function _createEntryFiles($root, EntryFiles $entryFiles, Mover $mover, $dryRun)
    {
        foreach ($entryFiles->directories as $dir) {
            $this->out(__d('cake_console', 'Create the directory %s', $dir));
            if (!$dryRun && !is_dir($root . DS . $dir)) {
                mkdir($root . DS . $dir, 0777, true);
            }
        }
        foreach ($entryFiles->files as $file => $contents) {
            $this->out(__d('cake_console', 'Create %s of pieceofcake2/app', $file));
            $path = $root . DS . str_replace('/', DS, $file);
            if ($dryRun || file_exists($path)) {
                continue;
            }
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, $contents);
            if ($file === 'bin/cake') {
                chmod($path, 0755);
            }
        }
        foreach ($entryFiles->kept as $constant => $file) {
            $this->out(__d('cake_console', 'Keep the constant %s of %s in config/define.php', $constant, $file));
        }
        $this->_removeOldEntryFiles($root, $entryFiles, $mover, $dryRun);
    }

    /**
     * Removes the entry files of the CakePHP 2 layout with git rm; outside Git, or with changes that are not
     * committed, they stay and are listed
     *
     * @param string $root The root of the project
     * @param \PieceOfCake2\Upgrade\Migration\EntryFiles $entryFiles The entry files
     * @param \PieceOfCake2\Upgrade\Migration\Mover $mover Removes the files
     * @param bool $dryRun Whether to only show the changes
     * @return void
     */
    protected function _removeOldEntryFiles($root, EntryFiles $entryFiles, Mover $mover, $dryRun)
    {
        $left = [];
        foreach ($entryFiles->oldFiles as $file) {
            $path = $root . DS . str_replace('/', DS, $file);
            if ($dryRun ? $mover->canRemove($path) : $mover->remove($path)) {
                $this->out(__d('cake_console', 'Remove %s, which the files of pieceofcake2/app replace', $file));
            } else {
                $left[] = $file;
            }
        }
        if ($left !== []) {
            $this->out(__d('cake_console', '<warning>Delete %s, which the files of pieceofcake2/app replace, once you have compared them.</warning>', implode(', ', $left)));
        }
    }

    /**
     * Moves the classes of an application, or of the plugin given with `--plugin`, into a namespace: the namespace
     * of `--namespace` (`App` by default, set in `App.namespace`) or the name of the plugin. Needs the layout of
     * pieceofcake2/app (run `layout` first). Not part of all.
     *
     * @return int|null The exit code of Rector, or null on an error
     */
    public function namespace()
    {
        $plugin = $this->params['plugin'] ?? null;
        $dryRun = !empty($this->params['dry-run']);
        $target = $this->_namespaceTarget($plugin ? (string)$plugin : null);
        if ($target === null) {
            return null;
        }
        [$root, $namespace] = $target;

        // A dry run makes the changes in a copy, and shows them as a diff
        $work = $dryRun ? $this->_copyForDryRun($root, 'after') : $root;
        $core = require dirname(__DIR__, 2) . DS . 'config' . DS . 'rector' . DS . 'classes' . DS . 'cakephp.php';
        $plan = NamespacePlan::create($work, $namespace, (bool)$plugin, $core);
        $mover = new Mover($work);
        if (!$dryRun && $plan->moves !== [] && !$mover->usesGit()) {
            $this->out(__d('cake_console', '<warning>Not a Git repository: the files are moved with rename(), so Git does not see them as moves.</warning>'));
        }
        foreach ($plan->moves as [$from, $to]) {
            $this->out(__d('cake_console', 'Move %s to %s', $mover->relative($from), $mover->relative($to)));
            $mover->move($from, $to);
        }
        foreach (['src' . DS . 'Lib', 'Lib'] as $dir) {
            $mover->removeEmpty($work . DS . $dir);
        }
        $before = $dryRun ? $this->_copyForDryRun($work, 'before') : null;
        $configFiles = $this->_hashes($plan->configPaths());
        $configUses = [];
        foreach (array_keys($configFiles) as $file) {
            $configUses[$file] = HeaderFormatter::uses((string)file_get_contents($file));
        }

        $cwd = (string)getcwd();
        chdir($work);
        try {
            $code = $this->_runNamespaceRules($plan, $plugin ? null : $namespace);
        } finally {
            chdir($cwd);
        }
        $this->_formatHeaders($plan, $configFiles, $configUses);
        if ($code !== 0) {
            if ($before !== null) {
                (new Folder(dirname($work, 2)))->delete();
            }
            $this->error(__d('cake_console', 'Rector failed (exit code %d). Fix the error, then run the command again.', $code));

            return $code;
        }
        if (!$plugin) {
            $this->_addAppNamespace($work, $namespace);
        }
        $this->_updateComposerJson($plan, $root, $work, $dryRun);
        if ($before !== null) {
            $this->out($this->_diff($before, $work));
            (new Folder(dirname($work, 2)))->delete();
        }
        $this->_notices(array_merge($mover->warnings(), $plan->notices));
        if (!$dryRun) {
            $this->out(__d('cake_console', 'Next, review the changes (git diff), run composer dump-autoload and the tests. The code needs pieceofcake2/cakephp 2.13 or later.'));
        }

        return $code;
    }

    /**
     * The hashes of the PHP files of directories, to find the files that Rector changes
     *
     * @param array<string> $dirs The directories
     * @return array<string, string> The hashes, by path
     */
    protected function _hashes(array $dirs)
    {
        $hashes = [];
        foreach ($dirs as $dir) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $hashes[$file->getPathname()] = (string)md5_file($file->getPathname());
                }
            }
        }

        return $hashes;
    }

    /**
     * Orders the docblock, namespace and use statements at the beginning of the namespaced files and of the other
     * files that Rector changed, and separates the class_alias() of the core class overrides
     *
     * @param \PieceOfCake2\Upgrade\Migration\NamespacePlan $plan The plan
     * @param array<string, string> $hashes The hashes of the other files before Rector, by path
     * @param array<string, array<string>> $uses The use statements of the other files before Rector, by path
     * @return void
     */
    protected function _formatHeaders(NamespacePlan $plan, array $hashes, array $uses = [])
    {
        $changed = array_keys(array_filter($hashes, static fn(string $hash, string $file): bool => md5_file($file) !== $hash, ARRAY_FILTER_USE_BOTH));
        foreach (array_merge(array_keys($plan->files), $changed) as $file) {
            if (!is_file($file)) {
                continue;
            }
            $contents = HeaderFormatter::format((string)file_get_contents($file), $uses[$file] ?? []);
            if (in_array($file, $plan->aliases, true)) {
                $contents = (string)preg_replace('/^}(\r?\n)class_alias\(/m', '}$1$1class_alias(', $contents);
            }
            file_put_contents($file, $contents);
        }
    }

    /**
     * The directory and the namespace of `upgrade namespace`, or null after an error: a plugin uses its name, an
     * application `--namespace` or App, and the directory must be in the layout of pieceofcake2/app
     *
     * @param string|null $plugin The plugin, or null for the application
     * @return array{string, string}|null
     */
    protected function _namespaceTarget($plugin)
    {
        if ($plugin !== null && isset($this->params['namespace'])) {
            $this->error(__d('cake_console', 'A plugin has the namespace of its name, as CakePHP looks its classes up as %s\\<Type>\\<Class>: leave out --namespace.', $plugin));

            return null;
        }
        $namespace = $plugin ?? trim((string)($this->params['namespace'] ?? 'App'), '\\');
        if (!preg_match('/^[A-Z][A-Za-z0-9_]*(\\\\[A-Z][A-Za-z0-9_]*)*$/', $namespace)) {
            $this->error(__d('cake_console', 'Not a namespace: %s', $namespace));

            return null;
        }
        $root = $plugin !== null ? $this->_pluginPath($plugin) : $this->_projectRoot();
        if ($root === null) {
            return null;
        }
        $legacy = NamespacePlan::legacyLayout($root, $plugin !== null);
        if ($legacy !== null) {
            $this->error(__d('cake_console', '%s is in the CakePHP 2 layout (%s): run bin/cake upgrade layout%s first.', $plugin ?? 'The application', $legacy, $plugin !== null ? ' --plugin ' . $plugin : ''));

            return null;
        }
        if ($plugin === null && !is_file($root . DS . 'config' . DS . 'core.php')) {
            $this->error(__d('cake_console', 'No config/core.php in %s: run the command from the root of the application.', $root));

            return null;
        }

        return [$root, $namespace];
    }

    /**
     * Runs the rules of `upgrade namespace` on the code (`src/`, `tests/`), then on `config/` and `templates/`, in
     * which the classes of CakePHP keep their global names
     *
     * @param \PieceOfCake2\Upgrade\Migration\NamespacePlan $plan The plan
     * @param string|null $namespace App.namespace of an application, or null for a plugin
     * @return int The exit code of Rector
     */
    protected function _runNamespaceRules(NamespacePlan $plan, $namespace)
    {
        $indexPaths = $plan->codePaths();
        foreach (CakePlugin::loaded() as $loaded) {
            $src = CakePlugin::path($loaded) . 'src';
            if (is_dir($src) && !str_starts_with($src, $plan->root . DS)) {
                $indexPaths[] = $src;
            }
        }
        $appNamespace = $namespace ?? Configure::read('App.namespace');
        $runs = [
            [$plan->codePaths(), true, ['php']],
            [$plan->configPaths(), false, ['php', 'ctp']],
        ];
        $code = 0;
        foreach ($runs as [$paths, $renameCore, $extensions]) {
            if ($paths === []) {
                continue;
            }
            $file = (string)tempnam(TMP, 'upgrade-plan-');
            file_put_contents($file, json_encode([
                'files' => $renameCore ? $plan->files : [],
                'aliases' => $renameCore ? $plan->aliases : [],
                'classes' => $plan->classes,
                'namespace' => is_string($appNamespace) && $appNamespace !== '' ? $appNamespace : null,
                'shortNames' => empty($this->params['no-short-names']),
                'indexPaths' => array_values(array_unique($indexPaths)),
                'renameCore' => $renameCore,
                'extensions' => $extensions,
            ]));
            $command = [
                PHP_BINARY,
                InstalledVersions::getInstallPath('rector/rector') . DS . 'bin' . DS . 'rector',
                'process',
                ...$paths,
                '--config',
                $this->_rectorConfig('namespace' . DS . 'namespace'),
                '--clear-cache',
                '--no-diffs',
            ];
            try {
                $result = $this->_runRectorWithReport($command, ['UPGRADE_NAMESPACE_PLAN' => $file]);
            } finally {
                unlink($file);
            }
            if ($result !== 0) {
                $code = $result;
                break;
            }
        }

        return $code;
    }

    /**
     * Adds App.namespace to config/core.php
     *
     * @param string $root The root of the application (or its copy for a dry run)
     * @param string $namespace The namespace
     * @return void
     */
    protected function _addAppNamespace($root, $namespace)
    {
        $file = $root . DS . 'config' . DS . 'core.php';
        [$result, $contents, $detail] = CoreConfig::addNamespace((string)file_get_contents($file), $namespace);
        if ($result === CoreConfig::SAME) {
            $this->out(__d('cake_console', 'config/core.php already sets App.namespace to %s.', $namespace));

            return;
        }
        if ($result === CoreConfig::DIFFERENT) {
            $this->out(__d('cake_console', '<warning>config/core.php sets App.namespace to %s, not %s: the classes are now in %s. Change one of them.</warning>', $detail, $namespace, $namespace));

            return;
        }
        if ($result === CoreConfig::NO_PLACE) {
            $this->out(__d('cake_console', '<warning>config/core.php has neither App.encoding nor Cache::config(): add Configure::write(\'App.namespace\', \'%s\'); to it by hand, before the caches are configured.</warning>', $namespace));

            return;
        }
        $this->out(__d('cake_console', 'Add Configure::write(\'App.namespace\', \'%s\'); to config/core.php, before %s', $namespace, $detail));
        file_put_contents($file, $contents);
    }

    /**
     * Switches the autoloading of composer.json to PSR-4: the composer.json of the application, or of the plugin
     * (else the one of the application, with the path of the plugin)
     *
     * @param \PieceOfCake2\Upgrade\Migration\NamespacePlan $plan The plan
     * @param string $root The directory of the application or plugin
     * @param string $work The directory of the plan (a copy of the root for a dry run)
     * @param bool $dryRun Whether to only show the changes
     * @return void
     */
    protected function _updateComposerJson(NamespacePlan $plan, $root, $work, $dryRun)
    {
        $target = $this->_composerFile($plan, $root, $work);
        if ($target === null) {
            return;
        }
        [$file, $prefix] = $target;
        $composer = new ComposerJson((string)file_get_contents($file));
        $name = $prefix === '' ? 'composer.json' : 'composer.json of the application';
        $changes = $this->_switchToPsr4($composer, $plan, $prefix);
        $paths = [];
        foreach ($plan->paths as $from => $to) {
            $paths[$prefix . $from] = $prefix . $to;
        }
        $this->_moveComposerPaths($composer, $paths, $name);
        if ($changes === []) {
            $this->out(__d('cake_console', '%s already autoloads %s with PSR-4.', $name, $plan->namespace));
        } else {
            $this->out(__d('cake_console', 'Update %s: %s', $name, implode('; ', $changes)));
        }
        // In a dry run, the composer.json of the copy is changed, not the one of the application
        if ((!$dryRun || $prefix === '') && $composer->isChanged()) {
            file_put_contents($file, $composer->contents());
        }
    }

    /**
     * The composer.json to change: of the application or plugin, or of the application for a plugin without one
     *
     * @param \PieceOfCake2\Upgrade\Migration\NamespacePlan $plan The plan
     * @param string $root The directory of the application or plugin
     * @param string $work The directory of the plan (a copy of the root for a dry run)
     * @return array{string, string}|null The file, and the path of the plugin in the application ('' or 'plugins/Blog/')
     */
    protected function _composerFile(NamespacePlan $plan, $root, $work)
    {
        $file = $work . DS . 'composer.json';
        $prefix = '';
        if ($plan->plugin && !is_file($file)) {
            $project = (string)$this->_projectRoot(false);
            if (!str_starts_with($root, $project . DS)) {
                $this->out(__d('cake_console', '<warning>No composer.json: autoload %s\\ from src/ and %s\\Test\\ from tests/ of the plugin with PSR-4.</warning>', $plan->namespace, $plan->namespace));

                return null;
            }
            $file = $project . DS . 'composer.json';
            $prefix = str_replace(DS, '/', substr($root, strlen($project) + 1)) . '/';
        }
        if (!is_file($file)) {
            $this->out(__d('cake_console', '<warning>No composer.json: autoload %s\\ from %ssrc/ and %s\\Test\\ from %stests/ with PSR-4.</warning>', $plan->namespace, $prefix, $plan->namespace, $prefix));

            return null;
        }

        return [$file, $prefix];
    }

    /**
     * PSR-4 instead of the classmap of the code, also of the moved directories (Controller/ of a plugin), and the
     * classmap of the core class overrides
     *
     * @param \PieceOfCake2\Upgrade\Migration\ComposerJson $composer composer.json
     * @param \PieceOfCake2\Upgrade\Migration\NamespacePlan $plan The plan
     * @param string $prefix The path of the plugin in the application, or ''
     * @return array<string> The changes
     */
    protected function _switchToPsr4(ComposerJson $composer, NamespacePlan $plan, $prefix)
    {
        $changes = [];
        foreach (['autoload', 'autoload-dev'] as $section) {
            foreach (array_merge(['src/', 'tests/'], array_keys($plan->paths)) as $dir) {
                foreach ($composer->removeClassmap($section, $prefix . $dir, [$prefix . 'src/Cake/']) as $removed) {
                    $changes[] = sprintf('%s.classmap: removed %s', $section, $removed);
                }
            }
        }
        foreach ([['autoload', 'src/', ''], ['autoload-dev', 'tests/', 'Test\\']] as [$section, $dir, $suffix]) {
            if ($composer->addPsr4($section, $plan->namespace . '\\' . $suffix, $prefix . $dir)) {
                $changes[] = sprintf('%s.psr-4: %s\\%s => %s%s', $section, $plan->namespace, $suffix, $prefix, $dir);
            }
        }
        if ($plan->overridden !== [] && $composer->addToList('autoload', 'classmap', 'src/Cake/')) {
            $changes[] = 'autoload.classmap: src/Cake/';
        }
        $vendor = rtrim((string)($composer->config('vendor-dir') ?: 'vendor'), '/');
        foreach ($plan->overridden as $overridden) {
            $excluded = $vendor . '/pieceofcake2/cakephp/src/' . $overridden;
            if ($composer->addToList('autoload', 'exclude-from-classmap', $excluded)) {
                $changes[] = 'autoload.exclude-from-classmap: ' . $excluded;
            }
        }

        return $changes;
    }

    /**
     * Updates the paths of composer.json that are in moved directories, and prints the changes
     *
     * @param \PieceOfCake2\Upgrade\Migration\ComposerJson $composer composer.json
     * @param array<string, string> $paths The new paths, by old path
     * @param string $name The name of the file in the messages
     * @return void
     */
    protected function _moveComposerPaths(ComposerJson $composer, array $paths, $name)
    {
        [$changes, $unknown] = $composer->movePaths($paths);
        if ($changes !== []) {
            $this->out(__d('cake_console', 'Update %s: %s', $name, implode('; ', $changes)));
        }
        if ($unknown !== []) {
            $this->out(__d('cake_console', '<warning>Update these paths of %s by hand, as their files moved to different places: %s</warning>', $name, implode('; ', $unknown)));
        }
    }

    /**
     * A copy of the files that `upgrade namespace` changes, for a dry run
     *
     * @param string $root The directory of the application or plugin
     * @param string $name The name of the copy
     * @return string The directory of the copy
     */
    protected function _copyForDryRun($root, $name)
    {
        $copy = TMP . 'upgrade-dry-run-' . getmypid() . DS . $name . DS . basename($root);
        $skip = ['vendor', 'node_modules', '.git', 'tmp', 'logs', 'webroot', 'plugins'];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                static fn(SplFileInfo $file, $key, RecursiveDirectoryIterator $iterator): bool => !($iterator->getSubPath() === '' && $file->isDir() && in_array($file->getFilename(), $skip, true)),
            ),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        mkdir($copy, 0777, true);
        foreach ($iterator as $file) {
            $target = $copy . DS . substr($file->getPathname(), strlen($root) + 1);
            if ($file->isDir()) {
                mkdir($target, 0777, true);
            } else {
                copy($file->getPathname(), $target);
            }
        }

        return realpath($copy) ?: $copy;
    }

    /**
     * The changes between two directories, as a diff
     *
     * @param string $before The directory before the changes
     * @param string $after The directory after the changes
     * @return string
     */
    protected function _diff($before, $after)
    {
        $output = [];
        $command = sprintf('git diff --no-index --no-color --no-prefix -- %s %s 2>&1', escapeshellarg($before), escapeshellarg($after));
        exec($command, $output, $code);
        if ($code > 1) {
            $output = [];
            exec(sprintf('diff -ruN %s %s 2>&1', escapeshellarg($before), escapeshellarg($after)), $output);
        }

        return str_replace([$before . DS, $after . DS, ltrim($before, DS) . DS, ltrim($after, DS) . DS], '', implode("\n", $output));
    }

    /**
     * The root of the project: the current directory
     *
     * @param bool $check Whether to print an error if it holds no application
     * @return string|null
     */
    protected function _projectRoot($check = true)
    {
        $root = (string)realpath((string)getcwd());
        if ($check && !Paths::hasDirectory($root, 'app') && !Paths::hasDirectory($root, 'config')) {
            $this->error(__d('cake_console', 'Neither app/ nor config/ in %s: run the command from the root of the project.', $root));

            return null;
        }

        return $root;
    }

    /**
     * The directory of a plugin, loaded or in a directory of plugins
     *
     * @param string $plugin The name of the plugin
     * @return string|null
     */
    protected function _pluginPath($plugin)
    {
        if (CakePlugin::loaded($plugin)) {
            return rtrim((string)realpath(CakePlugin::path($plugin)), DS);
        }
        foreach (App::path('plugins') as $path) {
            if (is_dir($path . $plugin)) {
                return rtrim((string)realpath($path . $plugin), DS);
            }
        }
        $this->error(__d('cake_console', 'The plugin %s was not found.', $plugin));

        return null;
    }

    /**
     * Prints what the user has to check or do by hand
     *
     * @param array<string> $notices The notices
     * @return void
     */
    protected function _notices(array $notices)
    {
        foreach ($notices as $notice) {
            $this->out(__d('cake_console', '<warning>%s</warning>', $notice));
        }
    }

    /**
     * Update tests.
     *
     * - Update tests class names to FooTest rather than FooTestCase.
     *
     * @return void
     */
    public function tests()
    {
        $this->_paths = [APP . 'tests' . DS];
        if (!empty($this->params['plugin'])) {
            $this->_paths = [CakePlugin::path($this->params['plugin']) . 'tests' . DS];
        }
        $patterns = [
            [
                '*TestCase extends CakeTestCase to *Test extends CakeTestCase',
                '/([a-zA-Z]*Test)Case extends CakeTestCase/',
                '\1 extends CakeTestCase',
            ],
        ];

        $this->_filesRegexpUpdate($patterns);
    }

    /**
     * Move files and folders to their new homes
     *
     * Moves folders containing files which cannot necessarily be auto-detected (libs and templates)
     * and then looks for all php files except vendors, and moves them to where Cake 2.0 expects
     * to find them.
     *
     * @return void
     */
    public function locations()
    {
        $cwd = getcwd();

        if (!empty($this->params['plugin'])) {
            chdir(CakePlugin::path($this->params['plugin']));
        }

        if (is_dir('plugins')) {
            $Folder = new Folder('plugins');
            [$plugins] = $Folder->read();
            foreach ($plugins as $plugin) {
                chdir($cwd . DS . 'plugins' . DS . $plugin);
                $this->out(__d('cake_console', 'Upgrading locations for plugin %s', $plugin));
                $this->locations();
            }
            $this->_files = [];
            chdir($cwd);
            $this->out(__d('cake_console', 'Upgrading locations for app directory'));
        }
        $moves = [
            'config' => 'Config',
            'Config' . DS . 'schema' => 'Config' . DS . 'Schema',
            'libs' => 'Lib',
            'tests' => 'Test',
            'views' => 'View',
            'models' => 'Model',
            'Model' . DS . 'behaviors' => 'Model' . DS . 'Behavior',
            'Model' . DS . 'datasources' => 'Model' . DS . 'Datasource',
            'Test' . DS . 'cases' => 'Test' . DS . 'Case',
            'Test' . DS . 'fixtures' => 'Test' . DS . 'Fixture',
            'vendors' . DS . 'shells' . DS . 'templates' => 'Console' . DS . 'Templates',
        ];
        foreach ($moves as $old => $new) {
            if (is_dir($old)) {
                $this->out(__d('cake_console', 'Moving %s to %s', $old, $new));
                if (!$this->params['dry-run']) {
                    $this->_moveFolder($old, $new);
                }
            }
        }

        $this->_moveViewFiles();
        $this->_moveAppClasses();

        $sourceDirs = [
            '.' => ['recursive' => false],
            'Console',
            'controllers',
            'Controller',
            'Lib' => ['checkFolder' => false],
            'models',
            'Model',
            'tests',
            'Test' => ['regex' => '@class (\S*Test) extends CakeTestCase@'],
            'views',
            'View',
            'vendors/shells',
        ];

        $defaultOptions = [
            'recursive' => true,
            'checkFolder' => true,
            'regex' => '@class (\S*) .*(\s|\v)*{@i',
        ];
        foreach ($sourceDirs as $dir => $options) {
            if (is_numeric($dir)) {
                $dir = $options;
                $options = [];
            }
            $options += $defaultOptions;
            $this->_movePhpFiles($dir, $options);
        }
    }

    /**
     * Update helpers.
     *
     * - Converts helpers usage to new format.
     *
     * @return void
     */
    public function helpers()
    {
        $this->_paths = array_diff(App::path('views'), App::core('views'));

        if (!empty($this->params['plugin'])) {
            $this->_paths = [CakePlugin::path($this->params['plugin']) . 'views' . DS];
        }

        $patterns = [];
        App::build([
            'View/Helper' => App::core('View/Helper'),
        ], App::APPEND);
        $helpers = App::objects('helper');
        $plugins = App::objects('plugin');
        $pluginHelpers = [];
        foreach ($plugins as $plugin) {
            CakePlugin::load($plugin);
            $pluginHelpers = array_merge(
                $pluginHelpers,
                App::objects('helper', CakePlugin::path($plugin) . DS . 'views' . DS . 'helpers' . DS, false),
            );
        }
        $helpers = array_merge($pluginHelpers, $helpers);
        foreach ($helpers as $helper) {
            $helper = preg_replace('/Helper$/', '', $helper);
            $oldHelper = $helper;
            $oldHelper[0] = strtolower($oldHelper[0]);
            $patterns[] = [
                "\${$oldHelper} to \$this->{$helper}",
                "/\\\${$oldHelper}->/",
                "\\\$this->{$helper}->",
            ];
        }

        $this->_filesRegexpUpdate($patterns);
    }

    /**
     * Update i18n.
     *
     * - Removes extra true param.
     * - Add the echo to __*() calls that didn't need them before.
     *
     * @return void
     */
    public function i18n()
    {
        $this->_paths = [
            APP,
        ];
        if (!empty($this->params['plugin'])) {
            $this->_paths = [CakePlugin::path($this->params['plugin'])];
        }

        $patterns = [
            [
                '<?php __*(*) to <?php echo __*(*)',
                '/<\?php\s*(__[a-z]*\(.*?\))/',
                '<?php echo \1',
            ],
            [
                '<?php __*(*, true) to <?php echo __*()',
                '/<\?php\s*(__[a-z]*\(.*?)(,\s*true)(\))/',
                '<?php echo \1\3',
            ],
            ['__*(*, true) to __*(*)', '/(__[a-z]*\(.*?)(,\s*true)(\))/', '\1\3'],
        ];

        $this->_filesRegexpUpdate($patterns);
    }

    /**
     * Upgrade the removed basics functions.
     *
     * - a(*) -> array(*)
     * - e(*) -> echo *
     * - ife(*, *, *) -> !empty(*) ? * : *
     * - a(*) -> array(*)
     * - r(*, *, *) -> str_replace(*, *, *)
     * - up(*) -> strtoupper(*)
     * - low(*, *, *) -> strtolower(*)
     * - getMicrotime() -> microtime(true)
     *
     * @return void
     */
    public function basics()
    {
        $this->_paths = [
            APP,
        ];
        if (!empty($this->params['plugin'])) {
            $this->_paths = [CakePlugin::path($this->params['plugin'])];
        }
        $patterns = [
            [
                'a(*) -> array(*)',
                '/\ba\((.*)\)/',
                'array(\1)',
            ],
            [
                'e(*) -> echo *',
                '/\be\((.*)\)/',
                'echo \1',
            ],
            [
                'ife(*, *, *) -> !empty(*) ? * : *',
                '/ife\((.*), (.*), (.*)\)/',
                '!empty(\1) ? \2 : \3',
            ],
            [
                'r(*, *, *) -> str_replace(*, *, *)',
                '/\br\(/',
                'str_replace(',
            ],
            [
                'up(*) -> strtoupper(*)',
                '/\bup\(/',
                'strtoupper(',
            ],
            [
                'low(*) -> strtolower(*)',
                '/\blow\(/',
                'strtolower(',
            ],
            [
                'getMicrotime() -> microtime(true)',
                '/getMicrotime\(\)/',
                'microtime(true)',
            ],
        ];
        $this->_filesRegexpUpdate($patterns);
    }

    /**
     * Update the properties moved to CakeRequest.
     *
     * @return void
     */
    public function request()
    {
        $views = array_diff(App::path('views'), App::core('views'));
        $controllers = array_diff(App::path('controllers'), App::core('controllers'), [APP]);
        $components = array_diff(App::path('components'), App::core('components'));

        $this->_paths = array_merge($views, $controllers, $components);

        if (!empty($this->params['plugin'])) {
            $pluginPath = CakePlugin::path($this->params['plugin']);
            $this->_paths = [
                $pluginPath . 'controllers' . DS,
                $pluginPath . 'controllers' . DS . 'components' . DS,
                $pluginPath . 'views' . DS,
            ];
        }
        $patterns = [
            [
                '$this->data -> $this->request->data',
                '/(\$this->data\b(?!\())/',
                '$this->request->data',
            ],
            [
                '$this->params -> $this->request->params',
                '/(\$this->params\b(?!\())/',
                '$this->request->params',
            ],
            [
                '$this->webroot -> $this->request->webroot',
                '/(\$this->webroot\b(?!\())/',
                '$this->request->webroot',
            ],
            [
                '$this->base -> $this->request->base',
                '/(\$this->base\b(?!\())/',
                '$this->request->base',
            ],
            [
                '$this->here -> $this->request->here',
                '/(\$this->here\b(?!\())/',
                '$this->request->here',
            ],
            [
                '$this->action -> $this->request->action',
                '/(\$this->action\b(?!\())/',
                '$this->request->action',
            ],
            [
                '$this->request->onlyAllow() -> $this->request->allowMethod()',
                '/\$this->request->onlyAllow\(/',
                '$this->request->allowMethod(',
            ],
        ];
        $this->_filesRegexpUpdate($patterns);
    }

    /**
     * Update Configure::read() calls with no params.
     *
     * @return void
     */
    public function configure()
    {
        $this->_paths = [
            APP,
        ];
        if (!empty($this->params['plugin'])) {
            $this->_paths = [CakePlugin::path($this->params['plugin'])];
        }
        $patterns = [
            [
                "Configure::read() -> Configure::read('debug')",
                '/Configure::read\(\)/',
                'Configure::read(\'debug\')',
            ],
        ];
        $this->_filesRegexpUpdate($patterns);
    }

    /**
     * constants
     *
     * @return void
     */
    public function constants()
    {
        $this->_paths = [
            APP,
        ];
        if (!empty($this->params['plugin'])) {
            $this->_paths = [CakePlugin::path($this->params['plugin'])];
        }
        $patterns = [
            [
                'LIBS -> CAKE',
                '/\bLIBS\b/',
                'CAKE',
            ],
            [
                "CONFIGS -> APP . 'Config' . DS",
                '/\bCONFIGS\b/',
                'APP . \'Config\' . DS',
            ],
            [
                "CONTROLLERS -> APP . 'Controller' . DS",
                '/\bCONTROLLERS\b/',
                'APP . \'Controller\' . DS',
            ],
            [
                "COMPONENTS -> APP . 'Controller' . DS . 'Component' . DS",
                '/\bCOMPONENTS\b/',
                'APP . \'Controller\' . DS . \'Component\'',
            ],
            [
                "MODELS -> APP . 'Model' . DS",
                '/\bMODELS\b/',
                'APP . \'Model\' . DS',
            ],
            [
                "BEHAVIORS -> APP . 'Model' . DS . 'Behavior' . DS",
                '/\bBEHAVIORS\b/',
                'APP . \'Model\' . DS . \'Behavior\' . DS',
            ],
            [
                "VIEWS -> APP . 'View' . DS",
                '/\bVIEWS\b/',
                'APP . \'View\' . DS',
            ],
            [
                "HELPERS -> APP . 'View' . DS . 'Helper' . DS",
                '/\bHELPERS\b/',
                'APP . \'View\' . DS . \'Helper\' . DS',
            ],
            [
                "LAYOUTS -> APP . 'View' . DS . 'Layouts' . DS",
                '/\bLAYOUTS\b/',
                'APP . \'View\' . DS . \'Layouts\' . DS',
            ],
            [
                "ELEMENTS -> APP . 'View' . DS . 'Elements' . DS",
                '/\bELEMENTS\b/',
                'APP . \'View\' . DS . \'Elements\' . DS',
            ],
            [
                "CONSOLE_LIBS -> CAKE . 'Console' . DS",
                '/\bCONSOLE_LIBS\b/',
                'CAKE . \'Console\' . DS',
            ],
            [
                "CAKE_TESTS_LIB -> CAKE . 'TestSuite' . DS",
                '/\bCAKE_TESTS_LIB\b/',
                'CAKE . \'TestSuite\' . DS',
            ],
            [
                "CAKE_TESTS -> CAKE . 'Test' . DS",
                '/\bCAKE_TESTS\b/',
                'CAKE . \'Test\' . DS',
            ],
        ];
        $this->_filesRegexpUpdate($patterns);
    }

    /**
     * Update controller redirects.
     *
     * - Make redirect statements return early.
     *
     * @return void
     */
    public function controller_redirects()
    {
        $this->_paths = App::Path('Controller');
        if (!empty($this->params['plugin'])) {
            $this->_paths = App::Path('Controller', $this->params['plugin']);
        }
        $patterns = [
            [
                '$this->redirect() to return $this->redirect()',
                '/\t\$this-\>redirect\(/',
                "\t" . 'return $this->redirect(',
            ],
        ];

        $this->_filesRegexpUpdate($patterns);
    }

    /**
     * Update components.
     *
     * - Make components that extend CakeObject to extend Component.
     *
     * @return void
     */
    public function components()
    {
        $this->_paths = App::Path('Controller/Component');
        if (!empty($this->params['plugin'])) {
            $this->_paths = App::Path('Controller/Component', $this->params['plugin']);
        }
        $patterns = [
            [
                '*Component extends Object to *Component extends Component',
                '/([a-zA-Z]*Component extends) Object/',
                '\1 Component',
            ],
            [
                '*Component extends CakeObject to *Component extends Component',
                '/([a-zA-Z]*Component extends) CakeObject/',
                '\1 Component',
            ],
        ];

        $this->_filesRegexpUpdate($patterns);
    }

    /**
     * Replace cakeError with built-in exceptions.
     * NOTE: this ignores calls where you've passed your own secondary parameters to cakeError().
     *
     * @return void
     */
    public function exceptions()
    {
        $controllers = array_diff(App::path('controllers'), App::core('controllers'), [APP]);
        $components = array_diff(App::path('components'), App::core('components'));

        $this->_paths = array_merge($controllers, $components);

        if (!empty($this->params['plugin'])) {
            $pluginPath = CakePlugin::path($this->params['plugin']);
            $this->_paths = [
                $pluginPath . 'controllers' . DS,
                $pluginPath . 'controllers' . DS . 'components' . DS,
            ];
        }
        $patterns = [
            [
                '$this->cakeError("error400") -> throw new BadRequestException()',
                '/(\$this->cakeError\(["\']error400["\']\));/',
                'throw new BadRequestException();',
            ],
            [
                '$this->cakeError("error404") -> throw new NotFoundException()',
                '/(\$this->cakeError\(["\']error404["\']\));/',
                'throw new NotFoundException();',
            ],
            [
                '$this->cakeError("error500") -> throw new InternalErrorException()',
                '/(\$this->cakeError\(["\']error500["\']\));/',
                'throw new InternalErrorException();',
            ],
        ];
        $this->_filesRegexpUpdate($patterns);
    }

    /**
     * Move application views files to where they now should be
     *
     * Find all view files in the folder and determine where cake expects the file to be
     *
     * @return void
     */
    protected function _moveViewFiles()
    {
        if (!is_dir('View')) {
            return;
        }

        $dirs = scandir('View');
        foreach ($dirs as $old) {
            if (!is_dir('View' . DS . $old) || $old === '.' || $old === '..') {
                continue;
            }

            $new = 'View' . DS . Inflector::camelize($old);
            $old = 'View' . DS . $old;
            if ($new === $old) {
                continue;
            }

            $this->out(__d('cake_console', 'Moving %s to %s', $old, $new));
            if (!$this->params['dry-run']) {
                $this->_moveFolder($old, $new);
            }
        }
    }

    /**
     * Move a folder relative to the current working directory.
     *
     * The folder is renamed through a temporary name first, so that case-only renames
     * (e.g. `config` to `Config`) also work on case-insensitive filesystems.
     * If the destination already exists, the contents are merged into it.
     *
     * @param string $old The folder to move.
     * @param string $new The destination folder.
     * @return void
     */
    protected function _moveFolder($old, $new)
    {
        $tmp = $old . '__';
        if ($this->params['git']) {
            exec('git mv -f ' . escapeshellarg($old) . ' ' . escapeshellarg($tmp));
            exec('git mv -f ' . escapeshellarg($tmp) . ' ' . escapeshellarg($new));

            return;
        }

        rename($old, $tmp);
        if (!is_dir($new)) {
            rename($tmp, $new);

            return;
        }

        $cwd = getcwd() . DS;
        $Folder = new Folder($cwd . $tmp);
        $Folder->move(['to' => $cwd . $new]);
    }

    /**
     * Move the AppController, and AppModel classes.
     *
     * @return void
     */
    protected function _moveAppClasses()
    {
        $files = [
            APP . 'app_controller.php' => APP . 'Controller' . DS . 'AppController.php',
            APP . 'controllers' . DS . 'app_controller.php' => APP . 'Controller' . DS . 'AppController.php',
            APP . 'app_model.php' => APP . 'Model' . DS . 'AppModel.php',
            APP . 'models' . DS . 'app_model.php' => APP . 'Model' . DS . 'AppModel.php',
        ];
        foreach ($files as $old => $new) {
            if (file_exists($old)) {
                $this->out(__d('cake_console', 'Moving %s to %s', $old, $new));

                if ($this->params['dry-run']) {
                    continue;
                }
                if ($this->params['git']) {
                    exec('git mv -f ' . escapeshellarg($old) . ' ' . escapeshellarg($old . '__'));
                    exec('git mv -f ' . escapeshellarg($old . '__') . ' ' . escapeshellarg($new));
                } else {
                    rename($old, $new);
                }
            }
        }
    }

    /**
     * Move application php files to where they now should be
     *
     * Find all php files in the folder (honoring recursive) and determine where CakePHP expects the file to be
     * If the file is not exactly where CakePHP expects it - move it.
     *
     * @param string $path The path to move files in.
     * @param array $options array(recursive, checkFolder)
     * @return void
     */
    protected function _movePhpFiles($path, $options)
    {
        if (!is_dir($path)) {
            return;
        }

        $paths = $this->_paths;

        $this->_paths = [$path];
        $this->_files = [];
        if ($options['recursive']) {
            $this->_findFiles('php');
        } else {
            $this->_files = scandir($path);
            foreach ($this->_files as $i => $file) {
                if (strlen($file) < 5 || !str_ends_with($file, '.php')) {
                    unset($this->_files[$i]);
                }
            }
        }

        $cwd = getcwd();
        foreach ($this->_files as &$file) {
            $file = $cwd . DS . $file;

            $contents = file_get_contents($file);
            preg_match($options['regex'], $contents, $match);
            if (!$match) {
                continue;
            }

            $class = $match[1];

            if (str_starts_with($class, 'Dbo')) {
                $type = 'Dbo';
            } else {
                preg_match('@([A-Z][^A-Z]*)$@', $class, $match);
                if ($match) {
                    $type = $match[1];
                } else {
                    $type = 'unknown';
                }
            }

            preg_match('@^.*[\\\/]plugins[\\\/](.*?)[\\\/]@', $file, $match);
            $base = $cwd . DS;
            if ($match) {
                $base = $match[0];
            }

            if ($options['checkFolder'] && !empty($this->_map[$type])) {
                $folder = str_replace('/', DS, $this->_map[$type]);
                $new = $base . $folder . DS . $class . '.php';
            } else {
                $new = dirname($file) . DS . $class . '.php';
            }

            if ($file === $new) {
                continue;
            }

            $dir = dirname($new);
            if (!is_dir($dir)) {
                new Folder($dir, true);
            }

            $this->out(__d('cake_console', 'Moving %s to %s', $file, $new), 1, Shell::VERBOSE);
            if (!$this->params['dry-run']) {
                if ($this->params['git']) {
                    exec('git mv -f ' . escapeshellarg($file) . ' ' . escapeshellarg($file . '__'));
                    exec('git mv -f ' . escapeshellarg($file . '__') . ' ' . escapeshellarg($new));
                } else {
                    rename($file, $new);
                }
            }
        }

        $this->_paths = $paths;
    }

    /**
     * Updates files based on regular expressions.
     *
     * @param array $patterns Array of search and replacement patterns.
     * @return void
     */
    protected function _filesRegexpUpdate($patterns)
    {
        $this->_findFiles($this->params['ext']);
        foreach ($this->_files as $file) {
            $this->out(__d('cake_console', 'Updating %s...', $file), 1, Shell::VERBOSE);
            $this->_updateFile($file, $patterns);
        }
    }

    /**
     * Searches the paths and finds files based on extension.
     *
     * @param string $extensions The extensions to include. Defaults to none.
     * @return void
     */
    protected function _findFiles($extensions = '')
    {
        $this->_files = [];
        foreach ($this->_paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            $Iterator = new RegexIterator(
                new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)),
                '/^.+\.(' . $extensions . ')$/i',
                RegexIterator::MATCH,
            );
            foreach ($Iterator as $file) {
                if ($file->isFile()) {
                    $this->_files[] = $file->getPathname();
                }
            }
        }
    }

    /**
     * Update a single file.
     *
     * @param string $file The file to update
     * @param array $patterns The replacement patterns to run.
     * @return void
     */
    protected function _updateFile($file, $patterns)
    {
        $contents = file_get_contents($file);

        foreach ($patterns as $pattern) {
            $this->out(__d('cake_console', ' * Updating %s', $pattern[0]), 1, Shell::VERBOSE);
            $contents = preg_replace($pattern[1], $pattern[2], $contents);
        }

        $this->out(__d('cake_console', 'Done updating %s', $file), 1);
        if (!$this->params['dry-run']) {
            file_put_contents($file, $contents);
        }
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @return ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();

        $subcommandParser = [
            'options' => [
                'plugin' => [
                    'short' => 'p',
                    'help' => __d('cake_console', 'The plugin to update. Only the specified plugin will be updated.'),
                ],
                'ext' => [
                    'short' => 'e',
                    'help' => __d('cake_console', 'The extension(s) to search. A pipe delimited list, or a preg_match compatible subpattern'),
                    'default' => 'php|ctp|thtml|inc|tpl',
                ],
                'git' => [
                    'short' => 'g',
                    'help' => __d('cake_console', 'Use git command for moving files around.'),
                    'boolean' => true,
                ],
                'dry-run' => [
                    'short' => 'd',
                    'help' => __d('cake_console', 'Dry run the update, no files will actually be modified.'),
                    'boolean' => true,
                ],
            ],
        ];

        $parser->description(
            __d('cake_console', 'A tool to help automate upgrading an application or plugin ' .
            'from CakePHP 1.3 to 2.0. Be sure to have a backup of your application before ' .
            'running these commands.',),
        )->addSubcommand('all', [
            'help' => __d('cake_console', 'Run all upgrade commands.'),
            'parser' => $subcommandParser,
            ])->addSubcommand('tests', [
            'help' => __d('cake_console', 'Update tests class names to FooTest rather than FooTestCase.'),
            'parser' => $subcommandParser,
            ])->addSubcommand('locations', [
            'help' => __d('cake_console', 'Move files and folders to their new homes.'),
            'parser' => $subcommandParser,
            ])->addSubcommand('i18n', [
            'help' => __d('cake_console', 'Update the i18n translation method calls.'),
            'parser' => $subcommandParser,
            ])->addSubcommand('helpers', [
            'help' => __d('cake_console', 'Update calls to helpers.'),
            'parser' => $subcommandParser,
            ])->addSubcommand('basics', [
            'help' => __d('cake_console', 'Update removed basics functions to PHP native functions.'),
            'parser' => $subcommandParser,
            ])->addSubcommand('request', [
            'help' => __d('cake_console', 'Update removed request access, and replace with $this->request.'),
            'parser' => $subcommandParser,
            ])->addSubcommand('configure', [
            'help' => __d('cake_console', "Update Configure::read() to Configure::read('debug')"),
            'parser' => $subcommandParser,
            ])->addSubcommand('constants', [
            'help' => __d('cake_console', 'Replace Obsolete constants'),
            'parser' => $subcommandParser,
            ])->addSubcommand('controller_redirects', [
            'help' => __d('cake_console', 'Return early on controller redirect calls.'),
            'parser' => $subcommandParser,
            ])->addSubcommand('components', [
            'help' => __d('cake_console', 'Update components to extend Component class.'),
            'parser' => $subcommandParser,
            ])->addSubcommand('exceptions', [
            'help' => __d('cake_console', 'Replace use of cakeError with exceptions.'),
            'parser' => $subcommandParser,
            ])->addSubcommand('rector', [
            'help' => __d('cake_console', 'Run a set of Rector rules for a change of pieceofcake2/cakephp on the directories or files of the arguments, e.g. src tests. Not part of all.'),
            'parser' => [
                'options' => [
                    'rules' => [
                        'short' => 'r',
                        'help' => __d('cake_console', 'The set of rules, a file of config/rector/ of the plugin.'),
                        'default' => 'variadic-arguments',
                    ],
                    'dry-run' => [
                        'short' => 'd',
                        'help' => __d('cake_console', 'Show the changes without writing them.'),
                        'boolean' => true,
                    ],
                    'no-short-names' => [
                        'help' => __d('cake_console', 'class-name-strings: keep the short names (\'Auth\', \'DebugKit.Toolbar\'), which CakePHP looks up when the application runs; only replace the full class names.'),
                        'boolean' => true,
                    ],
                ],
            ],
            ])->addSubcommand('layout', [
            'help' => __d('cake_console', 'Move an application from the CakePHP 2 layout (app/Config, app/Controller, app/View, ...) to the layout of pieceofcake2/app (config/, src/, templates/, ...), or the configuration, templates, translations and tests of a plugin. Not part of all.'),
            'parser' => [
                'options' => [
                    'plugin' => [
                        'short' => 'p',
                        'help' => __d('cake_console', 'The plugin to move, instead of the application.'),
                    ],
                    'dry-run' => [
                        'short' => 'd',
                        'help' => __d('cake_console', 'Show the moves without making them.'),
                        'boolean' => true,
                    ],
                ],
            ],
            ])->addSubcommand('namespace', [
            'help' => __d('cake_console', 'Move the classes of an application or plugin in the layout of pieceofcake2/app into a namespace: App.namespace, PSR-4 and the code. Run layout first. Not part of all.'),
            'parser' => [
                'options' => [
                    'plugin' => [
                        'short' => 'p',
                        'help' => __d('cake_console', 'The plugin to change, instead of the application. Its namespace is its name.'),
                    ],
                    'namespace' => [
                        'short' => 'n',
                        'help' => __d('cake_console', 'The namespace of the application (default: App).'),
                    ],
                    'no-short-names' => [
                        'help' => __d('cake_console', 'Keep the short names in the settings (\'Auth\', \'DebugKit.Toolbar\'), which CakePHP looks up when the application runs; only replace the full class names.'),
                        'boolean' => true,
                    ],
                    'dry-run' => [
                        'short' => 'd',
                        'help' => __d('cake_console', 'Show the moves and the changes without making them.'),
                        'boolean' => true,
                    ],
                ],
            ],
            ]);

        return $parser;
    }
}
