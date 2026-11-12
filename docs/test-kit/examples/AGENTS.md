# AGENTS.md

TCU: website and admin of a university tennis club (interfac matches, practices, players' availabilities).
Symfony 8.1, PHP 8.5, PostgreSQL. UI texts are in French.

## Stack and layout

- Doctrine ORM + migrations, Twig + AssetMapper + Stimulus/Symfony UX (plain CSS, no build step),
  SecurityBundle, Form, Validator, Messenger (Doctrine transport), Lock, Mailer (Postmark), Sentry.
- One codebase, two kernels picked by `HTTP_HOST` (`src/Kernel.php`): the public site, and the admin
  (EasyAdmin, `admin/`) whose config is loaded on top only for `HOST_ADMIN`. On the CLI, reach the
  admin container with `HTTP_HOST=<HOST_ADMIN> bin/console ...` (e.g. `debug:router`, `lint:container`).
  Each host has its own cache dir: `var/cache/{site,admin}/<env>`.
- `packs/` holds self-contained modules (Security, Status, Chart) with their own controllers and templates.
- Environments: dev, prod and test only (`Kernel::getAllowedEnvs()`). No staging config.

## Conventions

Follow https://symfony.com/doc/current/best_practices.html. The points that matter most:

- PHP attributes for framework metadata: `#[Route]`, `#[IsGranted]`, `#[Assert\...]`, `#[AsCommand]`,
  `#[AsEventListener]`, `#[AsMessageHandler]`... No YAML or XML routing.
- Autowiring and autoconfiguration. Where a type-hint can't express it, `#[Autowire]` or `#[Target]` in
  the class. A YAML service definition is the last resort.
- Thin controllers extending `AbstractController`; logic goes in services.
- Use the framework for what it already does: Form, Validator, Serializer, Messenger, voters,
  `path()`/`url()` in Twig. Before hand-writing infrastructure (locks, queues, caches, HTTP clients),
  check for a Symfony component. `LockFactory` for mutual exclusion, never a hand-built flag.
- Request data: `#[MapRequestPayload]` / `#[MapQueryString]`, not `json_decode()`.
- Constructor property promotion; `readonly` for DTOs and value objects.
- New capability: `composer require <package>` and let the Flex recipe register it. Never hand-edit
  `config/bundles.php` or hand-write a bundle's base config.
- `maker-bundle`: pass every argument and `--no-interaction`; if a maker still prompts, write the code.

## Database

- Schema changes only through migrations (`make:migration`, `doctrine:migrations:migrate`), never
  `doctrine:schema:update` or hand-written SQL.
- Never edit a migration that has already run (check `doctrine:migrations:status`): add a follow-up one.

## Configuration and secrets

- `.env` is committed and holds defaults only. Real values go in `.env.local` / `.env.test.local`
  (git-ignored) or the secrets vault.
- The dev `MAILER_DSN` can be a real Postmark account: anything that may send emails outside the test
  environment (a local server, a console command) should run with `MAILER_DSN=null://null`.
- `symfony/requirements-checker` and other tooling may be used by deploy scripts that live on the
  server, not in this repo: don't remove "unused" tooling without asking.

## Checks before committing

```bash
vendor/bin/php-cs-fixer fix                    # house style: @Symfony with `$a . $b` and `$x === null`
vendor/bin/phpstan analyse                     # no new errors; don't add to phpstan-baseline.neon
bin/console lint:container                     # and again with HTTP_HOST=<HOST_ADMIN>
bin/console lint:twig templates admin/templates packs
php bin/phpunit                                # after changes to controllers, templates, queries or forms
```

## Tests

Deliberately small. Not run on deploy.

- `tests/Smoke/PagesTest.php` requests every page without route parameters, on both hosts and as
  anonymous / player / admin, and fails only on a 5xx. New pages are picked up from the router.
- The test database `tcu_test` (migrations + `app:fixtures:load`) is built by `tests/bootstrap.php` only
  when a migration, the fixtures command or the day changes; each test is rolled back by
  dama/doctrine-test-bundle. `TEST_DB_REBUILD=1 php bin/phpunit` forces a rebuild.
- Add a unit test only for a tricky service, typically after a bug in it. Never assert on markup,
  wording or dates that age.

## Discover, don't guess

Framework APIs change between versions. Look things up in the project rather than relying on memory:
`bin/console about`, `debug:router`, `debug:container`, `debug:autowiring <name>`,
`config:dump-reference <bundle>`, the source and docblocks under `vendor/`, and
https://symfony.com/doc/current/. When something fails, read `var/log/<env>.log` and the profiler first.
