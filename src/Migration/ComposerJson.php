<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Migration;

use InvalidArgumentException;

/**
 * Switches the autoloading of a `composer.json` to PSR-4, and keeps the rest of the file as it is written.
 *
 * Only the `autoload` and `autoload-dev` sections are written again, with the indentation of the file; the other
 * entries of these sections are kept.
 */
final class ComposerJson
{
    /**
     * The decoded file
     *
     * @var array<string, mixed>
     */
    private array $data;

    /**
     * The sections to write again
     *
     * @var array<string, true>
     */
    private array $changed = [];

    /**
     * @param string $contents The contents of `composer.json`
     */
    public function __construct(private string $contents)
    {
        $data = json_decode($contents, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('composer.json is no valid JSON: ' . json_last_error_msg());
        }
        $this->data = $data;
    }

    /**
     * Adds a PSR-4 namespace, unless the namespace is already there
     *
     * @param string $section `autoload` or `autoload-dev`
     * @param string $namespace The namespace, e.g. `App\`
     * @param string $path The directory, e.g. `src/`
     * @return bool Whether it was added
     */
    public function addPsr4(string $section, string $namespace, string $path): bool
    {
        if (isset($this->data[$section]['psr-4'][$namespace])) {
            return false;
        }
        $this->data[$section]['psr-4'][$namespace] = $path;
        $this->changed[$section] = true;

        return true;
    }

    /**
     * Removes the classmap entries of a directory and of its subdirectories, e.g. `src/` and `src/Model/`
     *
     * @param string $section `autoload` or `autoload-dev`
     * @param string $path The directory, e.g. `src/`
     * @param array<string> $keep Entries to keep, e.g. `src/Cake/`
     * @return array<string> The removed entries
     */
    public function removeClassmap(string $section, string $path, array $keep = []): array
    {
        $entries = $this->data[$section]['classmap'] ?? null;
        if (!is_array($entries)) {
            return [];
        }
        $prefix = self::normalize($path);
        $keep = array_map([self::class, 'normalize'], $keep);
        $removed = [];
        foreach ($entries as $index => $entry) {
            $normalized = self::normalize((string)$entry);
            if (($normalized === $prefix || str_starts_with($normalized, $prefix . '/')) && !in_array($normalized, $keep, true)) {
                $removed[] = (string)$entry;
                unset($entries[$index]);
            }
        }
        if ($removed === []) {
            return [];
        }
        if ($entries === []) {
            unset($this->data[$section]['classmap']);
        } else {
            $this->data[$section]['classmap'] = array_values($entries);
        }
        $this->changed[$section] = true;

        return $removed;
    }

    /**
     * Adds an entry to a list of a section (`classmap`, `exclude-from-classmap`), unless it is there
     *
     * @param string $section `autoload` or `autoload-dev`
     * @param string $key The list
     * @param string $entry The entry
     * @return bool Whether it was added
     */
    public function addToList(string $section, string $key, string $entry): bool
    {
        $list = $this->data[$section][$key] ?? [];
        if (!is_array($list) || in_array(self::normalize($entry), array_map(fn($item): string => self::normalize((string)$item), $list), true)) {
            return false;
        }
        $list[] = $entry;
        $this->data[$section][$key] = $list;
        $this->changed[$section] = true;

        return true;
    }

