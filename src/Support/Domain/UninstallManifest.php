<?php

namespace App\Support\Domain;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

/**
 * The per-domain `uninstall.json` contract: schema, plan, apply, drift scan.
 *
 * A product domain records everything it added outside `app/Domain/{Name}/` in
 * one manifest, so `domain:remove` can be specific instead of re-auditing the
 * repo every time. The file lives inside the domain directory (it dies with the
 * domain) and is scaffolded by `domain:make`.
 *
 * Shape (`domain-uninstall/v1`):
 *
 *   schema   "domain-uninstall/v1"
 *   domain   "{Name}"                   must equal the registry key
 *   repos    { backend|spa|docs: { delete[], edits[], unresolved[], ignore[] } }
 *   data     { tables[], migrations[], rbac{permissions[],roles[]}, rows[] }
 *   needles  []                         extra literals the drift scan looks for
 *   notes    []                         work the ops cannot express
 *
 * Every reference the scanner finds must be classified: it is inside a `delete`
 * path, it is the target of an `edits[]` op, or it is listed in `ignore`. The
 * reason on an `ignore` entry says why the reference survives, or spells out
 * the hand edit the closed op vocabulary cannot express — so the section is a
 * reviewed record, never a silent allowlist.
 *
 * `unresolved` is the draft-time worklist: hit files nobody has classified yet.
 * `domain:remove` refuses to run while it is not empty (unless `--force`), and
 * the drift guard fails on it, because an unclassified reference is exactly the
 * one a removal forgets.
 */
class UninstallManifest
{
    public const SCHEMA = 'domain-uninstall/v1';

    /** @var list<string> */
    private const EDIT_KEYS = ['file', 'op', 'match', 'begin'];

    public function __construct(
        private DomainManager $domains,
        private UninstallFileEditor $editor,
        private UninstallReferenceScanner $scanner,
    ) {}

    public function path(string $name): string
    {
        return $this->domains->domainPath($name).DIRECTORY_SEPARATOR.'uninstall.json';
    }

    public function relativePath(string $name): string
    {
        return 'app/Domain/'.$this->domains->normalizeName($name).'/uninstall.json';
    }

