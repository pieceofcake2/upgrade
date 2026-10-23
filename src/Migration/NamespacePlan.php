<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Migration;

use FilesystemIterator;
use PhpParser\Error;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\InlineHTML;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The moves and the namespaces of `bin/cake upgrade namespace`, for an application or a plugin in the layout of
 * pieceofcake2/app.
 *
 * - The class folders of a plugin (`Controller/`, `Model/`, `View/`, `Console/`, `Lib/`) move into `src/`.
 * - `src/Lib/<Type>/` moves to `src/<Type>/` for the types of CakePHP (`Error`, `Routing`, `Cache`, ...), as
 *   namespaced code has no `Lib`: `src/Lib/Error/AppExceptionRenderer.php` becomes `App\Error\AppExceptionRenderer`.
 *   `src/Lib/Foo.php` stays, as `App\Lib\Foo`.
 * - A copy of a class of CakePHP in an application (a core class override, e.g. `Dispatcher`) moves to
 *   `src/Cake/<path>`, the path of its namespace (`src/Cake/Routing/Dispatcher.php`).
 * - The namespace of a class comes from the path of its file: `<Namespace>\<directories>` in `src/`,
 *   `<Namespace>\Test\<directories>` in `tests/`, `Cake\<directories>` in `src/Cake/`.
 */
final class NamespacePlan
{
    /**
     * The types of classes of CakePHP: the directories of `Lib/` that move to `src/`
     *
     * @var array<string>
     */
    public const TYPES = [
        'Cache', 'Configure', 'Console', 'Controller', 'Core', 'Error', 'Event', 'I18n', 'Log', 'Model', 'Network',
        'Routing', 'TestSuite', 'Utility', 'View',
    ];

    /**
     * The base classes of an application, which are no core class overrides
     *
     * @var array<string>
     */
    private const APP_CLASSES = ['appcontroller', 'appmodel', 'apphelper', 'appshell'];

    /**
     * The moves, absolute paths
     *
     * @var array<array{string, string}>
     */
    public array $moves = [];

    /**
     * The new paths of the moved directories, by old path, relative to the root, for the paths of composer.json
     *
     * @var array<string, string>
     */
    public array $paths = [];

    /**
     * The namespaces of the files, by absolute path after the moves
     *
     * @var array<string, string>
     */
    public array $files = [];

    /**
     * The files of the core class overrides, after the moves
     *
     * @var array<string>
     */
    public array $aliases = [];

    /**
     * The files of CakePHP that the overrides replace, relative to `src/` of pieceofcake2/cakephp
     *
     * @var array<string>
     */
    public array $overridden = [];

    /**
     * The new names of the classes, by global name
     *
     * @var array<string, string>
     */
    public array $classes = [];

    /**
     * What the user has to check or do by hand
     *
     * @var array<string>
     */
    public array $notices = [];

    /**
     * @param string $root The directory of the application or plugin
     * @param string $namespace The namespace
     * @param bool $plugin Whether it is a plugin
     * @param array<string, string> $core The classes of CakePHP, by global name
     */
    private function __construct(public string $root, public string $namespace, public bool $plugin, private array $core)
    {
    }

    /**
     * Why the directory is in the CakePHP 2 layout, or null when it is in the layout of pieceofcake2/app
     *
     * @param string $root The root of the application, or the directory of a plugin
     * @param bool $plugin Whether it is a plugin
     * @return string|null
     */
    public static function legacyLayout(string $root, bool $plugin): ?string
    {
        if (!$plugin) {
            return match (true) {
                LayoutPlan::detect($root) === LayoutPlan::APP_DIRECTORY => 'app/Config/',
                Paths::hasDirectory($root, 'Config') => 'Config/',
                LayoutPlan::detect($root) === LayoutPlan::ROOT_APP => 'Controller/',
                default => null,
            };
        }
        foreach (['Config', 'Locale', 'Test'] as $dir) {
            if (Paths::hasDirectory($root, $dir)) {
                return $dir . '/';
            }
        }
        foreach (Paths::entries($root . DIRECTORY_SEPARATOR . 'View') as $entry) {
            if ($entry !== 'Helper' && !(str_ends_with($entry, '.php') && is_file($root . DIRECTORY_SEPARATOR . 'View' . DIRECTORY_SEPARATOR . $entry))) {
                return 'View/' . $entry;
            }
        }

        return null;
    }

