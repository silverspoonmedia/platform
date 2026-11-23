<?php

namespace App\Support\Domain;

use Illuminate\Support\Str;
use SplFileInfo;

/**
 * Finds every place a product domain is referenced, across the three checkouts.
 *
 * The scan is the discovery half of the uninstall workflow: `domain:remove
 * --draft-manifest` turns it into a candidate manifest, and
 * `DomainUninstallManifestTest` uses it to prove that nothing a domain touched
 * is missing from its `uninstall.json`. A reference is "covered" when its file
 * is inside a `delete` path, is the target of an `edits[]` entry, is listed in
 * `ignore` (with a reason), or is one of the structural files `domain:remove`
 * already rewrites.
 *
 * The SPA and docs checkouts are siblings of this repo. Absent (CI checks out
 * one repo), a section is reported as unverifiable instead of clean — the same
 * convention as `scripts/docs-guard.php`.
 */
class UninstallReferenceScanner
{
    public const REPO_BACKEND = 'backend';

    public const REPO_SPA = 'spa';

    public const REPO_DOCS = 'docs';

    /** @var list<string> */
    public const REPOS = [self::REPO_BACKEND, self::REPO_SPA, self::REPO_DOCS];

    /** Files `domain:remove` rewrites structurally; never an "unlisted reference". */
    private const STRUCTURAL_FILES = [
        'config/domains.php',
        'bootstrap/providers.php',
        'routes/api/v1.php',
        'routes/web.php',
        'database/seeders/DatabaseSeeder.php',
    ];

    /** @var list<string> */
    private const SKIP_DIRS = [
        '.git',
        '.cursor',
        '.docusaurus',
        '.vite',
        '.wrangler',
        '.tmp-i18n',
        '.phpstan.cache',
        'node_modules',
        'vendor',
        'dist',
        'build',
        'coverage',
        'storage',
        'bootstrap/cache',
        'public/build',
    ];

    /**
     * Files that are not repo content: machine-local overrides `.gitignore`
     * excludes (`/database/seeders/data/*.local.php`).
     *
     * They exist on a developer machine and are absent from a clean checkout, so
     * scanning them makes the manifest — and the drift guard — depend on who runs
     * it. There is no "unverifiable" escape for a backend path the way there is
     * for an absent sibling checkout, so the reference is neither scanned nor
     * recorded; a removal cannot act on a file that never ships, and the hand edit
     * the override still needs belongs in the manifest's `notes`.
     *
     * @var list<string>
     */
    private const SKIP_FILE_PATTERNS = [
        'database/seeders/data/*.local.php',
    ];

    /**
     * Extensions the reference scan reads.
     *
     * Markdown is deliberately absent: prose mentions are the docs-sync
     * contract's job (`docs-coverage.json`, the `docs` repo's `check:docs`), not
     * this guard's. Scanning prose made every archive note and glossary entry a
     * finding, which is how a guard becomes noise and then an allowlist.
     *
     * @var list<string>
     */
    private const SCAN_EXTENSIONS = [
        'php', 'json', 'jsonc', 'ts', 'tsx', 'mjs', 'js', 'vue', 'yml', 'yaml', 'example',
    ];

    /** @var list<string> */
    private const SCAN_FILENAMES = ['_headers'];

    private const MAX_FILE_BYTES = 2_097_152;

    /**
     * @return array<string, string> repo => absolute root (siblings may be absent)
     */
    public function roots(): array
    {
        $parent = dirname(base_path());

        return [
            self::REPO_BACKEND => base_path(),
            self::REPO_SPA => $parent.DIRECTORY_SEPARATOR.$this->siblingDirectory(self::REPO_SPA, 'monolith-vue-workers'),
            self::REPO_DOCS => $parent.DIRECTORY_SEPARATOR.$this->siblingDirectory(self::REPO_DOCS, 'docs'),
        ];
    }

    /**
     * The sibling checkout's directory name.
     *
     * A fork's pair is not named after the original (`cored-backend` next to
     * `cored-frontend`), and a hardcoded sibling name would make its `spa` section
     * permanently unverifiable — the removal would report "checked" while never
     * reading the SPA. `config/domains.php` `siblings` names the directories; the
     * monolith's defaults keep every existing path and warning unchanged.
     */
    private function siblingDirectory(string $repo, string $default): string
    {
        $configured = config('domains.siblings.'.$repo);

        return is_string($configured) && $configured !== '' ? $configured : $default;
    }

    public function root(string $repo): ?string
    {
        $root = $this->roots()[$repo] ?? null;

        return $root !== null && is_dir($root) ? $root : null;
    }

