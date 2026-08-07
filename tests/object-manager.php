<?php

// Hands phpstan/phpstan-doctrine the entity manager, which is how it learns
// the mapping: which properties the ORM fills in, what a repository returns,
// which fields a DQL query may name.
//
// The test environment on purpose — it runs on SQLite (see .env.test), so the
// container boots without a database server being up. Nothing here connects;
// only the metadata is read.

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

// Same env/debug pair the test suite boots with, so both share one compiled
// container instead of building a second variant of it.
$kernel = new Kernel('test', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
