<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Migration;

/**
 * Checks of paths with the case of their names, which `is_dir()` ignores on case-insensitive file systems: there,
 * `is_dir('Config')` is also true for `config/`.
 */
final class Paths
{
    /**
     * The names of the files and directories of a directory
     *
     * @param string $dir The directory
     * @return array<string>
     */
    public static function entries(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $entries = array_values(array_diff((array)scandir($dir), ['.', '..']));
        sort($entries);

        return $entries;
    }

    /**
     * Whether a directory holds a file or directory of this name, with this case
     *
     * @param string $path The path
     * @return bool
     */
    public static function exists(string $path): bool
    {
        return file_exists($path) && in_array(basename($path), self::entries(dirname($path)), true);
    }

    /**
     * Whether a directory holds a directory of this name, with this case
     *
     * @param string $dir The parent directory
     * @param string $name The name
     * @return bool
     */
    public static function hasDirectory(string $dir, string $name): bool
    {
        return is_dir($dir . DIRECTORY_SEPARATOR . $name) && in_array($name, self::entries($dir), true);
    }
}
