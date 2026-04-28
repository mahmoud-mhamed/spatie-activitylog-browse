<?php

namespace Mhamed\SpatieActivitylogBrowse\Support;

class DeletionLogger
{
    /**
     * Append a deletion entry to the JSON history file.
     * Silently no-ops on failure so cleanup operations are never broken.
     */
    public static function record(array $payload): void
    {
        if (! self::enabled()) {
            return;
        }

        $payload = array_merge([
            'id'        => self::generateId(),
            'timestamp' => now()->toIso8601String(),
        ], $payload);

        $path = self::path();

        try {
            self::ensureDirectory($path);
            self::resetIfTooLarge($path);

            $entries = self::readAll($path);
            array_unshift($entries, $payload);

            $maxEntries = (int) (config('activitylog-browse.deletion_history.max_entries', 500));
            if ($maxEntries > 0 && count($entries) > $maxEntries) {
                $entries = array_slice($entries, 0, $maxEntries);
            }

            self::writeAll($path, $entries);
        } catch (\Throwable) {
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        if (! self::enabled()) {
            return [];
        }

        try {
            return self::readAll(self::path());
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array{entries: array, total: int, page: int, per_page: int, last_page: int} */
    public static function paginate(int $page = 1, int $perPage = 25): array
    {
        $all = self::all();
        $total = count($all);
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        return [
            'entries'   => array_slice($all, $offset, $perPage),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public static function clear(): bool
    {
        $path = self::path();
        try {
            if (is_file($path)) {
                return @unlink($path);
            }
        } catch (\Throwable) {
        }

        return true;
    }

    public static function size(): int
    {
        $path = self::path();
        return is_file($path) ? (int) @filesize($path) : 0;
    }

    public static function enabled(): bool
    {
        return (bool) config('activitylog-browse.deletion_history.enabled', false);
    }

    public static function path(): string
    {
        return (string) config(
            'activitylog-browse.deletion_history.path',
            storage_path('activitylog-browse/deletion-history.json')
        );
    }

    public static function maxSizeBytes(): int
    {
        return ((int) config('activitylog-browse.deletion_history.max_size_mb', 3)) * 1024 * 1024;
    }

    /** @return array<int, array<string, mixed>> */
    protected static function readAll(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<int, array<string, mixed>> $entries */
    protected static function writeAll(string $path, array $entries): void
    {
        $json = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return;
        }

        @file_put_contents($path, $json, LOCK_EX);
    }

    protected static function ensureDirectory(string $path): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        // Drop a .gitignore so the consuming app doesn't accidentally commit
        // the deletion-history.json file (and so Laravel-style storage hygiene
        // is preserved). Only created if missing.
        $gitignore = $dir . DIRECTORY_SEPARATOR . '.gitignore';
        if (! is_file($gitignore)) {
            @file_put_contents($gitignore, "*\n!.gitignore\n");
        }
    }

    protected static function resetIfTooLarge(string $path): void
    {
        if (! is_file($path)) {
            return;
        }

        $max = self::maxSizeBytes();
        if ($max > 0 && @filesize($path) > $max) {
            @file_put_contents($path, '[]', LOCK_EX);
        }
    }

    protected static function generateId(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (\Throwable) {
            return uniqid('', true);
        }
    }
}
