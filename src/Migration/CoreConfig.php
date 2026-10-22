<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Migration;

/**
 * Adds `Configure::write('App.namespace', ...)` to `config/core.php`, and changes nothing else in it.
 *
 * The setting goes just before the docblock of `App.encoding`, as in `config/app.php` of CakePHP 5, where the
 * namespace comes before the encoding. It must come before `Cache::config('_cake_core_')`, which runs while
 * `config/core.php` loads (before `config/bootstrap.php`) and finds the engine with `App::className()`. Without an
 * `App.encoding` line it goes before the first `Cache::config()`; without either, it is left to the user.
 */
final class CoreConfig
{
    /**
     * The setting was added
     *
     * @var string
     */
    public const ADDED = 'added';

    /**
     * The setting has the same value already
     *
     * @var string
     */
    public const SAME = 'same';

    /**
     * The setting has another value
     *
     * @var string
     */
    public const DIFFERENT = 'different';

    /**
     * There is no place for the setting
     *
     * @var string
     */
    public const NO_PLACE = 'no-place';

    /**
     * Adds App.namespace
     *
     * @param string $contents The contents of `config/core.php`
     * @param string $namespace The namespace
     * @return array{string, string, string|null} The result (one of the constants), the new contents, and the value
     *   already set (for `different`) or the line before which the setting was added (for `added`)
     */
    public static function addNamespace(string $contents, string $namespace): array
    {
        if (preg_match('/^[ \t]*Configure::write\(\s*([\'"])App\.namespace\1\s*,\s*(.+?)\s*\)\s*;/m', $contents, $match)) {
            $value = $match[2];
            if (preg_match('/^([\'"])(.*)\1$/s', $value, $literal)) {
                $value = str_replace(['\\\\', '\\' . $literal[1]], ['\\', $literal[1]], $literal[2]);
            }

            return [trim($value, '\\') === $namespace ? self::SAME : self::DIFFERENT, $contents, $value];
        }

        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $lines = explode($eol, $contents);
        $before = self::lineOf($lines, '/^[ \t]*Configure::write\(\s*([\'"])App\.encoding\1/') ?? self::lineOf($lines, '/^[ \t]*Cache::config\(/');
        if ($before === null) {
            return [self::NO_PLACE, $contents, null];
        }
        $line = $lines[$before];
        // Before the docblock of the line
        if ($before > 0 && trim($lines[$before - 1]) === '*/') {
            for ($start = $before - 1; $start >= 0; $start--) {
                if (str_starts_with(ltrim($lines[$start]), '/**')) {
                    $before = $start;
                    break;
                }
            }
        }
        array_splice($lines, $before, 0, [
            '/**',
            ' * The namespace of the classes of the application',
            ' */',
            "Configure::write('App.namespace', '" . $namespace . "');",
            '',
        ]);

        return [self::ADDED, implode($eol, $lines), trim($line)];
    }

    /**
     * The index of the first line that matches a pattern
     *
     * @param array<string> $lines The lines
     * @param string $pattern The pattern
     * @return int|null
     */
    private static function lineOf(array $lines, string $pattern): ?int
    {
        foreach ($lines as $index => $line) {
            if (preg_match($pattern, $line)) {
                return $index;
            }
        }

        return null;
    }
}
