<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Rector;

/**
 * The places that a rule leaves for a review, written to a file that the upgrade shell prints after Rector.
 *
 * Rector runs the rules in parallel processes and only prints the changes, so the rules append their lines to the
 * file of the environment variable `UPGRADE_RECTOR_REPORT`, which the shell sets.
 */
final class Report
{
    /**
     * The environment variable with the path of the file
     *
     * @var string
     */
    public const ENV = 'UPGRADE_RECTOR_REPORT';

    /**
     * Appends a line for a place of a file
     *
     * @param string $file The file
     * @param int $line The line in the file
     * @param string $message What to review
     * @return void
     */
    public static function add(string $file, int $line, string $message): void
    {
        $report = getenv(self::ENV);
        if ($report === false || $report === '') {
            return;
        }
        $cwd = getcwd();
        if ($cwd !== false && str_starts_with($file, $cwd . DIRECTORY_SEPARATOR)) {
            $file = substr($file, strlen($cwd) + 1);
        }
        file_put_contents($report, sprintf("%s:%d: %s\n", $file, $line, $message), FILE_APPEND | LOCK_EX);
    }
}
