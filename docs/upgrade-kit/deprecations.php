<?php

/**
 * Print the compile-time deprecations of every cached container under var/cache.
 *
 * Usage (from the project root, after warming the cache, e.g. bin/console lint:container):
 *   php docs/upgrade-kit/deprecations.php            # summary + messages
 *   php docs/upgrade-kit/deprecations.php --count    # counts only
 *
 * Why: deprecations triggered while the container is compiled (config options, autowiring,
 * bundle extensions) are NOT written to var/log/dev.log. Symfony stores them, serialized, in
 * <cache dir>/<Kernel><Env>DebugContainerDeprecations.log next to the compiled container.
 * Runtime deprecations (triggered while handling requests) are in var/log/<env>.log instead:
 *   grep "User Deprecated" var/log/dev.log
 * Some only appear on a COLD cache (e.g. Doctrine proxy generation): rm -rf var/cache first.
 */
$countOnly = in_array('--count', $argv, true);
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(getcwd() . '/var/cache', FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (str_ends_with($file->getFilename(), 'Deprecations.log')) {
        $files[] = $file->getPathname();
    }
}
if (!$files) {
    exit("No *Deprecations.log under var/cache — warm the cache first (bin/console lint:container).\n");
}
sort($files);

foreach ($files as $path) {
    $entries = @unserialize((string) file_get_contents($path)) ?: [];
    printf("%s: %d deprecation(s)\n", substr($path, strlen(getcwd()) + 1), count($entries));
    if ($countOnly) {
        continue;
    }
    foreach ($entries as $entry) {
        printf("  [%dx] %s\n", $entry['count'] ?? 1, $entry['message']);
    }
}
