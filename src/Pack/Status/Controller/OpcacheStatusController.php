<?php

namespace App\Pack\Status\Controller;

use App\Controller\BaseController;
use App\Pack\Status\Exception\OpcacheIssueException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Check opcache status (full, etc.)
 *
 * Returns 204 if status seems OK
 * Throws (return 500) if issue (opcache full)
 * 
 * Usage:
 *  - visit /_check/opcache
 *  - ping /_check/opcache in uptime robot to get alerts
 */
class OpcacheStatusController extends BaseController
{
    public function __construct(
        private string $environment,
        private string $projectDir,
    ) {
    }

    // Opcache preloading
    // https://symfony.com/doc/current/performance.html
    // UNUSED -- does not seem that important since inheritance caching in php 8.1
    // https://externals.io/message/113091
    // https://github.com/php/php-src/pull/6627#issuecomment-775869140
    // private const OPCACHE_PRELOAD = "/config/preload.php";
    private const OPCACHE_PRELOAD = "";
    // Opcache
    // https://symfony.com/doc/current/performance.html
    private const OPCACHE_ENABLE = "1";
    private const OPCACHE_MEMORY_CONSUMPTION = "2048";
    private const OPCACHE_MAX_ACCELERATED_FILES = "150000"; // max number of keys (files in cache)
    private const OPCACHE_VALIDATE_TIMESTAMPS = "0"; // 0 => never update cached files (restart required)

    private array $issues = [];

    #[Route(name: "opcache_check", path: "/_check/opcache")]
    public function checkOpcache()
    {
        $this->_checkConfig();

        $this->_checkStatus();

        $this->_checkPreloadingConfig();

        if (!empty($this->issues)) {
            throw new OpcacheIssueException("Opcache issue: " . implode(', ', $this->issues));
        }

        return new Response(content: 'OK', status: Response::HTTP_OK);
    }

    private function _checkConfig(): void
    {
        $opcacheEnable = \ini_get('opcache.enable');
        $opcacheMemoryConsumption = \ini_get('opcache.memory_consumption');
        $opcacheMaxAcceleratedFiles = \ini_get('opcache.max_accelerated_files');
        $opcacheValidateTimestamps = \ini_get('opcache.validate_timestamps');

        if ((bool) $opcacheEnable != (bool) self::OPCACHE_ENABLE) {
            $this->_addUnexpectedConfig(
                config: 'opcache.enable',
                value: $opcacheEnable,
                expectedValue: self::OPCACHE_ENABLE
            );
        }

        $opcacheStatus = \opcache_get_status();

        if ($opcacheStatus['opcache_enabled'] == false) {
            $this->_addUnexpectedConfig(
                config: 'opcache_enabled',
                value: 'false',
                expectedValue: 'true'
            );
        }

        if ((int) $opcacheMemoryConsumption != (int) self::OPCACHE_MEMORY_CONSUMPTION) {
            $this->_addUnexpectedConfig(
                config: 'opcache.memory_consumption',
                value: $opcacheMemoryConsumption,
                expectedValue: self::OPCACHE_MEMORY_CONSUMPTION
            );
        }

        if ((int) $opcacheMaxAcceleratedFiles != (int) self::OPCACHE_MAX_ACCELERATED_FILES) {
            $this->_addUnexpectedConfig(
                config: 'opcache.max_accelerated_files',
                value: $opcacheMaxAcceleratedFiles,
                expectedValue: self::OPCACHE_MAX_ACCELERATED_FILES
            );
        }

        if ((bool) $opcacheValidateTimestamps != (bool) self::OPCACHE_VALIDATE_TIMESTAMPS) {
            $this->_addUnexpectedConfig(
                config: 'opcache.validate_timestamps',
                value: $opcacheValidateTimestamps,
                expectedValue: self::OPCACHE_VALIDATE_TIMESTAMPS
            );
        }
    }

    private function _checkStatus(): void
    {
        $status = \opcache_get_status();
        $statistics = $status['opcache_statistics'];

        if ($status['cache_full'] == true) {
            $this->issues[] = 'cache full';
        }

        if ((int) $statistics['opcache_hit_rate'] < 95) {
            $this->issues[] = 'opcache hit rate ' . $statistics['opcache_hit_rate'] . ' (expected > 95)';
        }
    }

    private function _checkPreloadingConfig(): void
    {
        $opcachePreload = \ini_get('opcache.preload');
        //$opcachePreloadUser = \ini_get('opcache.preload_user');

        $expectedOpcachePreload = self::OPCACHE_PRELOAD ? \preg_replace('#releases/(\d+)-(\d+)/#', 'current-release/', $this->projectDir) . self::OPCACHE_PRELOAD : "";

        if ($opcachePreload != $expectedOpcachePreload) {
            $this->_addUnexpectedConfig(
                config: 'opcache.preload',
                value: $opcachePreload,
                expectedValue: $expectedOpcachePreload,
            );
        }
    }

    private function _addUnexpectedConfig(string $config, string $value, string $expectedValue): void
    {
        $this->issues[] = "{$config} {$value} (expected {$expectedValue})";
    }
}
