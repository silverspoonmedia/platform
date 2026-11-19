<?php

namespace App\Support\Domain;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Text surgery behind the `uninstall.json` edit ops.
 *
 * Every op removes whole lines (or a balanced block) instead of re-encoding the
 * file, so a curated locale pack or a hand-formatted seeder keeps its shape and
 * the diff stays readable. The op vocabulary is closed on purpose: an edit the
 * editor cannot express belongs in the manifest's `notes`, resolved by hand.
 *
 * Ops (see docs/platform/reference-architecture.md):
 *   remove_lines_matching  match: regex, tested against each trimmed line
 *   remove_quoted_strings  match: list of exact string literals (inline arrays)
 *   remove_array_keys      match: list of dotted PHP array paths (`a.b.c`)
 *   remove_block           begin: literal anchor; removes the balanced value after it
 *   remove_json_keys       match: list of dotted JSON paths (`a.b`, `a.`, `.a.`)
 *
 * `remove_array_keys` expects one entry per line (the shape `domain:make` and
 * Laravel produce). An inline list on a single line is `remove_quoted_strings`.
 */
class UninstallFileEditor
{
    /** @var list<string> */
    public const OPS = [
        'remove_lines_matching',
        'remove_quoted_strings',
        'remove_array_keys',
        'remove_block',
        'remove_json_keys',
    ];

    /**
     * @param  array<string, mixed>  $edit  One `edits[]` entry (`op` + its spec).
     * @return array{removed: int, notes: list<string>}
     */
    public function apply(string $absolutePath, array $edit): array
    {
        if (! File::isFile($absolutePath)) {
            return ['removed' => 0, 'notes' => ['file missing: '.$absolutePath]];
        }

        $notes = [];
        [$updated, $removed] = $this->edit(File::get($absolutePath), $edit, $notes);

        if ($removed > 0 && $updated !== File::get($absolutePath)) {
            File::put($absolutePath, $updated);
        }

        return ['removed' => $removed, 'notes' => $notes];
    }

    /**
     * Count the removals the op would perform, without touching the file.
     *
     * @param  array<string, mixed>  $edit
     * @return array{removed: int, notes: list<string>}
     */
    public function preview(string $absolutePath, array $edit): array
    {
        if (! File::isFile($absolutePath)) {
            return ['removed' => 0, 'notes' => ['file missing: '.$absolutePath]];
        }

        $notes = [];
        [, $removed] = $this->edit(File::get($absolutePath), $edit, $notes);

        return ['removed' => $removed, 'notes' => $notes];
    }

    /**
     * @param  array<string, mixed>  $edit
     * @param  list<string>  $notes
     * @return array{0: string, 1: int}
     */
    private function edit(string $contents, array $edit, array &$notes): array
    {
        $op = (string) ($edit['op'] ?? '');

        if (! in_array($op, self::OPS, true)) {
            throw new InvalidArgumentException("Unsupported uninstall op [{$op}].");
        }

        return match ($op) {
            'remove_lines_matching' => $this->removeLinesMatching($contents, $this->requireString($edit, 'match', $op)),
            'remove_quoted_strings' => $this->removeQuotedStrings($contents, $this->requireList($edit, 'match', $op)),
            'remove_array_keys' => $this->removeKeyedPaths($contents, $this->requireList($edit, 'match', $op), '=>'),
            'remove_json_keys' => $this->removeJsonKeys($contents, $this->requireList($edit, 'match', $op), $notes),
            'remove_block' => $this->removeBlock($contents, $this->requireString($edit, 'begin', $op)),
        };
    }

    // ---------------------------------------------------------------- line ops

    /**
     * @return array{0: string, 1: int}
     */
    private function removeLinesMatching(string $contents, string $pattern): array
    {
        $regex = '#'.$pattern.'#';

        if (@preg_match($regex, '') === false) {
            throw new InvalidArgumentException("remove_lines_matching: invalid regex [{$pattern}].");
        }

        $eol = $this->eol($contents);
        $kept = [];
        $removed = 0;

        foreach ($this->splitLines($contents) as $line) {
            if (preg_match($regex, trim($line)) === 1) {
                $removed++;

                continue;
            }

            $kept[] = $line;
        }

        return [$this->joinLines($kept, $eol), $removed];
    }