    public function siblingPresent(string $repo): bool
    {
        return $repo === self::REPO_BACKEND || $this->root($repo) !== null;
    }

    /**
     * Needles that identify a domain without a hand-written list. Extra literals
     * (`needles`) and table names come from the manifest.
     *
     * @param  list<string>  $extra
     * @param  list<string>  $tables
     * @return list<string>
     */
    public function needles(string $name, array $extra = [], array $tables = []): array
    {
        $slug = Str::kebab($name);

        $needles = [
            'App\\Domain\\'.$name.'\\',
            'App\\Providers\\Domain\\'.$name.'ServiceProvider',
            $name.'ServiceProvider',
            $name.'RoleSeeder',
            'Beta tester '.$name,
            'domain.access:'.$name,
            'product.active:'.$name,
            "'".$slug.'.',
            '"'.$slug.'.',
            $slug.'.php',
            "'".$name."' =>",
            'product.'.$slug,
            'docs/domains/'.$slug,
            // Bare tokens: a domain name is a distinctive single word, and the
            // bare form is what catches `XCatalog`, `XException`, `@/views/x`,
            // `dirName: 'x'`. Prose files are out of scope already (see
            // SCAN_EXTENSIONS), so this stays signal.
            $name,
            $slug,
        ];

        foreach (array_merge($extra, $tables) as $needle) {
            if (is_string($needle) && $needle !== '') {
                $needles[] = $needle;
            }
        }

        return array_values(array_unique($needles));
    }

    /**
     * Every hit, grouped by repo and repo-relative file path.
     *
     * @param  list<string>  $extra
     * @param  list<string>  $tables
     * @return array<string, array<string, list<array{line: int, needle: string}>>>
     */
    public function hitsByFile(string $name, array $extra = [], array $tables = []): array
    {
        $needles = $this->needles($name, $extra, $tables);
        $out = [];

        foreach (self::REPOS as $repo) {
            $root = $this->root($repo);

            if ($root === null) {
                continue;
            }

            $out[$repo] = $this->scanRepo($root, $needles);
        }

        return $out;
    }

    /**
     * @param  list<string>  $needles
     * @return array<string, list<array{line: int, needle: string}>>
     */
    private function scanRepo(string $root, array $needles): array
    {
        $hits = [];

        foreach ($this->walk($root) as $path) {
            $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
            $lines = @file($path, FILE_IGNORE_NEW_LINES);

            if ($lines === false) {
                continue;
            }

            foreach ($lines as $index => $line) {
                foreach ($needles as $needle) {
                    if (str_contains($line, $needle)) {
                        $hits[$relative][] = ['line' => $index + 1, 'needle' => $needle];

                        break;
                    }
                }
            }
        }

        ksort($hits);

        return $hits;
    }

    /**
     * Scannable files under a root, skipping build output and vendored code by
     * repo-relative path (a basename check would miss `bootstrap/cache`).
     *
     * @return \Generator<string>
     */
    private function walk(string $root): \Generator
    {
        $filter = new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            function (SplFileInfo $file) use ($root): bool {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

                if ($this->isSkipped($relative)) {
                    return false;
                }

                return $file->isDir() || $this->isScannable($file);
            }
        );

