<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

buildTestDatabaseIfOutdated(dirname(__DIR__));

/**
 * The test database (migrations + app:fixtures:load) is built once and reused: each test runs in a
 * transaction rolled back by dama/doctrine-test-bundle, so it never drifts.
 *
 * It is rebuilt when its inputs change: a migration, the fixtures command, or the day (fixtures are
 * built relative to "today"). Force a rebuild with TEST_DB_REBUILD=1 php bin/phpunit.
 */
function buildTestDatabaseIfOutdated(string $projectDir): void
{
    $inputs = [...glob($projectDir . '/migrations/*.php'), $projectDir . '/src/Command/LoadFixturesCommand.php'];
    $checksum = hash('xxh128', date('Y-m-d') . implode('', array_map(
        static fn (string $file): string => $file . hash_file('xxh128', $file),
        $inputs,
    )));

    $checksumFile = $projectDir . '/var/test-database.checksum';
    if (!getenv('TEST_DB_REBUILD') && @file_get_contents($checksumFile) === $checksum) {
        return;
    }

    echo "Building the test database (migrations + fixtures)...\n";
    $console = escapeshellarg(\PHP_BINARY) . ' ' . escapeshellarg($projectDir . '/bin/console') . ' --env=test --no-interaction';
    foreach ([
        'doctrine:database:drop --force --if-exists',
        'doctrine:database:create',
        'doctrine:migrations:migrate --quiet',
        'app:fixtures:load --force --quiet',
    ] as $command) {
        passthru("$console $command > /dev/null", $exitCode);
        if ($exitCode !== 0) {
            @unlink($checksumFile);
            throw new RuntimeException("Building the test database failed on: bin/console $command --env=test");
        }
    }

    file_put_contents($checksumFile, $checksum);
}
