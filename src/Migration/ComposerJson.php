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
        $changes = [];
        $unknown = [];
        $move = function (string $path, string $where) use ($moves, &$changes, &$unknown): string {
            $normalized = self::normalize($path);
            foreach ($moves as $from => $to) {
                if ($normalized === $from || str_starts_with($normalized, $from . '/')) {
                    $new = $to . substr($normalized, strlen($from)) . (str_ends_with($path, '/') ? '/' : '');
                    $changes[] = sprintf('%s: %s => %s', $where, $path, $new);

                    return $new;
                }
            }
            foreach (array_keys($moves) as $from) {
                if (str_starts_with($from, $normalized . '/')) {
                    $unknown[] = sprintf('%s: %s', $where, $path);
                    break;
                }
            }

            return $path;
        };
        foreach (['autoload', 'autoload-dev'] as $section) {
            if (!isset($this->data[$section]) || !is_array($this->data[$section])) {
                continue;
            }
            $before = $this->data[$section];
            foreach ($this->data[$section] as $type => $entries) {
                if (!is_array($entries)) {
                    continue;
                }
                foreach ($entries as $key => $entry) {
                    if (is_string($entry)) {
                        $this->data[$section][$type][$key] = $move($entry, $section . '.' . $type);
                    } elseif (is_array($entry)) {
                        foreach ($entry as $index => $path) {
                            $this->data[$section][$type][$key][$index] = $move((string)$path, $section . '.' . $type);
                        }
                    }
                }
            }
            if ($this->data[$section] !== $before) {
                $this->changed[$section] = true;
            }
        }
        $paths = $this->data['extra']['installer-paths'] ?? null;
        if (is_array($paths)) {
            $new = [];
            foreach ($paths as $path => $types) {
                $new[$move((string)$path, 'extra.installer-paths')] = $types;
            }
            if ($new !== $paths) {
                $this->data['extra']['installer-paths'] = $new;
                $this->changed['extra'] = true;
            }
        }

        return [$changes, $unknown];
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
            // A new section after require-dev, require or autoload, else at the end
            $after = null;
            foreach (['autoload', 'require-dev', 'require'] as $previous) {
                if (isset($spans[$previous])) {
                    $after = $spans[$previous][1];
                    break;
                }
            }
            $entry = $indent . json_encode($section) . ': ' . $value;
            if ($after !== null) {
                $contents = substr($contents, 0, $after) . ',' . $eol . $entry . substr($contents, $after);
            } else {
                $close = (int)strrpos($contents, '}');
                $body = rtrim(substr($contents, 0, $close));
                $separator = str_ends_with($body, '{') ? '' : ',';
                $contents = $body . $separator . $eol . $entry . $eol . substr($contents, $close);
            }
        }

        return $contents;
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
        $spans = [];
        $depth = 0;
        $length = strlen($json);
        $key = null;
        $expectKey = false;
        $start = null;
        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            if ($char === '"') {
                $end = $i + 1;
                while ($end < $length && $json[$end] !== '"') {
                    $end += $json[$end] === '\\' ? 2 : 1;
                }
                if ($depth === 1 && $expectKey) {
                    $key = (string)json_decode(substr($json, $i, $end - $i + 1));
                    $expectKey = false;
                } elseif ($depth === 1 && $start === null && $key !== null) {
                    $spans[$key] = [$i, $end + 1];
                    $key = null;
                }
                $i = $end;
                continue;
            }
            if ($char === '{' || $char === '[') {
                if ($depth === 1 && $key !== null && $start === null) {
                    $start = $i;
                }
                $depth++;
                if ($depth === 1) {
                    $expectKey = true;
                }
                continue;
            }
            if ($char === '}' || $char === ']') {
                $depth--;
                if ($depth === 1 && $start !== null && $key !== null) {
                    $spans[$key] = [$start, $i + 1];
                    $start = null;
                    $key = null;
                }
                continue;
            }
            if ($depth === 1 && $char === ',') {
                $expectKey = true;
                continue;
            }
            if ($depth === 1 && $key !== null && $start === null && !ctype_space($char) && $char !== ':') {
                // A number, true, false or null
                $end = $i;
                while ($end < $length && !in_array($json[$end], [',', '}'], true) && !ctype_space($json[$end])) {
                    $end++;
                }
                $spans[$key] = [$i, $end];
                $key = null;
                $i = $end - 1;
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