    public function exists(string $name): bool
    {
        return File::isFile($this->path($name));
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $name): array
    {
        if (! $this->exists($name)) {
            throw new InvalidArgumentException(
                "No uninstall.json for domain [{$this->domains->normalizeName($name)}]. "
                .'Run domain:remove --draft-manifest to create one.'
            );
        }

        $decoded = json_decode(File::get($this->path($name)), true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("uninstall.json for [{$name}] is not valid JSON.");
        }

        $this->validate($decoded, $name);

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    public function validate(array $manifest, string $name): void
    {
        $expected = $this->domains->normalizeName($name);
        $findings = [];

        if (($manifest['schema'] ?? null) !== self::SCHEMA) {
            $findings[] = 'schema must be "'.self::SCHEMA.'"';
        }

        if (($manifest['domain'] ?? null) !== $expected) {
            $findings[] = "domain must be \"{$expected}\"";
        }

        $repos = $manifest['repos'] ?? null;

        if (! is_array($repos) || $repos === []) {
            $findings[] = 'repos must be a non-empty object';
        } else {
            foreach ($repos as $repo => $section) {
                if (! in_array($repo, UninstallReferenceScanner::REPOS, true)) {
                    $findings[] = "repos.{$repo} is not one of ".implode('|', UninstallReferenceScanner::REPOS);

                    continue;
                }

                if (! is_array($section)) {
                    $findings[] = "repos.{$repo} must be an object";

                    continue;
                }

                foreach (['delete'] as $key) {
                    if (isset($section[$key]) && ! $this->isStringList($section[$key], allowEmpty: true)) {
                        $findings[] = "repos.{$repo}.{$key} must be a list of strings";
                    }
                }

                if (isset($section['unresolved'])) {
                    if (! is_array($section['unresolved'])) {
                        $findings[] = "repos.{$repo}.unresolved must be a list";
                    } else {
                        foreach ($section['unresolved'] as $index => $entry) {
                            if (! $this->isReasonedEntry($entry, 'file')) {
                                $findings[] = "repos.{$repo}.unresolved[{$index}] needs a file and a real reason (not TODO)";
                            }
                        }
                    }
                }

                if (isset($section['edits'])) {
                    if (! is_array($section['edits'])) {
                        $findings[] = "repos.{$repo}.edits must be a list";
                    } else {
                        foreach ($section['edits'] as $index => $edit) {
                            foreach ($this->editFindings($edit, "repos.{$repo}.edits[{$index}]") as $finding) {
                                $findings[] = $finding;
                            }
                        }
                    }
                }

                if (isset($section['ignore'])) {
                    if (! is_array($section['ignore'])) {
                        $findings[] = "repos.{$repo}.ignore must be a list";
                    } else {
                        foreach ($section['ignore'] as $index => $entry) {
                            if (! $this->isReasonedEntry($entry, 'path')) {
                                $findings[] = "repos.{$repo}.ignore[{$index}] needs a path and a real reason (not TODO)";
                            }
                        }
                    }
                }
            }
        }

        $data = $manifest['data'] ?? [];

        if (! is_array($data)) {
            $findings[] = 'data must be an object';
        } else {
            foreach (['tables', 'migrations'] as $key) {
                if (isset($data[$key]) && ! $this->isStringList($data[$key], allowEmpty: true)) {
                    $findings[] = "data.{$key} must be a list of strings";
                }
            }

            if (isset($data['rbac']) && ! is_array($data['rbac'])) {
                $findings[] = 'data.rbac must be an object';
            } else {
                foreach (['permissions', 'roles'] as $key) {
                    if (isset($data['rbac'][$key]) && ! $this->isStringList($data['rbac'][$key], allowEmpty: true)) {
                        $findings[] = "data.rbac.{$key} must be a list of strings";
                    }
                }
            }

            if (isset($data['rows'])) {
                if (! is_array($data['rows'])) {
                    $findings[] = 'data.rows must be a list';
                } else {
                    foreach ($data['rows'] as $index => $row) {
                        $valid = is_array($row)
                            && is_string($row['table'] ?? null)
                            && (is_array($row['key'] ?? null) || is_array($row['match'] ?? null));

                        if (! $valid) {
                            $findings[] = "data.rows[{$index}] needs a table and a key or match";
                        }
                    }
                }
            }
        }

        if (isset($manifest['needles']) && ! $this->isStringList($manifest['needles'], allowEmpty: true)) {
            $findings[] = 'needles must be a list of strings';
        }

        if (isset($manifest['notes']) && ! $this->isStringList($manifest['notes'], allowEmpty: true)) {
            $findings[] = 'notes must be a list of strings';
        }

        if ($findings !== []) {
            throw new InvalidArgumentException(
                "Invalid uninstall.json for [{$expected}]:\n  - ".implode("\n  - ", $findings)
            );
        }
    }

    /**
     * @return list<string>
     */
    private function editFindings(mixed $edit, string $label): array
    {
        if (! is_array($edit)) {
            return ["{$label} must be an object"];
        }

        $findings = [];
        $op = $edit['op'] ?? null;

        if (! is_string($op) || ! in_array($op, UninstallFileEditor::OPS, true)) {
            return ["{$label}.op must be one of ".implode('|', UninstallFileEditor::OPS)];
        }

        if (! is_string($edit['file'] ?? null) || $edit['file'] === '') {
            $findings[] = "{$label}.file must be a non-empty string";
        }

        foreach (array_keys($edit) as $key) {
            if (! in_array($key, self::EDIT_KEYS, true)) {
                $findings[] = "{$label}.{$key} is not a recognised key";
            }
        }

        $needsList = ['remove_quoted_strings', 'remove_array_keys', 'remove_json_keys'];

        if (in_array($op, $needsList, true) && ! $this->isStringList($edit['match'] ?? null)) {
            $findings[] = "{$label}.match must be a non-empty list of strings for [{$op}]";
        }

        if ($op === 'remove_lines_matching' && (! is_string($edit['match'] ?? null) || $edit['match'] === '')) {
            $findings[] = "{$label}.match must be a non-empty regex string for [{$op}]";
        }

        if ($op === 'remove_block' && (! is_string($edit['begin'] ?? null) || $edit['begin'] === '')) {
            $findings[] = "{$label}.begin must be a non-empty anchor for [{$op}]";
        }

        return $findings;
    }

    /**
     * A `{file|path, reason}` entry: the reason is mandatory and may not be a
     * placeholder, so an escape hatch cannot be opened without saying why.
     */
    private function isReasonedEntry(mixed $entry, string $pathKey): bool
    {
        if (! is_array($entry) || ! is_string($entry[$pathKey] ?? null) || $entry[$pathKey] === '') {
            return false;
        }

        $reason = trim((string) ($entry['reason'] ?? ''));

        return $reason !== '' && ! str_starts_with(strtoupper($reason), 'TODO');
    }

    private function isStringList(mixed $value, bool $allowEmpty = false): bool
    {
        if (! is_array($value) || (! $allowEmpty && $value === [])) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_string($item) || $item === '') {
                return false;
            }
        }