    /**
     * Replaces the paths of the autoloading and of `extra.installer-paths` that are in moved directories
     *
     * @param array<string, string> $moves The new paths of the directories, by old path, relative to the file
     * @return array{array<string>, array<string>} The changes, and the paths in moved directories that have no new
     *   path (e.g. `app/`)
     */
    public function movePaths(array $moves): array
    {
        $moves = array_combine(array_map([self::class, 'normalize'], array_keys($moves)), array_map([self::class, 'normalize'], $moves));
        uksort($moves, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
        $result = [[], []];
        foreach (['autoload', 'autoload-dev'] as $section) {
            $this->moveSectionPaths($section, $moves, $result);
        }
        $installerPaths = $this->data['extra']['installer-paths'] ?? null;
        if (is_array($installerPaths)) {
            $new = [];
            foreach ($installerPaths as $path => $types) {
                $new[$this->movePath((string)$path, $moves, 'extra.installer-paths', $result)] = $types;
            }
            if ($new !== $installerPaths) {
                $this->data['extra']['installer-paths'] = $new;
                $this->changed['extra'] = true;
            }
        }

        return $result;
    }

    /**
     * Replaces the paths of a section of the autoloading that are in moved directories: classmap and files are lists of
     * paths, psr-0 and psr-4 map namespaces to a path or a list of them
     *
     * @param string $section `autoload` or `autoload-dev`
     * @param array<string, string> $moves The new paths, by old path, normalized, the longest first
     * @param array{array<string>, array<string>} $result The changes and the paths that cannot be moved
     * @return void
     */
    private function moveSectionPaths(string $section, array $moves, array &$result): void
    {
        if (!isset($this->data[$section]) || !is_array($this->data[$section])) {
            return;
        }
        $before = $this->data[$section];
        foreach ($this->data[$section] as $type => $entries) {
            foreach (is_array($entries) ? $entries : [] as $key => $entry) {
                if (!is_string($entry) && !is_array($entry)) {
                    continue;
                }
                $paths = [];
                foreach ((array)$entry as $index => $path) {
                    $paths[$index] = $this->movePath((string)$path, $moves, $section . '.' . $type, $result);
                }
                $this->data[$section][$type][$key] = is_array($entry) ? $paths : $paths[0];
            }
        }
        if ($this->data[$section] !== $before) {
            $this->changed[$section] = true;
        }
    }

    /**
     * The new path of a path in a moved directory
     *
     * @param string $path The path
     * @param array<string, string> $moves The new paths, by old path, normalized, the longest first
     * @param string $where The setting, for the messages
     * @param array{array<string>, array<string>} $result The changes and the paths that cannot be moved
     * @return string The new path, or the path
     */
    private function movePath(string $path, array $moves, string $where, array &$result): string
    {
        $normalized = self::normalize($path);
        foreach ($moves as $from => $to) {
            if ($normalized === $from || str_starts_with($normalized, $from . '/')) {
                $new = $to . substr($normalized, strlen($from)) . (str_ends_with($path, '/') ? '/' : '');
                $result[0][] = sprintf('%s: %s => %s', $where, $path, $new);

                return $new;
            }
        }
        foreach (array_keys($moves) as $from) {
            if (str_starts_with($from, $normalized . '/')) {
                $result[1][] = sprintf('%s: %s', $where, $path);
                break;
            }
        }

        return $path;
    }

    /**
     * The value of a setting of `config`
     *
     * @param string $key The setting
     * @return mixed
     */
    public function config(string $key): mixed
    {
        return $this->data['config'][$key] ?? null;
    }

    /**
     * Whether a section changed
     *
     * @return bool
     */
    public function isChanged(): bool
    {
        return $this->changed !== [];
    }

    /**
     * The contents with the changed sections
     *
     * @return string
     */
    public function contents(): string
    {
        $contents = $this->contents;
        $indent = preg_match('/^([ \t]+)"/m', $contents, $match) ? $match[1] : '    ';
        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        foreach (['autoload', 'autoload-dev', 'extra'] as $section) {
            if (!isset($this->changed[$section])) {
                continue;
            }
            $value = $this->format($this->data[$section] ?? [], $indent, 1, $eol);
            $spans = $this->topLevelSpans($contents);
            if (isset($spans[$section])) {
                [$start, $end] = $spans[$section];
                $contents = substr($contents, 0, $start) . $value . substr($contents, $end);
                continue;
            }
            $contents = $this->insertSection($contents, $indent . json_encode($section) . ': ' . $value, $spans, $eol);
        }

        return $contents;
    }

    /**
     * Inserts a new section after require-dev, require or autoload, else at the end
     *
     * @param string $contents The JSON text
     * @param string $entry The key and the value of the section
     * @param array<string, array{int, int}> $spans The offsets of the values of the top-level keys
     * @param string $eol The line ending
     * @return string
     */
    private function insertSection(string $contents, string $entry, array $spans, string $eol): string
    {
        foreach (['autoload', 'require-dev', 'require'] as $previous) {
            if (isset($spans[$previous])) {
                $after = $spans[$previous][1];

                return substr($contents, 0, $after) . ',' . $eol . $entry . substr($contents, $after);
            }
        }
        $close = (int)strrpos($contents, '}');
        $body = rtrim(substr($contents, 0, $close));
        $separator = str_ends_with($body, '{') ? '' : ',';

        return $body . $separator . $eol . $entry . $eol . substr($contents, $close);
    }

    /**
     * Formats a value as composer.json writes it, with lists of strings on one line when they are short
     *
     * @param mixed $value The value
     * @param string $indent The indentation of one level
     * @param int $level The level of the value
     * @param string $eol The line ending
     * @return string
     */
    private function format(mixed $value, string $indent, int $level, string $eol): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if (!is_array($value)) {
            return (string)json_encode($value, $flags);
        }
        if ($value === []) {
            return '{}';
        }
        $padding = str_repeat($indent, $level + 1);
        if (array_keys($value) === range(0, count($value) - 1)) {
            $scalars = array_filter($value, 'is_scalar') === $value;
            $inline = '[' . implode(', ', array_map(fn($item): string => (string)json_encode($item, $flags), $value)) . ']';
            if ($scalars && strlen($inline) <= 80) {
                return $inline;
            }
            $items = array_map(fn($item): string => $padding . $this->format($item, $indent, $level + 1, $eol), $value);

            return '[' . $eol . implode(',' . $eol, $items) . $eol . str_repeat($indent, $level) . ']';
        }
        $items = [];
        foreach ($value as $key => $item) {
            $items[] = $padding . json_encode((string)$key, $flags) . ': ' . $this->format($item, $indent, $level + 1, $eol);
        }

        return '{' . $eol . implode(',' . $eol, $items) . $eol . str_repeat($indent, $level) . '}';
    }