    /**
     * The plan of an application or a plugin
     *
     * @param string $root The root of the application, or the directory of a plugin
     * @param string $namespace The namespace: App.namespace, or the name of the plugin
     * @param bool $plugin Whether it is a plugin
     * @param array<string, string> $core The classes of CakePHP, by global name
     * @return self
     */
    public static function create(string $root, string $namespace, bool $plugin, array $core): self
    {
        $plan = new self($root, $namespace, $plugin, array_change_key_case($core));
        if ($plugin) {
            foreach (self::TYPES as $type) {
                $plan->add('Lib/' . $type, 'src/' . $type);
            }
            foreach (['Lib', 'Controller', 'Model', 'View', 'Console'] as $dir) {
                $plan->add($dir, 'src/' . $dir);
            }
        }
        foreach (self::TYPES as $type) {
            $plan->add('src/Lib/' . $type, 'src/' . $type);
        }
        $plan->planClasses();

        return $plan;
    }

    /**
     * The directories of the code with classes: `src/` and `tests/`
     *
     * @return array<string>
     */
    public function codePaths(): array
    {
        return array_values(array_filter([$this->path('src'), $this->path('tests')], fn(string $path): bool => $this->willExist($path)));
    }

    /**
     * The directories of the configuration and templates: `config/` and `templates/`
     *
     * @return array<string>
     */
    public function configPaths(): array
    {
        return array_values(array_filter([$this->path('config'), $this->path('templates')], 'is_dir'));
    }

    /**
     * The default configuration files of `config/` (`email.php.default`, `database.php.default`), which the
     * application copies to `email.php` and `database.php`: they get the same changes as the files of `config/`
     *
     * @return array<string>
     */
    public function defaultFiles(): array
    {
        $files = glob($this->path('config') . DIRECTORY_SEPARATOR . '*.php.default') ?: [];
        sort($files);

        return array_values(array_filter($files, 'is_file'));
    }

    /**
     * The entry files of an application, which `upgrade layout` creates: `bin/cake` and the PHP files of `webroot/`
     *
     * @return array<string>
     */
    public function entryFiles(): array
    {
        if ($this->plugin) {
            return [];
        }
        $files = glob($this->path('webroot') . DIRECTORY_SEPARATOR . '*.php') ?: [];
        if (is_file($this->path('bin/cake'))) {
            array_unshift($files, $this->path('bin/cake'));
        }

        return array_values(array_filter($files, 'is_file'));
    }

