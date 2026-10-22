<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Migration;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The moves of `bin/cake upgrade layout`, from the CakePHP 2 layout to the layout of pieceofcake2/app.
 *
 * - An application: `app/Config/` to `config/`, the classes to `src/`, the templates to `templates/`, `app/Locale/` to
 *   `resources/locales/`, `app/Test/` to `tests/`, `app/Plugin/` to `plugins/`, `app/webroot/` to `webroot/`,
 *   `app/tmp/` to `tmp/` and `app/tmp/logs/` to `logs/`.
 * - A plugin: only `Config/`, the templates of `View/`, `Locale/` and `Test/`. Its `src/` holds namespaced classes
 *   only, so the class folders move into `src/` with `bin/cake upgrade namespace`.
 * - The templates (in both): `.ctp` and `.thtml` become `.php`, and `Layouts/`, `Elements/`, `Emails/` and `Errors/`
 *   become `layout/`, `element/`, `email/` and `Error/`, as pieceofcake2/cakephp 2.13 reads them in `templates/`.
 *   The scaffold templates keep `.ctp`, as ScaffoldView only reads `.ctp`.
 *
 * The moves are listed before any of them is made, so a move may name a path that an earlier move creates.
 */
final class LayoutPlan
{
    /**
     * The directories of the templates in CakePHP 5
     *
     * @var array<string, string>
     */
    public const TEMPLATE_DIRECTORIES = ['Layouts' => 'layout', 'Elements' => 'element', 'Emails' => 'email', 'Errors' => 'Error'];

    /**
     * The moves, absolute paths
     *
     * @var array<array{string, string}>
     */
    public array $moves = [];

    /**
     * The new paths of the moved files and directories (but the templates), by old path, relative to the root, for
     * the paths of composer.json
     *
     * @var array<string, string>
     */
    public array $paths = [];

    /**
     * What the user has to check or do by hand
     *
     * @var array<string>
     */
    public array $notices = [];

    /**
     * Whether `.thtml` templates are renamed
     *
     * @var bool
     */
    private bool $thtml = false;

    /**
     * @param string $root The directory of the application or plugin
     */
    private function __construct(public string $root)
    {
    }

    /**
     * The moves of an application, run from the root of the project, which holds `app/` in the CakePHP 2 layout
     *
     * @param string $root The root of the project
     * @return self
     */
    public static function forApplication(string $root): self
    {
        $plan = new self($root);
        $app = $root . DIRECTORY_SEPARATOR . 'app';
        if (Paths::hasDirectory($root, 'app') && Paths::hasDirectory($app, 'Config')) {
            $plan->planApplication($app);
        }
        $plan->planTemplates($root . DIRECTORY_SEPARATOR . 'templates', $root . DIRECTORY_SEPARATOR . 'templates');
        $plan->finish();

        return $plan;
    }

    /**
     * The moves of a plugin
     *
     * @param string $path The directory of the plugin
     * @return self
     */
    public static function forPlugin(string $path): self
    {
        $plan = new self($path);
        $plan->add('Config', 'config');
        $plan->planView($path . DIRECTORY_SEPARATOR . 'View', null);
        $plan->add('Locale', 'resources/locales');
        $plan->planTests('Test', 'tests');
        $plan->planTemplates($path . DIRECTORY_SEPARATOR . 'templates', $path . DIRECTORY_SEPARATOR . 'templates');
        $plan->finish();

        return $plan;
    }

    /**
     * The moves of the files of `app/`
     *
     * @param string $app The directory `app/`
     * @return void
     */
    private function planApplication(string $app): void
    {
        $this->add('app/Config', 'config');
        $this->add('app/Controller', 'src/Controller');
        $this->add('app/Model', 'src/Model');
        $this->add('app/Lib', 'src/Lib');
        $this->add('app/Console/Command', 'src/Console/Command');
        $this->add('app/Console/Templates', 'src/Console/Templates');
        $this->planView($app . DIRECTORY_SEPARATOR . 'View', 'src/View');
        $this->add('app/Locale', 'resources/locales');
        $this->planTests('app/Test', 'tests');
        foreach (Paths::entries($app . DIRECTORY_SEPARATOR . 'Plugin') as $plugin) {
            if (is_dir($app . DIRECTORY_SEPARATOR . 'Plugin' . DIRECTORY_SEPARATOR . $plugin)) {
                $this->add('app/Plugin/' . $plugin, 'plugins/' . $plugin);
            }
        }
        $this->paths += ['app/Plugin' => 'plugins'];
        $this->add('app/webroot', 'webroot');
        $this->add('app/tmp/logs', 'logs');
        $this->add('app/tmp', 'tmp');

        $moved = ['Config', 'Controller', 'Model', 'Lib', 'View', 'Locale', 'Test', 'webroot', 'tmp'];
        $left = [];
        foreach (Paths::entries($app) as $entry) {
            if (in_array($entry, $moved, true)) {
                continue;
            }
            if ($entry === 'Plugin') {
                $left = array_merge($left, array_map(static fn(string $file): string => 'app/Plugin/' . $file, array_filter(
                    Paths::entries($app . DIRECTORY_SEPARATOR . 'Plugin'),
                    static fn(string $file): bool => $file !== 'empty' && !is_dir($app . DIRECTORY_SEPARATOR . 'Plugin' . DIRECTORY_SEPARATOR . $file),
                )));
                continue;
            }
            if ($entry === 'Console') {
                foreach (Paths::entries($app . DIRECTORY_SEPARATOR . 'Console') as $file) {
                    if (!in_array($file, ['Command', 'Templates'], true)) {
                        $left[] = 'app/Console/' . $file;
                    }
                }
                continue;
            }
            $left[] = 'app/' . $entry;
        }
        if ($left !== []) {
            $this->notices[] = sprintf(
                'Not moved, move or replace them by hand: %s. Use Composer for the files of app/Vendor/, and the ' .
                'bin/cake of pieceofcake2/app for app/Console/cake.',
                implode(', ', $left),
            );
        }
        $this->notices[] = 'Copy config/define.php, bin/cake, webroot/index.php and webroot/test.php from ' .
            'pieceofcake2/app: the files of app/ define the paths of the CakePHP 2 layout.';
    }