        $iterator = new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::LEAVES_ONLY);

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile()) {
                yield $file->getPathname();
            }
        }
    }

    private function isSkipped(string $relative): bool
    {
        foreach (self::SKIP_DIRS as $skip) {
            if ($relative === $skip || str_starts_with($relative, $skip.'/')) {
                return true;
            }
        }

        foreach (self::SKIP_FILE_PATTERNS as $pattern) {
            if (fnmatch($pattern, $relative)) {
                return true;
            }
        }

        return false;
    }

    private function isScannable(SplFileInfo $file): bool
    {
        if ($file->getSize() > self::MAX_FILE_BYTES) {
            return false;
        }

        $name = $file->getFilename();

        if (in_array($name, self::SCAN_FILENAMES, true)) {
            return true;
        }

        return in_array(strtolower($file->getExtension()), self::SCAN_EXTENSIONS, true);
    }

    /**
     * Is this relative path inside (or equal to) a manifest path that may hold a
     * glob?
     *
     * @param  list<string>  $patterns
     */
    public function pathCoveredBy(string $relative, array $patterns): bool
    {
        $relative = str_replace('\\', '/', $relative);

        foreach ($patterns as $pattern) {
            $pattern = str_replace('\\', '/', (string) $pattern);

            if ($pattern === '') {
                continue;
            }

            if (fnmatch($pattern, $relative)) {
                return true;
            }

            // A directory entry covers everything beneath it.
            if (! str_contains($pattern, '*') && str_starts_with($relative, rtrim($pattern, '/').'/')) {
                return true;
            }
        }

        return false;
    }

    public function isStructuralFile(string $relative): bool
    {
        return in_array(str_replace('\\', '/', $relative), self::STRUCTURAL_FILES, true);
    }

    /**
     * A domain's own `uninstall.json`. Manifests are records: they name other
     * domains in delete paths, ignore reasons and notes, and that mention is not
     * a live reference to clean up.
     */
    public function isManifestFile(string $relative): bool
    {
        return preg_match('#^app/Domain/[^/]+/uninstall\.json$#', str_replace('\\', '/', $relative)) === 1;
    }

    /**
     * The dotted PHP array path that encloses `$line` (1-based), or null.
     *
     * Walks the preceding lines tracking `'key' => [` openers by indentation,
     * which is the shape Laravel lang files and seeders use. A one-line array
     * (`'key' => ['a' => 1]`) is balanced and never opens a scope.
     */
    public function phpKeyPathAt(string $absolutePath, int $line): ?string
    {
        $lines = @file($absolutePath, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return null;
        }

        $stack = [];

        for ($i = 0; $i < min($line, count($lines)); $i++) {
            $text = $lines[$i];

            if (preg_match('/^\s*[\]\)]/', $text) === 1) {
                while ($stack !== []) {
                    array_pop($stack);

                    break;
                }
            }

            if (preg_match('/^(\s*)[\'"]([^\'"]+)[\'"]\s*=>\s*([\[(])/', $text, $match) !== 1) {
                continue;
            }

            $after = substr($text, strpos($text, $match[3]));

            if (substr_count($after, '[') + substr_count($after, '(')
                <= substr_count($after, ']') + substr_count($after, ')')) {
                continue;
            }

            $stack[] = ['indent' => strlen($match[1]), 'key' => $match[2]];
        }

        return $stack === [] ? null : implode('.', array_column($stack, 'key'));
    }

    /**
     * The dotted JSON path that encloses `$line` (1-based), or null.
     *
     * A one-line entry (`"key": { "a": 1 }`) opens and closes on the same line,
     * so only an unbalanced container pushes onto the stack.
     */
    public function jsonKeyPathAt(string $absolutePath, int $line): ?string
    {
        $lines = @file($absolutePath, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return null;
        }

        $stack = [];

        for ($i = 0; $i < min($line, count($lines)); $i++) {
            $text = $lines[$i];
            $leading = ltrim($text, " \t");

            if ($leading !== '' && ($leading[0] === '}' || $leading[0] === ']')) {
                foreach (str_split($leading) as $char) {
                    if ($char !== '}' && $char !== ']') {
                        break;
                    }

                    if ($stack !== []) {
                        array_pop($stack);
                    }
                }
            }

            if (preg_match('/^\s*"((?:[^"\\\\]|\\\\.)+)"\s*:\s*([{\[])/', $text, $match) !== 1) {
                continue;
            }

            $after = substr($text, strpos($text, $match[2]));

            if (substr_count($after, '{') + substr_count($after, '[')
                <= substr_count($after, '}') + substr_count($after, ']')) {
                continue;
            }

            $stack[] = stripcslashes($match[1]);
        }

        return $stack === [] ? null : implode('.', $stack);
    }

    /**
     * The `'literal'` / `"literal"` on a 1-based line, or null.
     */
    public function literalAt(string $absolutePath, int $line): ?string
    {
        $lines = @file($absolutePath, FILE_IGNORE_NEW_LINES);

        if ($lines === false || ! isset($lines[$line - 1])) {
            return null;
        }

        if (preg_match_all('/[\'"]([^\'"]+)[\'"]/', $lines[$line - 1], $matches) === 0) {
            return null;
        }

        // The last literal on the line is the value/key nearest the needle.
        return $matches[1][count($matches[1]) - 1] ?? null;
    }

    /**
     * The key on a 1-based line of a `'key' => …` / `"key": …` file, or null.
     */
    public function keyAt(string $absolutePath, int $line): ?string
    {
        $lines = @file($absolutePath, FILE_IGNORE_NEW_LINES);

        if ($lines === false || ! isset($lines[$line - 1])) {
            return null;
        }

        if (preg_match('/[\'"]([^\'"]+)[\'"]\s*(?:=>|:)/', $lines[$line - 1], $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    /**
     * @return list<string>
     */
    public function readLines(string $absolutePath): array
    {
        return @file($absolutePath, FILE_IGNORE_NEW_LINES) ?: [];
    }
}
