<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Migration;

use PhpParser\Error;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * The files that run an application in the layout of pieceofcake2/app: `config/define.php`, `bin/cake`,
 * `webroot/index.php`, `webroot/test.php` and `webroot/.htaccess`, and the directories of `tmp/` and `logs/`.
 *
 * `layout` creates the missing ones from the copies in `resources/skeleton/` of this plugin, which are the files of
 * pieceofcake2/app; it never overwrites a file. They replace the files of the CakePHP 2 layout (`app/webroot/index.php`,
 * `app/webroot/test.php` and `app/Console/cake`, `cake.php` and `cake.bat`), which define the paths of that layout.
 * The constants of their own that these files define, e.g. `define('SITE_NAME', 'Blog')`, are added to the new
 * `config/define.php`, which all the entry files load; those built on the path of the old file (`__FILE__`,
 * `__DIR__`) are listed instead, as the files moved.
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
     * The entry files of the CakePHP 2 layout that the new files replace
     *
     * @var array<string>
     */
    public const OLD_FILES = ['app/webroot/index.php', 'app/webroot/test.php', 'app/Console/cake', 'app/Console/cake.php', 'app/Console/cake.bat'];

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
     * The entry files to create
     *
     * @param string $root The root of the project
     * @param array<array{string, string}> $moves The moves before, relative to the root, with slashes
     * @return self
     */
    public static function plan(string $root, array $moves): self
    {
        $plan = new self();
        $skeleton = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'skeleton' . DIRECTORY_SEPARATOR;
        $exists = static fn(string $path): bool => self::existsAfter($root, $moves, $path);
        foreach (self::OLD_FILES as $file) {
            if (is_file($root . DIRECTORY_SEPARATOR . $file)) {
                $plan->oldFiles[] = $file;
            }
        }
        $defines = $plan->customDefines($root);
        foreach (self::FILES as $file) {
            if ($exists($file)) {
                continue;
            }
            $contents = (string)file_get_contents($skeleton . $file);
            if ($file === self::DEFINE && $defines !== []) {
                $contents = rtrim($contents) . "\n" . implode('', $defines);
            }
            $plan->files[$file] = $contents;
        }
        if ($plan->kept !== [] && !isset($plan->files[self::DEFINE])) {
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
     * @return array<string> The code of the definitions
     */
    private function customDefines(string $root): array
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
            foreach ((new NodeFinder())->findInstanceOf($stmts, FuncCall::class) as $call) {
                $define = $this->defineOf($code, $file, $call);
                if ($define !== null) {
                    $defines[] = $define;
                }
            }
        }

        return $defines;
    }

    /**
     * The definition for config/define.php of a constant of the application that an old entry file defines
     *
     * @param string $code The code of the file
     * @param string $file The file, relative to the root
     * @param \PhpParser\Node\Expr\FuncCall $call A call
     * @return string|null The code, or null for another call, a constant of CakePHP, or one built on the path of the file
     */
    private function defineOf(string $code, string $file, FuncCall $call): ?string
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