    /**
     * The moves of `View/`: the templates to `templates/`, the helpers and the view classes to `src/View/` (of an
     * application; they stay in `View/` in a plugin until `upgrade namespace`)
     *
     * @param string $view The directory `View/`
     * @param string|null $classes Where the view classes and helpers go, relative to the root, or null to keep them
     * @return void
     */
    private function planView(string $view, ?string $classes): void
    {
        if (!Paths::hasDirectory(dirname($view), basename($view))) {
            return;
        }
        $relativeView = substr($view, strlen($this->root) + 1);
        foreach (Paths::entries($view) as $entry) {
            $path = $view . DIRECTORY_SEPARATOR . $entry;
            if ($entry === 'Helper' || (is_file($path) && str_ends_with($entry, '.php'))) {
                if ($classes !== null) {
                    $this->add($relativeView . '/' . $entry, $classes . '/' . $entry);
                }
                continue;
            }
            $this->planTemplates($path, $this->root . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $entry);
        }
    }

    /**
     * The moves of `Test/`: `Case/` to `TestCase/`, the other files and directories as they are
     *
     * @param string $from The directory `Test/`, relative to the root
     * @param string $to The directory `tests/`, relative to the root
     * @return void
     */
    private function planTests(string $from, string $to): void
    {
        $dir = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $from);
        if (!Paths::hasDirectory(dirname($dir), basename($dir))) {
            return;
        }
        foreach (Paths::entries($dir) as $entry) {
            $this->add($from . '/' . $entry, $to . '/' . ($entry === 'Case' ? 'TestCase' : $entry));
        }
        $this->paths += [$from => $to];
    }

    /**
     * The moves of the files of a directory of templates, to their path in `templates/` with the names of CakePHP 5
     *
     * @param string $from The directory of the templates (or a file)
     * @param string $to Its path in `templates/`
     * @return void
     */
    private function planTemplates(string $from, string $to): void
    {
        $templates = $this->root . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR;
        if (is_file($from)) {
            $files = [$from => $to];
        } elseif (is_dir($from)) {
            $files = [];
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $files[$file->getPathname()] = $to . substr($file->getPathname(), strlen($from));
            }
            ksort($files);
        } else {
            return;
        }
        foreach ($files as $file => $destination) {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($destination, strlen($templates)));
            $renamed = $templates . str_replace('/', DIRECTORY_SEPARATOR, $this->templatePath($relative));
            if ($renamed !== $file) {
                $this->moves[] = [$file, $renamed];
            }
        }
    }

    /**
     * The path of a template in `templates/` with the names of CakePHP 5, e.g. `layout/email/html/default.php` for
     * `Layouts/Emails/html/default.ctp`, also in the directories of themes (`Themed/<Theme>/`) and of plugins
     * (`Plugin/<Plugin>/`)
     *
     * @param string $path The path relative to `templates/`, with slashes
     * @return string
     */
    public function templatePath(string $path): string
    {
        $parts = explode('/', $path);
        $index = 0;
        if ($parts[0] === 'Themed' && count($parts) > 2) {
            $index = 2;
        }
        if (($parts[$index] ?? null) === 'Plugin' && count($parts) > $index + 2) {
            $index += 2;
        }
        $directory = $parts[$index];
        if ($index < count($parts) - 1 && isset(self::TEMPLATE_DIRECTORIES[$directory])) {
            $parts[$index] = self::TEMPLATE_DIRECTORIES[$directory];
            if ($directory === 'Layouts' && $index + 1 < count($parts) - 1 && $parts[$index + 1] === 'Emails') {
                $parts[$index + 1] = 'email';
            }
        }
        $last = count($parts) - 1;
        if (!in_array($directory, ['Scaffolds', 'webroot'], true) && preg_match('/\.(ctp|thtml)$/', $parts[$last], $match)) {
            $parts[$last] = substr($parts[$last], 0, -strlen($match[0])) . '.php';
            $this->thtml = $this->thtml || $match[1] === 'thtml';
        }

        return implode('/', $parts);
    }

    /**
     * Adds a move of a file or directory, when it exists (with the case of its name)
     *
     * @param string $from Path relative to the root, with slashes
     * @param string $to Path relative to the root, with slashes
     * @return void
     */
    private function add(string $from, string $to): void
    {
        $path = $this->absolute($from);
        if (Paths::exists($path)) {
            $this->moves[] = [$path, $this->absolute($to)];
            $this->paths[$from] = $to;
        }
    }

    /**
     * @param string $path Path relative to the root, with slashes
     * @return string The absolute path
     */
    private function absolute(string $path): string
    {
        return $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Adds the notices about the templates
     *
     * @return void
     */
    private function finish(): void
    {
        if ($this->thtml) {
            $this->notices[] = 'The .thtml templates became .php: remove the $ext = \'.thtml\' of your controllers and views.';
        }
    }
}
