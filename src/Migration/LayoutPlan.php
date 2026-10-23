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
 *   `resources/locales/`, `app/Test/` to `tests/`, `app/Plugin/` to `plugins/`, `app/Vendor/` to `vendors/`,
 *   `app/webroot/` to `webroot/`, `app/tmp/` to `tmp/` and `app/tmp/logs/` to `logs/`. The entry files of
 *   pieceofcake2/app replace the ones of `app/` (see EntryFiles).
 * - An application whose root is the directory `app/` of CakePHP 2 (`Config/`, `Controller/`, `webroot/`, ... in the
 *   root): the same moves from the root, but `webroot/` and `tmp/` stay. Some directories may be lowercase already
 *   (`config/`), and the lowercase `vendor/` is the one of Composer, which stays.
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
     * The CakePHP 2 layout: `app/Config/`, `app/Controller/`, ...
     *
     * @var string
     */
    public const APP_DIRECTORY = 'app';

    /**
     * The CakePHP 2 layout without `app/`: `Config/`, `Controller/`, `webroot/`, ... in the root
     *
     * @var string
     */
    public const ROOT_APP = 'root';

    /**
     * The layout of pieceofcake2/app: `config/`, `src/`, `templates/`, ...
     *
     * @var string
     */
    public const NEW_LAYOUT = 'new';

    /**
     * A plugin
     *
     * @var string
     */
    public const PLUGIN = 'plugin';

    /**
     * Neither an application nor a plugin
     *
     * @var string
     */
    public const NONE = 'none';

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
     * The entry files of the layout of pieceofcake2/app to create (applications only)
     *
     * @var \PieceOfCake2\Upgrade\Migration\EntryFiles|null
     */
    public ?EntryFiles $entryFiles = null;

    /**
     * The layout before the moves, one of the constants of the layouts
     *
     * @var string
     */
    public string $layout = self::PLUGIN;

    /**
     * The directories that the moves may leave empty, relative to the root
     *
     * @var array<string>
     */
    public array $sources = [];

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
     * The layout of a directory, from the names of its files with their case:
     *
     * - `app/Config/`: the CakePHP 2 layout (APP_DIRECTORY).
     * - A plugin (PLUGIN): `"type": "cakephp-plugin"` in its composer.json, or the directories of CakePHP 2
     *   (`Config/`, `Controller/`, `View/`, ...) without a `core.php` in `Config/` or `config/`.
     * - `Config/core.php` or `config/core.php` with `Controller/` and `webroot/index.php` in the root: the CakePHP 2
     *   layout without `app/` (ROOT_APP).
     * - `config/core.php` or `config/` otherwise: the layout of pieceofcake2/app (NEW_LAYOUT).
     *
     * @param string $root The directory
     * @return string One of the constants of the layouts
     */
    public static function detect(string $root): string
    {
        if (Paths::hasDirectory($root, 'app') && Paths::hasDirectory($root . DIRECTORY_SEPARATOR . 'app', 'Config')) {
            return self::APP_DIRECTORY;
        }
        if ((self::composer($root)['type'] ?? null) === 'cakephp-plugin') {
            return self::PLUGIN;
        }
        $config = Paths::hasDirectory($root, 'Config') ? 'Config' : (Paths::hasDirectory($root, 'config') ? 'config' : null);
        if ($config !== null && Paths::exists($root . DIRECTORY_SEPARATOR . $config . DIRECTORY_SEPARATOR . 'core.php')) {
            $webroot = $root . DIRECTORY_SEPARATOR . 'webroot';
            if (Paths::hasDirectory($root, 'Controller') && Paths::hasDirectory($root, 'webroot') && Paths::exists($webroot . DIRECTORY_SEPARATOR . 'index.php')) {
                return self::ROOT_APP;
            }

            return self::NEW_LAYOUT;
        }
        foreach (['Config', 'Controller', 'Model', 'View', 'Lib', 'Locale', 'Test'] as $dir) {
            if (Paths::hasDirectory($root, $dir)) {
                return self::PLUGIN;
            }
        }

        return $config !== null ? self::NEW_LAYOUT : self::NONE;
    }

    /**
     * The moves of an application, run from the root of the project, which holds `app/` in the CakePHP 2 layout, or
     * which is the directory `app/` of CakePHP 2
     *
     * @param string $root The root of the project
     * @return self
     */
    public static function forApplication(string $root): self
    {
        $plan = new self($root);
        $plan->layout = self::detect($root);
        $base = match ($plan->layout) {
            self::APP_DIRECTORY => 'app/',
            self::ROOT_APP => '',
            default => null,
        };
        if ($base !== null) {
            $plan->planApplication($base);
        }
        $plan->planTemplates($root . DIRECTORY_SEPARATOR . 'templates', $root . DIRECTORY_SEPARATOR . 'templates');
        $plan->sources[] = 'templates';
        $moves = [];
        foreach ($plan->moves as [$from, $to]) {
            $moves[] = [
                str_replace(DIRECTORY_SEPARATOR, '/', substr($from, strlen($root) + 1)),
                str_replace(DIRECTORY_SEPARATOR, '/', substr($to, strlen($root) + 1)),
            ];
        }
        $plan->entryFiles = EntryFiles::plan($root, $moves, $base);
        $plan->notices = array_merge($plan->notices, $plan->entryFiles->notices);
        if ($base !== null) {
            $plan->gitignoreNotice();
        }
        $plan->finish();

        return $plan;
    }

    /**
     * The settings of the composer.json of a directory
     *
     * @param string $root The directory
     * @return array<string, mixed>
     */
    private static function composer(string $root): array
    {
        $file = $root . DIRECTORY_SEPARATOR . 'composer.json';
        $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;

        return is_array($data) ? $data : [];
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
        $plan->sources = ['View', 'Locale', 'Test', 'templates'];
        $plan->finish();

        return $plan;
    }

    /**
     * The moves of the files of `app/`, or of the root when it is the directory `app/` of CakePHP 2
     *
     * @param string $base `app/`, or '' for the root
     * @return void
     */
    private function planApplication(string $base): void
    {
        $app = $base === '' ? $this->root : $this->root . DIRECTORY_SEPARATOR . 'app';
        $this->addFirst([$base . 'Config', $base . 'config'], 'config');
        $this->add($base . 'Controller', 'src/Controller');
        $this->add($base . 'Model', 'src/Model');
        $this->add($base . 'Lib', 'src/Lib');
        $this->add($base . 'Console/Command', 'src/Console/Command');
        $this->add($base . 'Console/Templates', 'src/Console/Templates');
        $this->planView($app . DIRECTORY_SEPARATOR . 'View', 'src/View');
        $this->addFirst([$base . 'Locale', $base . 'locale'], 'resources/locales');
        $this->planTests($base . 'Test', 'tests');
        // The hand-copied libraries go to vendors/, which App::import('Vendor') reads and Composer does not manage.
        // The lowercase vendor/ of Composer stays (Paths::exists() checks the case).
        $vendorDir = rtrim((string)(self::composer($this->root)['config']['vendor-dir'] ?? 'vendor'), '/');
        $vendor = $base === '' && $vendorDir === 'Vendor' ? false : Paths::hasDirectory($app, 'Vendor');
        if ($vendor) {
            $this->add($base . 'Vendor', 'vendors');
            $this->notices[] = $base . 'Vendor/ moved to vendors/, where App::import(\'Vendor\', ...) finds the files. ' .
                'Install the libraries with Composer instead when they have a package.';
        }
        if ($base === '') {
            $this->planRoot();

            return;
        }
        foreach (Paths::entries($app . DIRECTORY_SEPARATOR . 'Plugin') as $plugin) {
            if (is_dir($app . DIRECTORY_SEPARATOR . 'Plugin' . DIRECTORY_SEPARATOR . $plugin)) {
                $this->add('app/Plugin/' . $plugin, 'plugins/' . $plugin);
            }
        }
        $this->paths += ['app/Plugin' => 'plugins'];
        // The front controllers of app/webroot/ are replaced by the ones of pieceofcake2/app (see EntryFiles)
        foreach (Paths::entries($app . DIRECTORY_SEPARATOR . 'webroot') as $entry) {
            if (!in_array('app/webroot/' . $entry, EntryFiles::oldFiles('app/'), true)) {
                $this->add('app/webroot/' . $entry, 'webroot/' . $entry);
            }
        }
        $this->paths += ['app/webroot' => 'webroot'];
        $this->add('app/tmp/logs', 'logs');
        $this->add('app/tmp', 'tmp');
        $this->sources[] = 'app';

        $left = $this->leftIn($app);
        if ($left !== []) {
            $this->notices[] = sprintf('Not moved, move or replace them by hand: %s.', implode(', ', $left));
        }
        $this->rootNotices('app/webroot');
    }

    /**
     * The moves of the root when it is the directory `app/` of CakePHP 2: `webroot/` and `tmp/` stay, `Plugin/`
     * moves as a whole to `plugins/`, where composer.json then installs the plugins (see UpgradeShell::layout())
     *
     * @return void
     */
    private function planRoot(): void
    {
        $this->add('Plugin', 'plugins');
        $this->paths += ['Plugin' => 'plugins'];
        $this->add('tmp/logs', 'logs');
        foreach (['Controller', 'Model', 'Lib', 'Console', 'View', 'Locale', 'locale', 'Test', 'Plugin', 'Vendor'] as $dir) {
            if (Paths::hasDirectory($this->root, $dir)) {
                $this->sources[] = $dir;
            }
        }
        $left = [];
        foreach (Paths::entries($this->root . DIRECTORY_SEPARATOR . 'Console') as $file) {
            if (!in_array($file, ['Command', 'Templates'], true) && !in_array('Console/' . $file, EntryFiles::oldFiles(''), true)) {
                $left[] = 'Console/' . $file;
            }
        }
        if ($left !== []) {
            $this->notices[] = sprintf('Not moved, move or replace them by hand: %s.', implode(', ', $left));
        }
        $this->rootNotices('webroot');
    }

    /**
     * The files of `app/` that do not move, but the entry files that EntryFiles replaces
     *
     * @param string $app The directory `app/`
     * @return array<string> Paths relative to the root
     */
    private function leftIn(string $app): array
    {
        $moved = ['Config', 'Controller', 'Model', 'Lib', 'View', 'Locale', 'Test', 'Vendor', 'webroot', 'tmp'];
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
                    if (!in_array($file, ['Command', 'Templates'], true) && !in_array('app/Console/' . $file, EntryFiles::oldFiles('app/'), true)) {
                        $left[] = 'app/Console/' . $file;
                    }
                }
                continue;
            }
            $left[] = 'app/' . $entry;
        }

        return $left;
    }

    /**
     * Lists the files in the root of the project that lead to `app/webroot/`, or to `webroot/` in an application
     * whose root is the directory `app/` of CakePHP 2
     *
     * @param string $webroot `app/webroot` or `webroot`
     * @return void
     */
    private function rootNotices(string $webroot): void
    {
        foreach (['index.php', '.htaccess'] as $file) {
            $path = $this->root . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path) || !str_contains((string)file_get_contents($path), $webroot)) {
                continue;
            }
            $this->notices[] = $webroot === 'webroot'
                ? sprintf('%s leads to webroot/: set the document root of the web server to webroot/, then delete it.', $file)
                : sprintf(
                    '%s leads to app/webroot/, which moved to webroot/: set the document root of the web server to ' .
                    'webroot/, then delete it.',
                    $file,
                );
        }
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
     * Adds the move of the first of the files or directories that exists (with the case of its name), unless it is
     * the destination
     *
     * @param array<string> $from Paths relative to the root, with slashes
     * @param string $to Path relative to the root, with slashes
     * @return void
     */
    private function addFirst(array $from, string $to): void
    {
        foreach ($from as $path) {
            if (Paths::exists($this->absolute($path))) {
                if ($path !== $to) {
                    $this->add($path, $to);
                }

                return;
            }
        }
    }

    /**
     * Lists the lines of `.gitignore` that name moved paths
     *
     * @return void
     */
    private function gitignoreNotice(): void
    {
        $file = $this->root . DIRECTORY_SEPARATOR . '.gitignore';
        if (!is_file($file)) {
            return;
        }
        $lines = [];
        foreach (preg_split('/\r?\n/', (string)file_get_contents($file)) ?: [] as $line) {
            $pattern = ltrim(ltrim(trim($line), '!'), '/');
            if ($pattern === '' || str_starts_with($pattern, '#')) {
                continue;
            }
            foreach (array_keys($this->paths) as $from) {
                if ($pattern === $from || str_starts_with($pattern, $from . '/')) {
                    $lines[] = trim($line);
                    break;
                }
            }
        }
        if ($lines !== []) {
            $this->notices[] = sprintf('.gitignore names moved paths, update them: %s', implode(', ', $lines));
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