    /**
     * @param  list<string>  $literals
     * @return array{0: string, 1: int}
     */
    private function removeQuotedStrings(string $contents, array $literals): array
    {
        $removed = 0;
        $count = function () use (&$removed): string {
            $removed++;

            return '';
        };

        foreach ($literals as $literal) {
            $quoted = preg_quote($literal, '/');

            // `'lit', ` — an element that is not the last one.
            $contents = preg_replace_callback("/(['\"]){$quoted}\\1\\s*,\\s*/", $count, $contents) ?? $contents;

            // `, 'lit'` — the last element of an inline list.
            $contents = preg_replace_callback("/,\\s*(['\"]){$quoted}\\1/", $count, $contents) ?? $contents;

            // A bare `'lit'` left over (single-element list, or an assignment).
            $contents = preg_replace_callback("/(['\"]){$quoted}\\1/", $count, $contents) ?? $contents;
        }

        return [$contents, $removed];
    }

    /**
     * Remove balanced `'key' => value` entries by dotted path.
     *
     * @param  list<string>  $paths
     * @return array{0: string, 1: int}
     */
    private function removeKeyedPaths(string $contents, array $paths, string $separator): array
    {
        $removed = 0;

        foreach ($paths as $path) {
            $segments = array_values(array_filter(
                explode('.', $path),
                static fn (string $segment): bool => $segment !== ''
            ));

            if ($segments === []) {
                continue;
            }

            [$contents, $count] = $this->removeKeyedPath($contents, $segments, $separator);
            $removed += $count;
        }

        return [$contents, $removed];
    }

