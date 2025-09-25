<?php

namespace Pack\Status\Model;

/**
 * Model class for opcache data
 * for opcache status page
 * based on https://github.com/rlerdorf/opcache-status/blob/master/opcache.php
 */
class OpCacheStatus
{
    public const THOUSAND_SEPARATOR = true;

    private $configuration;
    private $status;
    private $d3Scripts = [];

    public function __construct()
    {
        $this->configuration = opcache_get_configuration();
        $this->status = opcache_get_status();
    }

    public function getPageTitle(): string
    {
        return 'PHP ' . phpversion() . " with OpCache {$this->configuration['version']['version']}";
    }

    public function getStatusDataRows(): string
    {
        $rows = [];
        
        foreach ($this->status as $key => $value) {

            if ($key === 'scripts') {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    if ($v === false) {
                        $value = 'false';
                    }
                    if ($v === true) {
                        $value = 'true';
                    }
                    if ($k === 'used_memory' || $k === 'free_memory' || $k === 'wasted_memory') {
                        $v = $this->_sizeForHumans(
                            $v
                        );
                    }
                    if ($k === 'current_wasted_percentage' || $k === 'opcache_hit_rate') {
                        $v = number_format(
                            $v,
                            2
                        ) . '%';
                    }
                    if ($k === 'blacklist_miss_ratio') {
                        $v = number_format($v, 2) . '%';
                    }
                    if ($k === 'start_time' || $k === 'last_restart_time') {
                        $v = ($v ? date(DATE_RFC822, $v) : 'never');
                    }
                    if (self::THOUSAND_SEPARATOR === true && is_int($v)) {
                        $v = number_format($v);
                    }
                    if (is_array($v)) {
                        $v = "<array>";
                    }

                    $rows[] = "<tr><th>$k</th><td>$v</td></tr>\n";
                }

                continue;
            }

            if ($value === false) {
                $value = 'false';
            }

            if ($value === true) {
                $value = 'true';
            }

            $rows[] = "<tr><th>$key</th><td>$value</td></tr>\n";
        }

