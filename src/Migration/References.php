<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Migration;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Finds the lines of the files that still name paths of the old layout, which `upgrade layout` and
 * `upgrade namespace` list for a review instead of changing them: they are often in texts and comments, where only a
 * person can tell whether to change them.
 *
 * - After `layout`: the template names of CakePHP 2 (`home.ctp`, `view.thtml`) and the directories `Layouts/`,
 *   `Elements/`, `Emails/` and `Errors/`, e.g. `templates/Pages/home.ctp` in a text of the home page.
 * - After `namespace`: the classmap of Composer and `composer dump-autoload`, which PSR-4 replaces, and the paths of
 *   `src/Lib/` that moved, e.g. `src/Lib/Error` in a comment of `config/core.php`.
 */
final class References
{
    /**
     * The template names and directories of CakePHP 2
     *
     * @var string
     */
    public const TEMPLATES = '/\.(?:ctp|thtml)\b|(?<![\w-])(?:Layouts|Elements|Emails|Errors)(?=[\/\\\\]|\'\s*\.\s*DS\b)/';

    /**
     * The largest file that is read, in bytes
     *
     * @var int
     */
    private const MAX_SIZE = 1048576;

    /**
     * The pattern of the names that `upgrade namespace` leaves behind: the classmap, `composer dump-autoload` and the
     * paths of `src/Lib/<Type>/`, which moved to `src/<Type>/`, also `src/Lib/` itself when it is gone
     *
     * @param bool $noLib Whether there is no `src/Lib/`
     * @return string
     */
    public static function namespacePattern(bool $noLib): string
    {
        $lib = '\bLib\/(?:' . implode('|', NamespacePlan::TYPES) . ')\b';

        return '/(?i:\bclassmap\b)|\bdump-autoload\b|' . $lib . ($noLib ? '|\bsrc\/Lib\b' : '') . '/';
    }

    /**
     * The lines of the files of the directories that match a pattern, as `<file>:<line>: <text>`, with the paths
     * after the moves (the files are still at their places in a dry run)
     *
     * @param string $root The root, which the paths are relative to
     * @param array<string> $dirs The directories, relative to the root, with slashes
     * @param string $pattern The regular expression
     * @param array<string> $skip The directories to leave out, relative to the root, with slashes
     * @param array<array{string, string}> $moves The moves that are not made yet, absolute paths
     * @return array<string>
     */
    public static function find(string $root, array $dirs, string $pattern, array $skip = [], array $moves = []): array
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $targets = array_map(static fn(string $dir): string => $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir), $dirs);
        $sources = $targets;
        foreach ($moves as [$from, $to]) {
            if (self::inside($to, $targets)) {
                $sources[] = $from;
            }
        }
        $files = [];
        foreach (array_unique($sources) as $source) {
            foreach (self::files($source) as $file) {
                $moved = self::moved($file, $moves);
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($moved, strlen($root) + 1));
                if (self::inside($moved, $targets) && !self::skipped($relative, $skip)) {
                    $files[$relative] = $file;
                }
            }
        }
        ksort($files, SORT_STRING);
        $lines = [];
        foreach ($files as $relative => $file) {
            foreach (self::matches((string)file_get_contents($file), $pattern) as $number => $text) {
                $lines[] = sprintf('%s:%d: %s', $relative, $number, $text);
            }
        }

        return $lines;
    }

    /**
     * The lines of a text that match a pattern, shortened around the first match
     *
     * @param string $contents The text
     * @param string $pattern The regular expression
     * @return array<int, string> The lines, by number
     */
    public static function matches(string $contents, string $pattern): array
    {
        if (str_contains(substr($contents, 0, 8000), "\0")) {
            return [];
        }
        $matches = [];
        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $index => $line) {
            if (!preg_match($pattern, $line, $match, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $text = trim($line);
            if (strlen($text) > 120) {
                $offset = max(0, (int)$match[0][1] - (strlen($line) - strlen(ltrim($line))) - 50);
                $text = ($offset > 0 ? '...' : '') . substr($text, $offset, 110) . ($offset + 110 < strlen($text) ? '...' : '');
            }
            $matches[$index + 1] = $text;
        }

        return $matches;
    }

    /**
     * The files of a directory, or the file itself
     *
     * @param string $path A directory or a file
     * @return array<string>
     */
    private static function files(string $path): array
    {
        if (is_file($path)) {
            return filesize($path) <= self::MAX_SIZE ? [$path] : [];
        }
        if (!is_dir($path)) {
            return [];
        }
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && $file->getSize() <= self::MAX_SIZE) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * The path of a file after the moves
     *
     * @param string $path The path now
     * @param array<array{string, string}> $moves The moves
     * @return string
     */
    private static function moved(string $path, array $moves): string
    {
        foreach ($moves as [$from, $to]) {
            if ($path === $from || str_starts_with($path, $from . DIRECTORY_SEPARATOR)) {
                $path = $to . substr($path, strlen($from));
            }
        }

        return $path;
    }

    /**
     * Whether a path is in one of the directories
     *
     * @param string $path The path
     * @param array<string> $dirs The directories
     * @return bool
     */
    private static function inside(string $path, array $dirs): bool
    {
        foreach ($dirs as $dir) {
            if ($path === $dir || str_starts_with($path, $dir . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a path is in one of the directories to leave out
     *
     * @param string $relative The path, relative to the root, with slashes
     * @param array<string> $skip The directories
     * @return bool
     */
    private static function skipped(string $relative, array $skip): bool
    {
        foreach ($skip as $dir) {
            if (str_starts_with($relative, rtrim($dir, '/') . '/')) {
                return true;
            }
        }

        return false;
    }
}