    /**
     * @param  list<string>  $segments
     * @return array{0: string, 1: int}
     */
    private function removeKeyedPath(string $contents, array $segments, string $separator): array
    {
        $key = array_shift($segments);
        $pattern = '/([\'"])'.preg_quote((string) $key, '/').'\\1\\s*'.preg_quote($separator, '/').'/';

        if (preg_match($pattern, $contents, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [$contents, 0];
        }

        $keyOffset = $match[0][1];
        $valueStart = $this->skipWhitespace($contents, $keyOffset + strlen($match[0][0]));

        if ($segments !== []) {
            // Descend into the container value, then recurse for the next segment.
            if (! isset($contents[$valueStart]) || ! $this->isBracket($contents[$valueStart])) {
                return [$contents, 0];
            }

            $close = $this->matchBracket($contents, $valueStart);

            if ($close === null) {
                return [$contents, 0];
            }

            $inner = substr($contents, $valueStart, $close - $valueStart);
            [$inner, $count] = $this->removeKeyedPath($inner, $segments, $separator);

            if ($count === 0) {
                return [$contents, 0];
            }

            return [substr($contents, 0, $valueStart).$inner.substr($contents, $close), $count];
        }

        if (isset($contents[$valueStart]) && $this->isBracket($contents[$valueStart])) {
            $close = $this->matchBracket($contents, $valueStart);

            if ($close === null) {
                return [$contents, 0];
            }

            $entryEnd = $close;
        } else {
            $entryEnd = $this->scalarEnd($contents, $valueStart);
        }

        $entryStart = $this->entryStart($contents, $keyOffset);
        $entryEnd = $this->consumeTrailing($contents, $entryEnd);

        return [substr($contents, 0, $entryStart).substr($contents, $entryEnd), 1];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function removeBlock(string $contents, string $begin): array
    {
        $anchor = strpos($contents, $begin);

        if ($anchor === false) {
            return [$contents, 0];
        }

        $bracket = $this->openingBracket($contents, $anchor);

        if ($bracket === null) {
            return [$contents, 0];
        }

        $close = $this->matchBracket($contents, $bracket);

        if ($close === null) {
            return [$contents, 0];
        }

        $start = $this->lineStart($contents, $bracket);
        $end = $this->consumeTrailing($contents, $close);

        return [substr($contents, 0, $start).substr($contents, $end), 1];
    }

    /**
     * The container the anchor belongs to: the first bracket after it on the same
     * line (`'key' => [`), else the innermost bracket before it that encloses it
     * (a JS object whose `label:` sits on its own line).
     *
     * An empty pair followed by an assignment is skipped: in `foo: Bar[] = [` the
     * `[]` is a type annotation, not the container being removed, and removing it
     * would leave the file broken. An empty value (`'key' => []`) is still the
     * container.
     */
    private function openingBracket(string $contents, int $anchor): ?int
    {
        $limit = strlen($contents);

        for ($i = $anchor; $i < $limit; $i++) {
            if ($contents[$i] === "\n") {
                break;
            }

            if (! $this->isBracket($contents[$i])) {
                continue;
            }

            $close = $this->matchBracket($contents, $i);

            if ($close === null) {
                continue;
            }

            $isEmptyPair = trim(substr($contents, $i + 1, $close - $i - 2)) === '';
            $isTypeAnnotation = preg_match('/^\s*[:=]/', substr($contents, $close, 8)) === 1;

            if ($isEmptyPair && $isTypeAnnotation) {
                continue;
            }

            return $i;
        }

        for ($i = $anchor; $i >= 0; $i--) {
            if (! $this->isBracket($contents[$i])) {
                continue;
            }

            $close = $this->matchBracket($contents, $i);

            if ($close !== null && $close > $anchor) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $matches
     * @param  list<string>  $notes
     * @return array{0: string, 1: int}
     */
    private function removeJsonKeys(string $contents, array $matches, array &$notes): array
    {
        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            $notes[] = 'remove_json_keys: file is not valid JSON';

            return [$contents, 0];
        }

        $selected = [];

        foreach ($this->jsonPaths($decoded) as $path) {
            foreach ($matches as $pattern) {
                if ($this->jsonPathMatches($path, $pattern)) {
                    $selected[] = $path;

                    break;
                }
            }
        }

        // Deepest first, so removing a child never invalidates a parent offset.
        usort($selected, static fn (string $a, string $b): int => substr_count($b, '.') <=> substr_count($a, '.'));

        $removed = 0;

        foreach (array_unique($selected) as $path) {
            [$contents, $count] = $this->removeKeyedPath($contents, explode('.', $path), ':');
            $removed += $count;
        }

        // A container left empty by the removals goes too, so the pack does not
        // keep an empty-namespace ghost.
        $removed += $this->pruneEmptyJsonContainers($contents, $notes);

        return [$contents, $removed];
    }

    /**
     * @param  list<string>  $notes
     */
    private function pruneEmptyJsonContainers(string &$contents, array &$notes): int
    {
        $pruned = 0;

        for ($pass = 0; $pass < 10; $pass++) {
            $decoded = json_decode($contents, true);

            if (! is_array($decoded)) {
                $notes[] = 'remove_json_keys: result is not valid JSON after pruning';

                return $pruned;
            }

            $empties = [];

            foreach ($this->jsonPaths($decoded) as $path) {
                $value = $this->jsonValueAt($decoded, $path);

                if (is_array($value) && $value === []) {
                    $empties[] = $path;
                }
            }

            if ($empties === []) {
                break;
            }

            usort($empties, static fn (string $a, string $b): int => substr_count($b, '.') <=> substr_count($a, '.'));

            $changed = false;

            foreach ($empties as $path) {
                [$candidate, $count] = $this->removeKeyedPath($contents, explode('.', $path), ':');

                if ($count > 0) {
                    $contents = $candidate;
                    $pruned += $count;
                    $changed = true;
                }
            }

            if (! $changed) {
                break;
            }
        }

        return $pruned;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function jsonPaths(array $data, string $prefix = ''): array
    {
        $paths = [];

        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $paths[] = $path;

            if (is_array($value)) {
                $paths = array_merge($paths, $this->jsonPaths($value, $path));
            }
        }

        return $paths;
    }

    private function jsonPathMatches(string $path, string $pattern): bool
    {
        if (str_starts_with($pattern, '.') && str_ends_with($pattern, '.')) {
            return str_contains($path, $pattern);
        }

        if (str_ends_with($pattern, '.')) {
            return str_starts_with($path, $pattern);
        }

        return $path === $pattern;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function jsonValueAt(array $data, string $path): mixed
    {
        $value = $data;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    // ------------------------------------------------------------- primitives

    private function eol(string $contents): string
    {
        return str_contains($contents, "\r\n") ? "\r\n" : "\n";
    }

    /**
     * @return list<string>
     */
    private function splitLines(string $contents): array
    {
        return preg_split('/\R/', $contents) ?: [];
    }

    /**
     * @param  list<string>  $lines
     */
    private function joinLines(array $lines, string $eol): string
    {
        return implode($eol, $lines);
    }

    private function lineStart(string $contents, int $offset): int
    {
        $previous = strrpos(substr($contents, 0, $offset), "\n");

        return $previous === false ? 0 : $previous + 1;
    }

    /**
     * Where the removed span begins: the whole line when the entry owns it, or
     * just the entry when it shares the line with a sibling element.
     */
    private function entryStart(string $contents, int $keyOffset): int
    {
        $start = $this->lineStart($contents, $keyOffset);

        if (trim(substr($contents, $start, $keyOffset - $start)) !== '') {
            while ($start < $keyOffset && ($contents[$start] === ' ' || $contents[$start] === "\t")) {
                $start++;
            }
        }

        return $start;
    }

    private function skipWhitespace(string $contents, int $offset): int
    {
        $limit = strlen($contents);

        while ($offset < $limit && str_contains(" \t\n\r", $contents[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private function isBracket(string $char): bool
    {
        return $char === '[' || $char === '{' || $char === '(';
    }

    /**
     * Offset just past the bracket that closes the one at `$open`, quote-aware.
     */
    private function matchBracket(string $contents, int $open): ?int
    {
        $pairs = ['[' => ']', '{' => '}', '(' => ')'];
        $openChar = $contents[$open];

        if (! isset($pairs[$openChar])) {
            return null;
        }

        $closeChar = $pairs[$openChar];
        $depth = 0;
        $quote = null;
        $limit = strlen($contents);
        $i = $open;

        while ($i < $limit) {
            $char = $contents[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i += 2;

                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                $i++;

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $i++;

                continue;
            }

            if ($char === '/' && ($contents[$i + 1] ?? '') === '/') {
                $newline = strpos($contents, "\n", $i);
                $i = $newline === false ? $limit : $newline + 1;

                continue;
            }

            if ($char === '/' && ($contents[$i + 1] ?? '') === '*') {
                $end = strpos($contents, '*/', $i);
                $i = $end === false ? $limit : $end + 2;

                continue;
            }

            if ($char === $openChar) {
                $depth++;
            } elseif ($char === $closeChar) {
                $depth--;

                if ($depth === 0) {
                    return $i + 1;
                }
            }

            $i++;
        }

        return null;
    }

    /**
     * End of a scalar value: the next top-level comma or the parent's closing bracket.
     */
    private function scalarEnd(string $contents, int $offset): int
    {
        $limit = strlen($contents);
        $depth = 0;
        $quote = null;
        $i = $offset;

        while ($i < $limit) {
            $char = $contents[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i += 2;

                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                $i++;

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $i++;

                continue;
            }

            if ($this->isBracket($char)) {
                $depth++;
            } elseif ($char === ']' || $char === '}' || $char === ')') {
                if ($depth === 0) {
                    return $i;
                }

                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                return $i;
            }

            $i++;
        }

        return $limit;
    }

    /**
     * Step past an optional trailing comment, comma, and the line break.
     */
    private function consumeTrailing(string $contents, int $offset): int
    {
        $limit = strlen($contents);
        $i = $offset;

        $lineEnd = strpos($contents, "\n", $i);
        $lineEnd = $lineEnd === false ? $limit : $lineEnd;

        if (preg_match('/^\s*\/\//', substr($contents, $i, $lineEnd - $i)) === 1) {
            $i = $lineEnd;
        }

        while ($i < $limit && ($contents[$i] === ' ' || $contents[$i] === "\t")) {
            $i++;
        }

        if ($i < $limit && $contents[$i] === ',') {
            $i++;

            while ($i < $limit && ($contents[$i] === ' ' || $contents[$i] === "\t")) {
                $i++;
            }
        }

        if ($i < $limit && $contents[$i] === "\r") {
            $i++;
        }

        if ($i < $limit && $contents[$i] === "\n") {
            $i++;
        }

        return $i;
    }

    /**
     * @param  array<string, mixed>  $edit
     */
    private function requireString(array $edit, string $key, string $op): string
    {
        $value = $edit[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("Op [{$op}] requires a non-empty string [{$key}].");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $edit
     * @return list<string>
     */
    private function requireList(array $edit, string $key, string $op): array
    {
        $value = $edit[$key] ?? null;

        if (! is_array($value) || $value === []) {
            throw new InvalidArgumentException("Op [{$op}] requires a non-empty list [{$key}].");
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
    }
}