        return implode("\n", $rows);
    }

    public function getConfigDataRows(): string
    {
        $rows = [];

        foreach ($this->configuration['directives'] as $key => $value) {
            if ($value === false) {
                $value = 'false';
            }
            if ($value === true) {
                $value = 'true';
            }
            if ($key == 'opcache.memory_consumption') {
                $value = $this->_sizeForHumans($value);
            }
            $rows[] = "<tr><th>$key</th><td>$value</td></tr>\n";
        }

        return implode("\n", $rows);
    }

    public function getScriptStatusRows(): string
    {
        foreach ($this->status['scripts'] as $key => $data) {
            $dirs[dirname($key)][basename($key)] = $data;
            $this->_arrayPset($this->d3Scripts, $key, array(
                'name' => basename($key),
                'size' => $data['memory_consumption'],
            ));
        }

        asort($dirs);

        $basename = '';
        while (true) {
            if (count($this->d3Scripts) != 1) break;
            $basename .= DIRECTORY_SEPARATOR . key($this->d3Scripts);
            $this->d3Scripts = reset($this->d3Scripts);
        }

        $this->d3Scripts = $this->_processPartition($this->d3Scripts, $basename);
        $id = 1;

        $rows = [];
        foreach ($dirs as $dir => $files) {
            $count = count($files);
            $file_plural = $count > 1 ? 's' : null;
            $m = 0;
            foreach ($files as $file => $data) {
                $m += $data["memory_consumption"];
            }
            $m = $this->_sizeForHumans($m);

            if ($count > 1) {
                $rows[] = '<tr>';
                $rows[] = "<th class=\"clickable\" id=\"head-{$id}\" colspan=\"3\" onclick=\"toggleVisible('#head-{$id}', '#row-{$id}')\">{$dir} ({$count} file{$file_plural}, {$m})</th>";
                $rows[] = '</tr>';
            }

            foreach ($files as $file => $data) {
                $rows[] = "<tr id=\"row-{$id}\">";
                $rows[] = "<td>" . $this->_formatValue($data["hits"]) . "</td>";
                $rows[] = "<td>" . $this->_sizeForHumans($data["memory_consumption"]) . "</td>";
                $rows[] = $count > 1 ? "<td>{$file}</td>" : "<td>{$dir}/{$file}</td>";
                $rows[] = '</tr>';
            }

            ++$id;
        }

        return implode("\n", $rows);
    }

    public function getScriptStatusCount(): int
    {
        return count($this->status["scripts"]);
    }

    public function isFull(): bool
    {
        return (bool) $this->status['cache_full'];
    }

    public function getPercentFull(): int
    {
        return (int) (100 * $this->status['memory_usage']['used_memory'] / $this->configuration['directives']['opcache.memory_consumption']);
    }

    public function getMemoryLimit(): string
    {
        return $this->_sizeForHumans($this->configuration['directives']['opcache.memory_consumption']);
    }

    public function getMemoryStats(): array
    {
        return [
            'used' => $this->status['memory_usage']['used_memory'],
            'free' => $this->status['memory_usage']['free_memory'],
            'wasted' => $this->status['memory_usage']['wasted_memory'],
        ];
    }

    public function getKeysStats(): array
    {
        return [
            'cached' => $this->status['opcache_statistics']['num_cached_keys'],
            'remaining' => $this->status['opcache_statistics']['max_cached_keys'] - $this->status['opcache_statistics']['num_cached_keys'],
        ];
    }

    public function getHitsStats(): array
    {
        return [
            'misses' => $this->status['opcache_statistics']['misses'],
            'hits' => $this->status['opcache_statistics']['hits'],
        ];
    }

    public function getRestartStats(): array
    {
        return [
            'oom' => $this->status['opcache_statistics']['oom_restarts'],
            'manual' => $this->status['opcache_statistics']['manual_restarts'],
            'hash' => $this->status['opcache_statistics']['hash_restarts'],
        ];
    }

    public function getHumanUsedMemory(): string
    {
        return $this->_sizeForHumans($this->getUsedMemory());
    }

    public function getHumanFreeMemory(): string
    {
        return $this->_sizeForHumans($this->getFreeMemory());
    }

    public function getHumanWastedMemory(): string
    {
        return $this->_sizeForHumans($this->getWastedMemory());
    }

    public function getUsedMemory(): int
    {
        return $this->status['memory_usage']['used_memory'];
    }

    public function getFreeMemory(): int
    {
        return $this->status['memory_usage']['free_memory'];
    }

    public function getWastedMemory(): int
    {
        return $this->status['memory_usage']['wasted_memory'];
    }

    public function getWastedMemoryPercentage(): string
    {
        return number_format($this->status['memory_usage']['current_wasted_percentage'], 2);
    }

    public function getD3Scripts()
    {
        return $this->d3Scripts;
    }

    private function _processPartition($value, $name = null): array
    {
        if (array_key_exists('size', $value)) {
            return $value;
        }

        $array = array('name' => $name, 'children' => array());

        foreach ($value as $k => $v) {
            $array['children'][] = $this->_processPartition($v, $k);
        }

        return $array;
    }

    private function _formatValue($value): mixed
    {
        if (self::THOUSAND_SEPARATOR === true) {
            return number_format($value);
        } else {
            return $value;
        }
    }

    private function _sizeForHumans(int $bytes): string
    {
        if ($bytes > 1048576) {
            return sprintf('%d M', $bytes / 1048576);
        } else {
            if ($bytes > 1024) {
                return sprintf('%d K', $bytes / 1024);
            } else {
                return sprintf('%d bytes', $bytes);
            }
        }
    }

    // Borrowed from Laravel
    private function _arrayPset(&$array, $key, $value): array
    {
        if (is_null($key)) return $array = $value;
        $keys = explode(DIRECTORY_SEPARATOR, ltrim($key, DIRECTORY_SEPARATOR));
        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            $array = &$array[$key];
        }
        $array[array_shift($keys)] = $value;

        return $array;
    }
}