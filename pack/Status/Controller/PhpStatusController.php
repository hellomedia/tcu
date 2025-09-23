<?php

namespace Pack\Status\Controller;

use App\Controller\BaseController;
use Pack\Status\Exception\UnexpectedConfigException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Check php config
 *
 * Returns 204 if config seems OK
 * Throws (return 500) if config is not what we expect
 * 
 * Usage:
 *  - visit /_check/php
 *  - ping /_check/php in uptime robot to get alerts
 */
class PhpStatusController extends BaseController
{
    private const SHORT_OPEN_TAG = "0";
    // Uploads
    private const UPLOAD_MAX_FILESIZE = "50M";
    private const POST_MAX_SIZE = "100M"; // >= upload_max_filesize
    // Memory (per php process)
    // https://tideways.com/profiler/blog/new-feature-max-memory-monitoring
    //  Useful to configure right for two reasons:
    //    - Set too low and an increasing number of requests fail due to memory limit reached errors.
    //    - Set too high reduces the theoretical number of PHP processes that can run on a single server.
    // Php's peak memory usage (shown per request in symfony's profiler) is a good indicator for setting a value for this.
    // Our app's requests have a peak memory usage usually < 80M.
    // Something else to consider, this value must be higher than post_max_size.
    // A value of 128M, used for 2 days, was fine, but caused caused a memory exhausted error in composer during deployment.
    // For now, 256M is used to keep things simple.
    // Could be optimized further if we want to run the app on a server with lower specs:
    // - Composer can run with a flag to override the memory limit.
    // - We could avoid running composer on the server during deployment.
    private const MEMORY_LIMIT = "256M";  // per php-fpm process. > post_max_size. does not include opcache memory.
    // Date
    private const TIMEZONE = "Europe/Brussels";
    // Realpath cache
    // https://symfony.com/doc/current/performance.html
    // https://tideways.com/profiler/blog/how-does-the-php-realpath-cache-work-and-how-to-configure-it
    private const REALPATH_CACHE_SIZE = "4M";
    private const REALPATH_CACHE_TTL = "600";

    private array $issues = [];

    public function __construct(
        private string $environment,
        private string $projectDir,
    ) {
    }

    #[Route(name: "php_check", path: "/_check/php")]
    public function checkPhpConfig()
    {
        $this->_checkBaseConfig();

        $this->_checkRealpathCacheConfig();

        $this->_checkRealpathCacheStatus();

        if (!empty($this->issues)) {
            throw new UnexpectedConfigException("Unexpected php config: " . implode(', ', $this->issues));
        }

        return new Response(content: 'OK', status: Response::HTTP_OK);
    }

    private function _checkBaseConfig(): void
    {
        $uploadMaxFileSize = \ini_get('upload_max_filesize');
        $postMaxSize = \ini_get('post_max_size');
        $memoryLimit = \ini_get('memory_limit');
        $timezone = \ini_get('date.timezone');
        $shortOpenTag = \ini_get('short_open_tag'); // https://www.php.net/manual/en/ini.core.php#ini.short-open-tag

        if ($this->_convertToBytes($uploadMaxFileSize) != $this->_convertToBytes(self::UPLOAD_MAX_FILESIZE)) {
            $this->_addUnexpectedConfig(
                config: 'upload_max_filesize',
                value: $uploadMaxFileSize,
                expectedValue: self::UPLOAD_MAX_FILESIZE
            );
        }

        if ($this->_convertToBytes($postMaxSize) != $this->_convertToBytes(self::POST_MAX_SIZE)) {
            $this->_addUnexpectedConfig(
                config: 'post_max_size',
                value: $postMaxSize,
                expectedValue: self::POST_MAX_SIZE
            );
        }

        if ($this->_convertToBytes($memoryLimit) != $this->_convertToBytes(self::MEMORY_LIMIT)) {
            $this->_addUnexpectedConfig(
                config: 'memory_limit',
                value: $memoryLimit,
                expectedValue: self::MEMORY_LIMIT
            );
        }

        if ($timezone != self::TIMEZONE) {
            $this->_addUnexpectedConfig(
                config: 'date.timezone',
                value: $timezone,
                expectedValue: self::TIMEZONE
            );
        }

        // \ini_get('short_open_tag') can return "" or "0", and both are casted to false
        // https://www.php.net/manual/en/function.ini-get.php
        // https://www.php.net/manual/en/function.boolval.php
        if ((bool) $shortOpenTag != false) {
            $this->_addUnexpectedConfig(
                config: 'short_open_tag',
                value: $shortOpenTag,
                expectedValue: self::SHORT_OPEN_TAG,
            );
        }
    }

    private function _checkRealpathCacheConfig(): void
    {
        $size = \ini_get('realpath_cache_size');
        $ttl = \ini_get('realpath_cache_ttl');

        if ($this->_convertToBytes($size) != $this->_convertToBytes(self::REALPATH_CACHE_SIZE)) {
            $this->_addUnexpectedConfig(
                config: 'realpath_cache_size',
                value: $size,
                expectedValue: self::REALPATH_CACHE_SIZE
            );
        }

        if ((int) $ttl != (int) self::REALPATH_CACHE_TTL) {
            $this->_addUnexpectedConfig(
                config: 'realpath_cache_ttl',
                value: $ttl,
                expectedValue: self::REALPATH_CACHE_TTL
            );
        }
    }

    private function _checkRealpathCacheStatus(): void
    {
        $size = \ini_get('realpath_cache_size');
        $usage = realpath_cache_size();

        $remaining = $this->_convertToBytes($size) - $usage;

        // typical entry size is roughly 100-200 bytes
        $typicalRealpathEntrySize = 150;

        if ($remaining < (50 * $typicalRealpathEntrySize)) {
            $this->issues[] = "realpath cache low (remaining {$remaining} bytes)";
        }
    }

    private function _addUnexpectedConfig(string $config, string $value, string $expectedValue): void
    {
        $this->issues[] = "{$config} {$value} (expected {$expectedValue})";
    }

    /**
     * Convert human-readable size to bytes
     */
    private function _convertToBytes(string $size): int
    {
        if (\is_numeric($size)) {
            return $size;
        }

        $unit = strtoupper(substr($size, -1));
        $size = (int) $size;

        return match ($unit) {
            'K' => $size * 1024,
            'M' => $size * 1024 * 1024,
            'G' => $size * 1024 * 1024 * 1024,
        };
    }
}