        return true;
    }

    // ------------------------------------------------------------------- plan

    /**
     * Resolve the manifest into what would happen, without writing anything.
     *
     * @return array{
     *   manifest: array<string, mixed>,
     *   deletes: list<array{repo: string, path: string, matches: list<string>}>,
     *   edits: list<array{repo: string, file: string, op: string, edit: array<string, mixed>, matches: list<string>, removed: int, notes: list<string>}>,
     *   data: array<string, mixed>,
     *   unresolved: list<string>,
     *   unlisted: array<string, array<string, list<array{line: int, needle: string}>>>,
     *   warnings: list<string>,
     * }
     */
    public function plan(string $name): array
    {
        $manifest = $this->load($name);
        $warnings = [];
        $deletes = [];
        $edits = [];
        $unresolved = [];

        foreach ($manifest['repos'] as $repo => $section) {
            $root = $this->scanner->root($repo);

            if ($root === null) {
                $warnings[] = "sibling checkout absent — section [{$repo}] skipped (unverifiable, not clean)";
            }

            foreach ($section['delete'] ?? [] as $path) {
                $matches = $root === null ? [] : $this->resolve($root, $path);
                $deletes[] = ['repo' => $repo, 'path' => $path, 'matches' => $matches];

                if ($root !== null && $matches === []) {
                    $warnings[] = "delete path matches nothing: {$repo}:{$path}";
                }
            }

            foreach ($section['edits'] ?? [] as $edit) {
                $matches = $root === null ? [] : $this->resolve($root, (string) $edit['file']);

                if ($root !== null && $matches === []) {
                    $warnings[] = "edit file matches nothing: {$repo}:{$edit['file']}";
                }

                $removed = 0;
                $notes = [];

                foreach ($matches as $absolute) {
                    $result = $this->editor->preview($absolute, $edit);
                    $removed += $result['removed'];
                    $notes = array_merge($notes, $result['notes']);
                }

                $edits[] = [
                    'repo' => $repo,
                    'file' => (string) $edit['file'],
                    'op' => (string) $edit['op'],
                    'edit' => $edit,
                    'matches' => $matches,
                    'removed' => $removed,
                    'notes' => $notes,
                ];
            }

            foreach ($section['unresolved'] ?? [] as $entry) {
                $unresolved[] = "{$repo}:{$entry['file']} — {$entry['reason']}";
            }

            if ($root === null) {
                continue;
            }

            foreach ($section['ignore'] ?? [] as $entry) {
                if ($this->resolve($root, (string) $entry['path']) === []) {
                    $warnings[] = "ignore path matches nothing: {$repo}:{$entry['path']}";
                }
            }

            foreach ($section['unresolved'] ?? [] as $entry) {
                if ($this->resolve($root, (string) $entry['file']) === []) {
                    $warnings[] = "unresolved file matches nothing: {$repo}:{$entry['file']}";
                }
            }
        }

        $data = is_array($manifest['data'] ?? null) ? $manifest['data'] : [];

        foreach ($data['migrations'] ?? [] as $migration) {
            if (! File::isFile(base_path($migration))) {
                $warnings[] = "migration missing: {$migration}";
            }
        }

        return [
            'manifest' => $manifest,
            'deletes' => $deletes,
            'edits' => $edits,
            'data' => $data,
            'unresolved' => $unresolved,
            'unlisted' => $this->unlistedReferences($name, $manifest),
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    public function unlistedReferences(string $name, ?array $manifest = null): array
    {
        $manifest ??= $this->load($name);
        $data = is_array($manifest['data'] ?? null) ? $manifest['data'] : [];
        $hits = $this->scanner->hitsByFile(
            $name,
            $this->isStringList($manifest['needles'] ?? null, allowEmpty: true) ? $manifest['needles'] : [],
            $this->isStringList($data['tables'] ?? null, allowEmpty: true) ? $data['tables'] : [],
        );

        $out = [];
        $ownDirs = [
            'app/Domain/'.$this->domains->normalizeName($name).'/',
            'tests/Domain/'.$this->domains->normalizeName($name).'/',
        ];

        foreach ($hits as $repo => $files) {
            $section = $manifest['repos'][$repo] ?? [];
            $patterns = array_merge(
                $section['delete'] ?? [],
                array_map(static fn (array $edit): string => (string) $edit['file'], $section['edits'] ?? []),
                array_map(static fn (array $entry): string => (string) $entry['file'], $section['unresolved'] ?? []),
                array_map(static fn (array $entry): string => (string) $entry['path'], $section['ignore'] ?? []),
            );

            foreach ($files as $relative => $lines) {
                if ($repo === UninstallReferenceScanner::REPO_BACKEND) {
                    if ($this->scanner->isStructuralFile($relative) || $this->scanner->isManifestFile($relative)) {
                        continue;
                    }

                    $insideOwn = false;

                    foreach ($ownDirs as $dir) {
                        if (str_starts_with($relative, $dir)) {
                            $insideOwn = true;

                            break;
                        }
                    }

                    if ($insideOwn || in_array($relative, $data['migrations'] ?? [], true)) {
                        continue;
                    }
                }

                if ($this->scanner->pathCoveredBy($relative, $patterns)) {
                    continue;
                }

                $out[$repo][$relative] = $lines;
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ apply

    /**
     * Execute the file half of the plan (deletes + edits). Data purge is separate
     * and guarded by the caller.
     *
     * @param  array<string, mixed>|null  $plan  A plan from plan(), to avoid re-scanning.
     * @return array{deleted: int, edited: int, removed: int, warnings: list<string>}
     */
    public function applyFiles(string $name, ?array $plan = null): array
    {
        $plan ??= $this->plan($name);
        $deleted = 0;
        $edited = 0;
        $removed = 0;
        $warnings = $plan['warnings'];

        // Edits run before deletes: a manifest must not edit a file it deletes,
        // and this order surfaces that mistake as a "file missing" note.
        foreach ($plan['edits'] as $entry) {
            foreach ($entry['matches'] as $absolute) {
                $result = $this->editor->apply($absolute, $entry['edit']);
                $edited++;
                $removed += $result['removed'];

                foreach ($result['notes'] as $note) {
                    $warnings[] = $entry['repo'].':'.$entry['file'].' — '.$note;
                }
            }
        }

        foreach ($plan['deletes'] as $entry) {
            foreach ($entry['matches'] as $absolute) {
                if (is_dir($absolute)) {
                    File::deleteDirectory($absolute);
                } else {
                    File::delete($absolute);
                }

                $deleted++;
            }
        }

        return ['deleted' => $deleted, 'edited' => $edited, 'removed' => $removed, 'warnings' => $warnings];
    }

    /**
     * Purge the recorded data: tables, rows, RBAC, migration files.
     *
     * @param  array<string, mixed>  $data
     * @return list<string> warnings
     */
    public function purgeData(string $name, array $data): array
    {
        $warnings = [];

        foreach ($data['rows'] ?? [] as $row) {
            $table = (string) $row['table'];

            if (! Schema::hasTable($table)) {
                $warnings[] = "row purge skipped, table missing: {$table}";

                continue;
            }

            $query = DB::table($table);

            if (is_array($row['key'] ?? null)) {
                $query->where($row['key']);
            } elseif (is_array($row['match'] ?? null)) {
                $column = (string) ($row['match']['column'] ?? '');
                $operator = (string) ($row['match']['operator'] ?? '=');
                $value = (string) ($row['match']['value'] ?? '');
                $query->where($column, $operator, $value);
            }

            $query->delete();
        }

        foreach ($data['rbac']['roles'] ?? [] as $role) {
            Role::query()->where('name', $role)->delete();
        }

        foreach ($data['rbac']['permissions'] ?? [] as $permission) {
            Permission::query()->where('name', $permission)->delete();
        }

        foreach ($data['tables'] ?? [] as $table) {
            Schema::dropIfExists($table);
        }

        foreach ($data['migrations'] ?? [] as $migration) {
            $absolute = base_path($migration);

            if (File::isFile($absolute)) {
                File::delete($absolute);
            } else {
                $warnings[] = "migration missing: {$migration}";
            }
        }

        return $warnings;
    }

    // ------------------------------------------------------------------ draft

    /**
     * Candidate manifest from the live scan: conventional deletes that exist,
     * inferred edits, and the rest as `unresolved` work items.
     *
     * @return array<string, mixed>
     */
    public function draft(string $name): array
    {
        $name = $this->domains->normalizeName($name);
        $slug = $this->domains->slug($name);
        $hits = $this->scanner->hitsByFile($name);
        $repos = [];
        $draftData = $this->draftData($name, $slug);

        foreach (UninstallReferenceScanner::REPOS as $repo) {
            $root = $this->scanner->root($repo);

            if ($root === null) {
                $repos[$repo] = ['delete' => [], 'edits' => [], 'unresolved' => []];

                continue;
            }

            $candidates = $this->conventionalDeletes($repo, $name, $slug);
            $delete = [];

            foreach ($candidates as $candidate) {
                if ($this->resolve($root, $candidate) !== []) {
                    $delete[] = $candidate;
                }
            }

            $edits = [];
            $unresolved = [];

            foreach ($hits[$repo] ?? [] as $relative => $lines) {
                if ($this->scanner->pathCoveredBy($relative, $delete) || $this->scanner->isStructuralFile($relative)) {
                    continue;
                }

                if ($repo === UninstallReferenceScanner::REPO_BACKEND) {
                    $own = ['app/Domain/'.$name.'/', 'tests/Domain/'.$name.'/'];

                    if ($this->scanner->pathCoveredBy($relative, $own)
                        || $this->scanner->isManifestFile($relative)
                        || in_array($relative, $draftData['migrations'], true)) {
                        continue;
                    }
                }

                $inferred = $this->inferEdit($repo, $root, $relative, $lines, $name, $slug);

                if ($inferred === null) {
                    $unresolved[] = [
                        'file' => $relative,
                        'reason' => 'unclassified: '.count($lines).' hit(s) at line '
                            .implode(', ', array_slice(array_column($lines, 'line'), 0, 8)),
                    ];

                    continue;
                }

                $edits[] = $inferred;
            }

            $repos[$repo] = [
                'delete' => $delete,
                'edits' => $this->mergeEdits($edits),
                'unresolved' => $unresolved,
                'ignore' => [],
            ];
        }

        return [
            'schema' => self::SCHEMA,
            'domain' => $name,
            'repos' => $repos,
            'data' => $draftData,
            'needles' => [],
            'notes' => ['Draft from the reference scan. Classify every `unresolved` entry (an edit op, a delete, or an `ignore` with a reason), then run domain:remove --dry-run until it is clean.'],
        ];
    }

    /**
     * @return list<string>
     */
    private function conventionalDeletes(string $repo, string $name, string $slug): array
    {
        if ($repo === UninstallReferenceScanner::REPO_BACKEND) {
            return [
                'app/Domain/'.$name,
                'app/Providers/Domain/'.$name.'ServiceProvider.php',
                'routes/api/v1/'.$slug.'.php',
                'routes/api/v1/admin-'.$slug.'.php',
                'routes/web/'.$slug.'.php',
                'routes/web/'.$slug.'-public.php',
                'tests/Domain/'.$name,
                'database/seeders/'.$name.'RoleSeeder.php',
                'database/factories/Domain/'.$name,
                'config/'.$slug.'.php',
                'lang/*/api/'.$slug.'.php',
                'scripts/'.$slug,
                'docs/domains/'.$slug,
            ];
        }

        if ($repo === UninstallReferenceScanner::REPO_SPA) {
            return [
                'src/views/'.$slug,
                'src/composables/'.$slug,
                'src/components/'.$slug,
                'src/router/routes/'.$slug.'.ts',
                'tests/'.$slug.'*',
                'docs/domains/'.$slug,
            ];
        }

        return ['docs/'.$slug];
    }

    /**
     * @param  list<array{line: int, needle: string}>  $hits
     * @return array<string, mixed>|null
     */
    private function inferEdit(string $repo, string $root, string $relative, array $hits, string $name, string $slug): ?array
    {
        $absolute = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $lines = array_column($hits, 'line');

        if ($repo === UninstallReferenceScanner::REPO_BACKEND) {
            if (str_starts_with($relative, 'lang/')) {
                $paths = [];

                foreach ($lines as $line) {
                    $parent = $this->scanner->phpKeyPathAt($absolute, $line);
                    $key = $this->scanner->keyAt($absolute, $line);

                    if ($key === null) {
                        return null;
                    }

                    $paths[] = $parent === null || $parent === '' ? $key : $parent.'.'.$key;
                }

                return ['file' => $relative, 'op' => 'remove_array_keys', 'match' => array_values(array_unique($paths))];
            }

            if (str_contains($relative, '.env') && str_ends_with($relative, '.example')) {
                $pattern = '/^([A-Z0-9_]*'.strtoupper(str_replace('-', '_', $slug)).'[A-Z0-9_]*)=/';
                $keys = [];

                foreach ($this->scanner->readLines($absolute) as $text) {
                    if (preg_match($pattern, $text, $match) === 1) {
                        $keys[] = $match[1];
                    }
                }

                if ($keys === []) {
                    return null;
                }

                return [
                    'file' => $relative,
                    'op' => 'remove_lines_matching',
                    'match' => '^(?:'.implode('|', array_map(static fn (string $key): string => preg_quote($key, '/'), $keys)).')=',
                ];
            }

            if (in_array($relative, ['config/spa-routes.php', 'database/seeders/RoleSeeder.php'], true)) {
                $literals = [];

                foreach ($lines as $line) {
                    $literal = $this->scanner->literalAt($absolute, $line);

                    if ($literal !== null && $literal !== $slug) {
                        $literals[] = $literal;
                    }
                }

                if ($literals === []) {
                    return null;
                }

                return ['file' => $relative, 'op' => 'remove_quoted_strings', 'match' => array_values(array_unique($literals))];
            }

            if ($relative === 'config/products.php' || str_starts_with($relative, 'config/settings')) {
                return ['file' => $relative, 'op' => 'remove_block', 'begin' => "'{$name}' =>"];
            }

            return null;
        }

        if ($repo === UninstallReferenceScanner::REPO_SPA) {
            if (str_starts_with($relative, 'src/i18n/') && str_ends_with($relative, '.json')) {
                $paths = [];

                foreach ($lines as $line) {
                    $parent = $this->scanner->jsonKeyPathAt($absolute, $line);
                    $key = $this->scanner->keyAt($absolute, $line);

                    if ($key === null) {
                        return null;
                    }

                    $paths[] = $parent === null || $parent === '' ? $key : $parent.'.'.$key;
                }

                return ['file' => $relative, 'op' => 'remove_json_keys', 'match' => array_values(array_unique($paths))];
            }

            // Only the router registry is confidently line-scoped; a component or
            // a test that mentions the domain needs a human decision.
            if ($relative === 'src/router/index.ts') {
                return ['file' => $relative, 'op' => 'remove_lines_matching', 'match' => preg_quote($slug, '/')];
            }

            return null;
        }

        if ($relative === 'docs-coverage.json') {
            // `capabilities` is a flat map whose keys contain dots
            // (`product.<slug>`, `<slug>.<capability>`), so a dotted JSON path
            // cannot address them — the op is line-based instead.
            $keys = [];

            foreach ($lines as $line) {
                $key = $this->scanner->keyAt($absolute, $line);

                if ($key === null) {
                    return null;
                }

                $keys[] = preg_quote($key, '/');
            }

            return [
                'file' => $relative,
                'op' => 'remove_lines_matching',
                'match' => '^\s*"(?:'.implode('|', array_values(array_unique($keys))).')"\s*:',
            ];
        }

        return null;
    }

    /**
     * Collapse inferred edits that share a file and op into one entry.
     *
     * @param  list<array<string, mixed>>  $edits
     * @return list<array<string, mixed>>
     */
    private function mergeEdits(array $edits): array
    {
        $merged = [];

        foreach ($edits as $edit) {
            $key = $edit['file'].'|'.$edit['op'];

            if (isset($merged[$key])) {
                $existing = $merged[$key]['match'];
                $incoming = $edit['match'];

                $merged[$key]['match'] = is_array($existing) && is_array($incoming)
                    ? array_values(array_unique(array_merge($existing, $incoming)))
                    : $incoming;

                continue;
            }

            $merged[$key] = $edit;
        }

        return array_values($merged);
    }

    /**
     * @return array<string, mixed>
     */
    private function draftData(string $name, string $slug): array
    {
        $migrations = [];

        foreach (File::files(database_path('migrations')) as $file) {
            if (str_contains($file->getFilename(), $slug)) {
                $migrations[] = 'database/migrations/'.$file->getFilename();
            }
        }

        sort($migrations);

        return [
            'tables' => [],
            'migrations' => $migrations,
            'rbac' => ['permissions' => [], 'roles' => ['Beta tester '.$name]],
            'rows' => [
                ['table' => 'product_domain_visibilities', 'key' => ['product_key' => $name]],
                ['table' => 'product_activations', 'key' => ['product_key' => $name]],
                ['table' => 'product_analytics', 'key' => ['product_key' => $name]],
            ],
        ];
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @return list<string> absolute paths
     */
    public function resolve(string $root, string $relative): array
    {
        $relative = str_replace('\\', '/', trim($relative));

        if ($relative === '') {
            return [];
        }

        $absolute = rtrim($root, '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (! str_contains($relative, '*')) {
            return file_exists($absolute) ? [$absolute] : [];
        }

        $matches = glob($absolute) ?: [];
        sort($matches);

        return $matches;
    }

    /**
     * Write the draft next to the domain and return its path.
     */
    public function writeDraft(string $name): string
    {
        $path = $this->path($name);

        if (File::isFile($path)) {
            throw new RuntimeException("uninstall.json already exists for [{$name}] — delete it to re-draft.");
        }

        $draft = $this->draft($name);

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($draft, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        return $path;
    }
}
