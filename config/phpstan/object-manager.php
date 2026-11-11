<?php

// Lets phpstan-doctrine read the real Doctrine mapping (DQL checks, repository return types).
// https://github.com/phpstan/phpstan-doctrine#configuration

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
