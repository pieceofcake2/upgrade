<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Migration;

use PhpParser\Error;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpToken;

/**
 * The files that run an application in the layout of pieceofcake2/app: `config/define.php`, `bin/cake`,
 * `webroot/index.php`, `webroot/test.php` and `webroot/.htaccess`, and the directories of `tmp/` and `logs/`.
 *
 * `layout` creates the missing ones from the copies in `resources/skeleton/` of this plugin, which are the files of
 * pieceofcake2/app. They replace the files of the CakePHP 2 layout (`app/webroot/index.php`, `app/webroot/test.php`
 * and `app/Console/cake`, `cake.php` and `cake.bat`), which define the paths of that layout. The constants of their
 * own that these files define, e.g. `define('SITE_NAME', 'Blog')`, are added to the new `config/define.php`, which all
 * the entry files load; those built on the paths of the old layout (`__FILE__`, `__DIR__`, `APP`, `APP_DIR`, ...) or
 * defined under a condition are listed instead.
 *
 * In an application whose root is the directory `app/` of CakePHP 2, the old `webroot/index.php` and
 * `webroot/test.php` are at the place of the new ones: an old one (it defines `APP_DIR` or `CAKE_CORE_INCLUDE_PATH`
 * and loads no `define.php`) is replaced, another one is kept. So is a `define.php` of the application in the old
 * form, which defines `CAKE_CORE_INCLUDE_PATH`, or `ROOT` and `APP_DIR` for the old layout: its own constants are
 * kept as those of the other old entry files. The shell replaces a file only when Git has its contents. An existing
 * file is never overwritten otherwise: the files that an application already has (`bin/cake`, `config/define.php` in
 * the form of pieceofcake2/app, ...) are kept, with the paths of the moved directories updated (`'Config'` becomes
 * `'config'`), and listed for review.
 */
final class EntryFiles
{
    /**
     * The file of the path constants, which the other entry files load
     *
     * @var string
     */
    public const DEFINE = 'config/define.php';

    /**
     * The entry files of pieceofcake2/app, relative to the root
     *
     * @var array<string>
     */
    public const FILES = [self::DEFINE, 'bin/cake', 'webroot/index.php', 'webroot/test.php', 'webroot/.htaccess'];

    /**
     * The entry files of the CakePHP 2 layout that the new files replace, relative to `app/`
     *
     * @var array<string>
     */
    public const OLD_FILES = ['webroot/index.php', 'webroot/test.php', 'Console/cake', 'Console/cake.php', 'Console/cake.bat'];

    /**
     * The constants of the paths of the CakePHP 2 layout, whose values change with the new layout
     *
     * @var array<string>
     */
    private const LAYOUT_CONSTANTS = ['APP', 'APP_DIR', 'CAKE_CORE_INCLUDE_PATH', 'CORE_PATH', 'CAKE'];

    /**
     * The constants of the paths that config/define.php of pieceofcake2/app defines for the other entry files
     *
     * @var array<string>
     */
    private const DEFINE_CONSTANTS = ['ROOT', 'APP_DIR', 'WEBROOT_DIR', 'WWW_ROOT', 'VENDORS'];

    /**
     * The directories of pieceofcake2/app for the temporary files and the logs
     *
     * @var array<string>
     */
    public const DIRECTORIES = ['tmp/cache/models', 'tmp/cache/persistent', 'tmp/cache/views', 'tmp/sessions', 'tmp/tests', 'logs'];

    /**
     * The constants of the entry files of CakePHP 2, which the new files define themselves or no longer need
     *
     * @var array<string>
     */
    private const STANDARD_CONSTANTS = [
        'DS', 'ROOT', 'APP_DIR', 'APP', 'WEBROOT_DIR', 'WWW_ROOT', 'CAKE_CORE_INCLUDE_PATH', 'CORE_PATH', 'CAKE',
        'CAKEPHP_SHELL', 'VENDORS',
    ];

    /**
     * The files to create, relative to the root: their contents
     *
     * @var array<string, string>
     */
    public array $files = [];

    /**
     * The directories to create, relative to the root
     *
     * @var array<string>
     */
    public array $directories = [];

    /**
     * The old entry files, relative to the root
     *
     * @var array<string>
     */
    public array $oldFiles = [];

    /**
     * The old entry files at the place of a new one, which replaces them: the new contents, by file (in an
     * application whose root is the directory `app/` of CakePHP 2)
     *
     * @var array<string, string>
     */
    public array $replaced = [];

    /**
     * The paths before the moves of the old entry files that are replaced, by file
     *
     * @var array<string, string>
     */
    public array $sources = [];

