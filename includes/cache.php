<?php
// Tiny file-backed TTL cache for read-heavy values (dashboard counts,
// notification stats, etc.). No Redis required — works on any host with
// a writable temp dir. Single-key reads/writes are atomic via rename().
//
// Use sparingly. Anything user-specific should NOT use this without a
// per-user key. Anything sensitive should NOT use this at all.

function getAppCacheDir(): string {
    static $dir = null;
    if ($dir !== null) return $dir;

    $base = getenv('APP_CACHE_DIR');
    if (!$base) {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ewallet_cache';
    }
    if (!is_dir($base)) {
        @mkdir($base, 0700, true);
    }
    $dir = $base;
    return $dir;
}

function appCacheKeyToPath(string $key): string {
    // Hash so any string is filesystem-safe and length-bounded.
    return getAppCacheDir() . DIRECTORY_SEPARATOR . sha1($key) . '.cache';
}

/**
 * Get a cached value or compute it via $producer and cache the result.
 *
 * @param string   $key      cache key
 * @param int      $ttl      seconds before the cached value is considered stale
 * @param callable $producer fn(): mixed — invoked on miss/expiry
 * @return mixed
 */
function rememberCached(string $key, int $ttl, callable $producer) {
    $path = appCacheKeyToPath($key);

    if (is_file($path) && (time() - filemtime($path)) < $ttl) {
        $raw = @file_get_contents($path);
        if ($raw !== false) {
            $decoded = @unserialize($raw);
            if ($decoded !== false || $raw === serialize(false)) {
                return $decoded;
            }
        }
    }

    $value = $producer();

    // Write atomically: temp file + rename so partial writes never become
    // visible to other requests reading concurrently.
    $tmp = $path . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, serialize($value), LOCK_EX) !== false) {
        @rename($tmp, $path);
    }

    return $value;
}

/**
 * Invalidate a single cache entry. Call after any write that would change it.
 */
function forgetCached(string $key): void {
    $path = appCacheKeyToPath($key);
    if (is_file($path)) {
        @unlink($path);
    }
}