    /**
     * The offsets of the values of the top-level keys
     *
     * @param string $json The JSON text
     * @return array<string, array{int, int}> The start and end offsets, by key
     */
    private function topLevelSpans(string $json): array
    {
        // Strings, punctuation, and the other values (numbers, true, false, null)
        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"|[{}\[\],:]|[^\s{}\[\],:"]+/', $json, $tokens, PREG_OFFSET_CAPTURE);
        $spans = [];
        $depth = 0;
        $key = null;
        $start = null;
        $expectKey = false;
        foreach ($tokens[0] as [$token, $offset]) {
            if ($token === '{' || $token === '[') {
                if ($depth === 1 && $key !== null && $start === null) {
                    $start = $offset;
                }
                $depth++;
                $expectKey = $depth === 1;
            } elseif ($token === '}' || $token === ']') {
                $depth--;
                if ($depth === 1 && $key !== null && $start !== null) {
                    $spans[$key] = [$start, $offset + 1];
                    $key = $start = null;
                }
            } elseif ($depth === 1 && $token === ',') {
                $expectKey = true;
            } elseif ($depth === 1 && $token !== ':') {
                if ($expectKey) {
                    $key = (string)json_decode($token);
                    $expectKey = false;
                } elseif ($key !== null) {
                    $spans[$key] = [$offset, $offset + strlen($token)];
                    $key = null;
                }
            }
        }

        return $spans;
    }

    /**
     * A path without `./` and the trailing slash
     *
     * @param string $path The path
     * @return string
     */
    private static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        return rtrim($path, '/');
    }
}