    /**
     * The entry files that the application has, which are kept and listed for review, relative to the root after the
     * moves
     *
     * @var array<string>
     */
    public array $review = [];

    /**
     * The constants added to config/define.php: the file that defined them, by constant
     *
     * @var array<string, string>
     */
    public array $kept = [];

    /**
     * What the user has to check or do by hand
     *
     * @var array<string>
     */
    public array $notices = [];

    /**
     * The old entry files of a layout
     *
     * @param string $base `app/`, or '' for an application whose root is the directory `app/` of CakePHP 2
     * @return array<string> Relative to the root
     */
    public static function oldFiles(string $base): array
    {
        return array_map(static fn(string $file): string => $base . $file, self::OLD_FILES);
    }

    /**
     * The entry files to create
     *
     * @param string $root The root of the project
     * @param array<array{string, string}> $moves The moves before, relative to the root, with slashes
     * @param string|null $base `app/` or '' (see oldFiles()) for an application in the CakePHP 2 layout, null for one
     *   in the new layout
     * @return self
     */
    public static function plan(string $root, array $moves, ?string $base = 'app/'): self
    {
        $plan = new self();
        $skeleton = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'skeleton' . DIRECTORY_SEPARATOR;
        $exists = static fn(string $path): bool => self::existsAfter($root, $moves, $path);
        foreach (self::oldFiles($base ?? 'app/') as $file) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
            if (!Paths::exists($path) || !is_file($path)) {
                continue;
            }
            // In the root, the old front controllers are at the place of the new ones
            if ($base === '' && str_starts_with($file, 'webroot/') && !self::isOld((string)file_get_contents($path))) {
                continue;
            }
            $plan->oldFiles[] = $file;
            if (in_array($file, self::FILES, true)) {
                $plan->sources[$file] = $file;
            }
        }
        // A define.php of the application in the old form, e.g. Config/define.php that bin/cake loads
        $define = self::pathBefore($moves, self::DEFINE);
        $definePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $define);
        if ($base !== null && Paths::exists($definePath) && is_file($definePath) && self::isOldDefine((string)file_get_contents($definePath))) {
            array_unshift($plan->oldFiles, $define);
            $plan->sources[self::DEFINE] = $define;
        }
        $defines = $plan->customDefines($root, $base === '');
        foreach (self::FILES as $file) {
            $contents = (string)file_get_contents($skeleton . $file);
            if ($file === self::DEFINE && $defines !== []) {
                $contents = rtrim($contents) . "\n" . implode('', $defines);
            }
            if (isset($plan->sources[$file])) {
                $plan->replaced[$file] = $contents;
                continue;
            }
            if ($exists($file)) {
                if ($base !== null && $file !== 'webroot/.htaccess') {
                    $plan->review[] = $file;
                }
                continue;
            }
            $plan->files[$file] = $contents;
        }
        if ($plan->kept !== [] && !isset($plan->files[self::DEFINE]) && !isset($plan->replaced[self::DEFINE])) {
            $plan->notices[] = sprintf(
                'config/define.php exists, so these constants of the old entry files were not added to it: %s.',
                implode(', ', array_keys($plan->kept)),
            );
            $plan->kept = [];
        }
        foreach (self::DIRECTORIES as $dir) {
            if (!$exists($dir)) {
                $plan->directories[] = $dir;
            }
        }

        return $plan;
    }

    /**
     * Whether an entry file is one of the CakePHP 2 layout: it defines the paths of that layout (`APP_DIR`,
     * `CAKE_CORE_INCLUDE_PATH`) and loads no `define.php`
     *
     * @param string $code The code of the file
     * @return bool
     */
    public static function isOld(string $code): bool
    {
        return preg_match('/\bdefine\(\s*[\'"](APP_DIR|CAKE_CORE_INCLUDE_PATH)[\'"]/', $code) === 1 && !str_contains($code, 'define.php');
    }

    /**
     * Whether a `define.php` of the application is in the old form: it defines `CAKE_CORE_INCLUDE_PATH`, or `ROOT` or
     * `APP_DIR` with other values than the one of pieceofcake2/app (the root of the project and `'src'`)
     *
     * @param string $code The code of the file
     * @return bool
     */
    public static function isOldDefine(string $code): bool
    {
        return preg_match('/\bdefine\(\s*[\'"]CAKE_CORE_INCLUDE_PATH[\'"]/', $code) === 1 || self::otherPaths($code) !== [];
    }

    /**
     * The constants `ROOT` and `APP_DIR` that a `define.php` defines with other values than the one of
     * pieceofcake2/app
     *
     * @param string $code The code of the file
     * @return array<string>
     */
    private static function otherPaths(string $code): array
    {
        // The values of pieceofcake2/app
        $values = ['ROOT' => 'dirname\(__DIR__\)', 'APP_DIR' => '[\'"]src[\'"]'];
        $other = [];
        foreach ($values as $constant => $value) {
            if (
                preg_match('/\bdefine\(\s*[\'"]' . $constant . '[\'"]/', $code)
                && !preg_match('/\bdefine\(\s*[\'"]' . $constant . '[\'"]\s*,\s*' . $value . '\s*\)/', $code)
            ) {
                $other[] = $constant;
            }
        }

        return $other;
    }

    /**
     * The path of a file before the moves
     *
     * @param array<array{string, string}> $moves The moves, relative to the root, with slashes
     * @param string $path The path after the moves, relative to the root
     * @return string
     */
    private static function pathBefore(array $moves, string $path): string
    {
        foreach ($moves as [$from, $to]) {
            if ($path === $to) {
                return $from;
            }
            if (str_starts_with($path, $to . '/')) {
                return $from . substr($path, strlen($to));
            }
        }

        return $path;
    }

    /**
     * Updates the paths of the moved directories in the strings of the code of an entry file that is kept:
     * `dirname(__DIR__) . DS . 'Config' . DS . 'define.php'` becomes `dirname(__DIR__) . DS . 'config' . DS . 'define.php'`
     *
     * @param string $code The code
     * @param array<string, string> $paths The new paths of the moved directories, by old path, relative to the root
     * @return array{string, array<string>} The code, and the changes
     */
    public static function movePaths(string $code, array $paths): array
    {
        $changes = [];
        $result = '';
        foreach (PhpToken::tokenize($code) as $token) {
            $text = $token->text;
            if ($token->id === T_CONSTANT_ENCAPSED_STRING && strlen($text) > 2 && strpbrk($text, '\\$') === false) {
                $quote = $text[0];
                $value = substr($text, 1, -1);
                foreach ($paths as $from => $to) {
                    if ($from !== $to && ($value === $from || str_starts_with($value, $from . '/'))) {
                        $new = $quote . $to . substr($value, strlen($from)) . $quote;
                        $changes[] = $text . ' => ' . $new;
                        $text = $new;
                        break;
                    }
                }
            }
            $result .= $text;
        }

        return [$result, array_values(array_unique($changes))];
    }

    /**
     * What to check in a `config/define.php` that the application has: the constants of the paths that it defines,
     * whose values were for the CakePHP 2 layout, and those that the entry files of pieceofcake2/app need but it does
     * not define
     *
     * @param string $code The code of the file
     * @return string|null The notice, or null
     */
    public static function defineNotice(string $code): ?string
    {
        $missing = array_values(array_filter(
            self::DEFINE_CONSTANTS,
            static fn(string $constant): bool => !preg_match('/\bdefine\(\s*[\'"]' . $constant . '[\'"]/', $code),
        ));
        $other = self::otherPaths($code);
        $checks = [];
        if ($other !== []) {
            $checks[] = sprintf('it defines %s with other values than the new layout (ROOT is the root of the project, APP_DIR is \'src\')', implode(' and ', $other));
        }
        if ($missing !== []) {
            $checks[] = sprintf('the entry files of pieceofcake2/app need %s', implode(', ', $missing));
        }
        if ($checks === []) {
            return null;
        }

        return sprintf(
            'config/define.php was kept: compare it with resources/skeleton/config/define.php of pieceofcake2/upgrade, as %s.',
            implode(', and ', $checks),
        );
    }

    /**
     * Whether a path exists after the moves
     *
     * @param string $root The root of the project
     * @param array<array{string, string}> $moves The moves, relative to the root, with slashes
     * @param string $path The path, relative to the root
     * @return bool
     */
    private static function existsAfter(string $root, array $moves, string $path): bool
    {
        if (file_exists($root . DIRECTORY_SEPARATOR . $path)) {
            return true;
        }
        foreach ($moves as [$from, $to]) {
            if ($to === $path || str_starts_with($to, $path . '/')) {
                return true;
            }
            if (str_starts_with($path, $to . '/') && file_exists($root . DIRECTORY_SEPARATOR . $from . substr($path, strlen($to)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The definitions of the constants of the application in the old entry files, for config/define.php
     *
     * @param string $root The root of the project
     * @param bool $rootApplication Whether the root is the directory `app/` of CakePHP 2, where ROOT was its parent
     * @return array<string> The code of the definitions
     */
    private function customDefines(string $root, bool $rootApplication): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $defines = [];
        foreach ($this->oldFiles as $file) {
            $code = (string)file_get_contents($root . DIRECTORY_SEPARATOR . $file);
            if (!str_contains($code, '<?php')) {
                continue;
            }
            try {
                $stmts = $parser->parse($code) ?? [];
            } catch (Error) {
                $this->notices[] = sprintf('%s is no valid PHP: check it for settings to keep.', $file);
                continue;
            }
            $unconditional = $this->unconditionalCalls($stmts);
            foreach ((new NodeFinder())->findInstanceOf($stmts, FuncCall::class) as $call) {
                $define = $this->defineOf($code, $file, $call, isset($unconditional[spl_object_id($call)]), $rootApplication);
                if ($define !== null) {
                    $defines[] = $define;
                }
            }
        }

        return $defines;
    }

    /**
     * The calls of the statements of a file that run unconditionally: at the top level, or in an
     * `if (!defined('NAME'))` that guards the definition of a constant
     *
     * @param array<\PhpParser\Node\Stmt> $stmts The statements
     * @return array<int, true> By id of the call
     */
    private function unconditionalCalls(array $stmts): array
    {
        $calls = [];
        foreach ($stmts as $stmt) {
            $body = [$stmt];
            if ($stmt instanceof If_ && $stmt->elseifs === [] && $stmt->else === null && $stmt->cond instanceof BooleanNot) {
                $guard = $stmt->cond->expr;
                if ($guard instanceof FuncCall && $guard->name instanceof Name && $guard->name->toLowerString() === 'defined') {
                    $body = $stmt->stmts;
                }
            }
            foreach ($body as $child) {
                if ($child instanceof Expression && $child->expr instanceof FuncCall) {
                    $calls[spl_object_id($child->expr)] = true;
                }
            }
        }

        return $calls;
    }

    /**
     * The definition for config/define.php of a constant of the application that an old entry file defines
     *
     * @param string $code The code of the file
     * @param string $file The file, relative to the root
     * @param \PhpParser\Node\Expr\FuncCall $call A call
     * @param bool $unconditional Whether the call runs unconditionally
     * @param bool $rootApplication Whether the root is the directory `app/` of CakePHP 2, where ROOT was its parent
     * @return string|null The code, or null for another call, a constant of CakePHP, or one that is listed
     */
    private function defineOf(string $code, string $file, FuncCall $call, bool $unconditional, bool $rootApplication): ?string
    {
        $args = $call->getArgs();
        if (!$call->name instanceof Name || $call->name->toLowerString() !== 'define' || count($args) < 2 || !$args[0]->value instanceof String_) {
            return null;
        }
        $name = $args[0]->value->value;
        if (in_array($name, self::STANDARD_CONSTANTS, true) || isset($this->kept[$name])) {
            return null;
        }
        $value = $this->source($code, $args[1]);
        if (preg_match('/__FILE__|__DIR__/', $value)) {
            $this->notices[] = sprintf('%s defines %s from its own path (%s): define it in config/define.php by hand, from ROOT.', $file, $name, $value);

            return null;
        }
        $layoutConstants = $rootApplication ? array_merge(self::LAYOUT_CONSTANTS, ['ROOT']) : self::LAYOUT_CONSTANTS;
        $constants = array_map(static fn(ConstFetch $fetch): string => $fetch->name->toString(), (new NodeFinder())->findInstanceOf([$args[1]->value], ConstFetch::class));
        $used = array_values(array_intersect($constants, $layoutConstants));
        if ($used !== []) {
            $this->notices[] = sprintf('%s defines %s from %s of the CakePHP 2 layout (%s): define it in config/define.php by hand, for the new layout.', $file, $name, implode(', ', $used), $value);

            return null;
        }
        if (!$unconditional) {
            $this->notices[] = sprintf('%s defines %s under a condition: define it in config/define.php by hand.', $file, $name);

            return null;
        }
        $this->kept[$name] = $file;

        return sprintf("\n// Kept from %s\nif (!defined(%s)) {\n    define(%s, %s);\n}\n", $file, var_export($name, true), var_export($name, true), $value);
    }

    /**
     * The code of an argument
     *
     * @param string $code The code of the file
     * @param \PhpParser\Node\Arg $arg The argument
     * @return string
     */
    private function source(string $code, Arg $arg): string
    {
        return substr($code, $arg->value->getStartFilePos(), $arg->value->getEndFilePos() - $arg->value->getStartFilePos() + 1);
    }
}
