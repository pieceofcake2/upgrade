<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Migration;

use FilesystemIterator;

/**
 * Moves files and directories, with `git mv` in a Git repository so that Git keeps their history, else with
 * `rename()`.
 *
 * A directory moved onto an existing directory is merged into it. A file is never overwritten: the move is skipped
 * and listed in the warnings. A rename that only changes the case of a name (`Config` to `config`) goes through a
 * temporary name, so that it also works on case-insensitive file systems.
 */
final class Mover
{
    /**
     * Whether the root is in a Git repository, once checked
     *
     * @var bool|null
     */
    private ?bool $git = null;

    /**
     * The files that were not moved, and why
     *
     * @var array<string>
     */
    private array $warnings = [];

    /**
     * @param string $root The directory of the application or plugin, with the paths of the messages relative to it
     */
    public function __construct(private string $root)
    {
        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
    }

    /**
     * Whether the root is in a Git repository, so that the files are moved with `git mv`
     *
     * @return bool
     */
    public function usesGit(): bool
    {
        if ($this->git === null) {
            $output = [];
            exec('git -C ' . escapeshellarg($this->root) . ' rev-parse --is-inside-work-tree 2>/dev/null', $output, $code);
            $this->git = $code === 0 && trim(implode('', $output)) === 'true';
        }

        return $this->git;
    }

    /**
     * Moves a file or a directory
     *
     * @param string $from The absolute path of the file or directory
     * @param string $to The absolute path of the destination
     * @return void
     */
    public function move(string $from, string $to): void
    {
        if (!file_exists($from) || $from === $to) {
            return;
        }
        if (strtolower($from) === strtolower($to)) {
            // Only the case changes
            $tmp = $from . '.upgrade-' . getmypid();
            $this->rename($from, $tmp);
            $this->move($tmp, $to);

            return;
        }
        if (file_exists($to)) {
            if (is_dir($from) && is_dir($to)) {
                foreach (new FilesystemIterator($from, FilesystemIterator::SKIP_DOTS) as $child) {
                    $this->move($child->getPathname(), $to . DIRECTORY_SEPARATOR . $child->getFilename());
                }
                $this->removeEmpty($from);

                return;
            }
            $this->warnings[] = sprintf('%s was not moved: %s exists', $this->relative($from), $this->relative($to));

            return;
        }
        if (!is_dir(dirname($to))) {
            mkdir(dirname($to), 0777, true);
        }
        $this->rename($from, $to);
    }

    /**
     * Whether a file can be removed with `git rm`, so that Git keeps it in its history
     *
     * @param string $path The absolute path
     * @return bool
     */
    public function canRemove(string $path): bool
    {
        return is_file($path) && $this->usesGit() && $this->isTracked($path);
    }

    /**
     * Whether Git tracks a file and it has no changes since the last commit, so that Git keeps its contents when it is
     * overwritten
     *
     * @param string $path The absolute path
     * @return bool
     */
    public function isCommitted(string $path): bool
    {
        if (!$this->canRemove($path)) {
            return false;
        }
        exec(sprintf('git -C %s diff --quiet HEAD -- %s 2>/dev/null', escapeshellarg($this->root), escapeshellarg($path)), $output, $code);

        return $code === 0;
    }

    /**
     * Removes a file with `git rm`, if Git tracks it. Git refuses to remove a file with changes that it has not
     * committed, so they are never lost.
     *
     * @param string $path The absolute path
     * @return bool Whether it was removed
     */
    public function remove(string $path): bool
    {
        if (!$this->canRemove($path)) {
            return false;
        }
        exec(sprintf('git -C %s rm -q -- %s 2>&1', escapeshellarg($this->root), escapeshellarg($path)), $output, $code);

        return $code === 0;
    }

    /**
     * Removes a directory if it holds no files, and its empty subdirectories
     *
     * @param string $dir The directory
     * @return void
     */
    public function removeEmpty(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }
        foreach (new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS) as $child) {
            if ($child->isDir()) {
                $this->removeEmpty($child->getPathname());
            }
        }
        if ((new FilesystemIterator($dir))->valid() === false) {
            rmdir($dir);
        }
    }

    /**
     * The files that were not moved, and why
     *
     * @return array<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * A path relative to the root
     *
     * @param string $path The absolute path
     * @return string
     */
    public function relative(string $path): string
    {
        return str_starts_with($path, $this->root . DIRECTORY_SEPARATOR) ? substr($path, strlen($this->root) + 1) : $path;
    }

    /**
     * Renames a file or directory whose destination does not exist
     *
     * @param string $from Source
     * @param string $to Destination
     * @return void
     */
    private function rename(string $from, string $to): void
    {
        if ($this->usesGit() && $this->isTracked($from)) {
            exec(sprintf('git -C %s mv -- %s %s 2>&1', escapeshellarg($this->root), escapeshellarg($from), escapeshellarg($to)), $output, $code);
            if ($code === 0) {
                return;
            }
        }
        rename($from, $to);
    }

    /**
     * Whether Git tracks a file, or a file of a directory
     *
     * @param string $path The path
     * @return bool
     */
    private function isTracked(string $path): bool
    {
        $output = [];
        exec(sprintf('git -C %s ls-files -- %s 2>/dev/null', escapeshellarg($this->root), escapeshellarg($path)), $output, $code);

        return $code === 0 && $output !== [];
    }
}