    /**
     * The files whose classes the shell imports, not Rector, which does not import names in files with HTML: the
     * templates (the `.php` and `.ctp` files of `templates/`) and `bin/cake`, whose `#!` line is HTML for PHP
     *
     * @return array<string>
     */
    public function htmlFiles(): array
    {
        $files = [];
        $templates = $this->path('templates');
        if (is_dir($templates)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($templates, FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'ctp'], true)) {
                    $files[] = $file->getPathname();
                }
            }
            sort($files);
        }
        if (!$this->plugin && is_file($this->path('bin/cake'))) {
            $files[] = $this->path('bin/cake');
        }

        return $files;
    }

    /**
     * Finds the classes of the files, after the moves, and their namespaces
     *
     * @return void
     */
    private function planClasses(): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $declared = [];
        foreach ($this->sourceFiles() as $source => $file) {
            try {
                $stmts = $parser->parse((string)file_get_contents($source)) ?? [];
            } catch (Error $e) {
                $this->notices[] = sprintf('%s was not namespaced: %s', $this->relative($source), $e->getMessage());
                continue;
            }
            [$classes, $skip] = $this->classesOf($stmts);
            if ($classes === [] || $skip === '') {
                continue;
            }
            if ($skip !== null) {
                $this->notices[] = sprintf('%s was not namespaced: %s. Namespace it by hand.', $this->relative($file), $skip);
                continue;
            }
            $file = $this->overridePath($source, $file, $classes);
            $namespace = $this->namespaceOf($file);
            if ($namespace === null) {
                $this->notices[] = sprintf('%s was not namespaced: its directories are no valid namespace.', $this->relative($file));
                continue;
            }
            $this->files[$file] = $namespace;
            foreach ($classes as $class) {
                $declared[strtolower($class)][] = [$class, $namespace . '\\' . $class, $file];
            }
        }
        $this->mapClasses($declared);
    }

    /**
     * The global classes that a file declares, and why it cannot be namespaced
     *
     * @param array<\PhpParser\Node\Stmt> $stmts The statements of the file
     * @return array{array<string>, string|null} The classes, and the reason, or '' for a file with a namespace
     */
    private function classesOf(array $stmts): array
    {
        $classes = [];
        $skip = null;
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Namespace_) {
                return [[], ''];
            }
            if ($stmt instanceof Function_) {
                $skip = 'it also declares functions, which a namespace would rename';
            } elseif ($stmt instanceof InlineHTML) {
                $skip = 'it has HTML outside the PHP tags';
            } elseif ($stmt instanceof Declare_ && $stmt->stmts !== null) {
                $skip = 'it has a declare block';
            } elseif ($stmt instanceof ClassLike && $stmt->name !== null) {
                $classes[] = $stmt->name->toString();
            }
        }

        return [$classes, $skip];
    }

    /**
     * The new names of the classes, but those declared in more than one file, which are listed
     *
     * @param array<string, array<array{string, string, string}>> $declared The global name, the new name and the file
     *   of each declaration, by lowercase name
     * @return void
     */
    private function mapClasses(array $declared): void
    {
        foreach ($declared as $classes) {
            if (count($classes) > 1) {
                $this->notices[] = sprintf(
                    'The class %s is declared in %s: its uses in other files were not renamed.',
                    $classes[0][0],
                    implode(', ', array_map(fn(array $class): string => $this->relative($class[2]), $classes)),
                );
                continue;
            }
            $this->classes[$classes[0][0]] = $classes[0][1];
        }
    }

    /**
     * The new path of a file with a copy of a class of CakePHP: `src/Cake/<path>`, which is also moved
     *
     * @param string $source The path now
     * @param string $file The path after the moves
     * @param array<string> $classes The classes of the file
     * @return string The path
     */
    private function overridePath(string $source, string $file, array $classes): string
    {
        $src = $this->path('src') . DIRECTORY_SEPARATOR;
        if ($this->plugin || !str_starts_with($file, $src) || str_starts_with($file, $src . 'Cake' . DIRECTORY_SEPARATOR)) {
            return $file;
        }
        foreach ($classes as $class) {
            $namespaced = $this->core[strtolower($class)] ?? null;
            if ($namespaced === null || in_array(strtolower($class), self::APP_CLASSES, true)) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($namespaced, strlen('Cake\\'))) . '.php';
            $override = $src . 'Cake' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if ($override !== $file) {
                $this->moves[] = [$file, $override];
            }
            $this->aliases[] = $override;
            $this->overridden[] = $relative;
            $this->notices[] = sprintf(
                '%s is a copy of the class %s of CakePHP: it moved to %s. Compare it with src/%s of pieceofcake2/cakephp, ' .
                'which it replaces, as it may be a copy of an older version.',
                $this->relative($source),
                $namespaced,
                $this->relative($override),
                $relative,
            );

            return $override;
        }

        return $file;
    }

    /**
     * The namespace of a file from its path
     *
     * @param string $file The path after the moves
     * @return string|null The namespace, or null if a directory is no valid name
     */
    private function namespaceOf(string $file): ?string
    {
        $src = $this->path('src') . DIRECTORY_SEPARATOR;
        $tests = $this->path('tests') . DIRECTORY_SEPARATOR;
        if (str_starts_with($file, $src . 'Cake' . DIRECTORY_SEPARATOR) && !$this->plugin) {
            $namespace = 'Cake';
            $dir = dirname(substr($file, strlen($src . 'Cake') + 1));
        } elseif (str_starts_with($file, $src)) {
            $namespace = $this->namespace;
            $dir = dirname(substr($file, strlen($src)));
        } else {
            $namespace = $this->namespace . '\\Test';
            $dir = dirname(substr($file, strlen($tests)));
        }
        foreach ($dir === '.' ? [] : explode(DIRECTORY_SEPARATOR, $dir) as $part) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
                return null;
            }
            $namespace .= '\\' . $part;
        }

        return $namespace;
    }

    /**
     * The PHP files of the code, with their paths after the moves, except the templates of the console
     * (`src/Console/Templates/`), which bake copies
     *
     * @return array<string, string> The paths after the moves, by path now
     */
    private function sourceFiles(): array
    {
        $dirs = [$this->path('src'), $this->path('tests')];
        foreach ($this->moves as [$from]) {
            $dirs[] = $from;
        }
        $templates = $this->path('src') . DIRECTORY_SEPARATOR . 'Console' . DIRECTORY_SEPARATOR . 'Templates' . DIRECTORY_SEPARATOR;
        $files = [];
        foreach (array_unique($dirs) as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $moved = $this->moved($file->getPathname());
                if (!str_starts_with($moved, $templates) && $this->inCode($moved)) {
                    $files[$file->getPathname()] = $moved;
                }
            }
        }
        ksort($files);

        return $files;
    }

    /**
     * Whether a path is in `src/` or `tests/`
     *
     * @param string $path The path
     * @return bool
     */
    private function inCode(string $path): bool
    {
        return str_starts_with($path, $this->path('src') . DIRECTORY_SEPARATOR) || str_starts_with($path, $this->path('tests') . DIRECTORY_SEPARATOR);
    }

    /**
     * The path of a file after the moves
     *
     * @param string $path The path now
     * @return string
     */
    private function moved(string $path): string
    {
        foreach ($this->moves as [$from, $to]) {
            if ($path === $from || str_starts_with($path, $from . DIRECTORY_SEPARATOR)) {
                $path = $to . substr($path, strlen($from));
            }
        }

        return $path;
    }

    /**
     * Whether a directory exists, or will after the moves
     *
     * @param string $path The directory
     * @return bool
     */
    private function willExist(string $path): bool
    {
        if (is_dir($path)) {
            return true;
        }
        foreach ($this->moves as [, $to]) {
            if ($to === $path || str_starts_with($to, $path . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds a move of a directory, when it exists (with the case of its name)
     *
     * @param string $from Path relative to the root, with slashes
     * @param string $to Path relative to the root, with slashes
     * @return void
     */
    private function add(string $from, string $to): void
    {
        $path = $this->path($from);
        if (Paths::exists($path) && is_dir($path)) {
            $this->moves[] = [$path, $this->path($to)];
            $this->paths[$from] = $to;
        }
    }

    /**
     * @param string $path Path relative to the root, with slashes
     * @return string The absolute path
     */
    private function path(string $path): string
    {
        return $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * @param string $path An absolute path
     * @return string The path relative to the root
     */
    private function relative(string $path): string
    {
        return str_starts_with($path, $this->root . DIRECTORY_SEPARATOR) ? substr($path, strlen($this->root) + 1) : $path;
    }
}
