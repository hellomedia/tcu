# Test kit: small, non-intrusive checks for a Symfony project

Distilled from setting this up in **TCU** on 2026-11-12 (Symfony 8.1, PHP 8.5, PostgreSQL, two
host-dependent kernels). Written to be copied into a sibling Symfony project and followed there by
Claude, together with the user.

| File | What it is |
|---|---|
| `README.md` | This guide |
| `files/tests/bootstrap.php` | PHPUnit bootstrap that builds the test database once, rebuilt only when its inputs change |
| `files/tests/Smoke/PagesTest.php` | The smoke test: every GET page, per host and per user, fails only on 5xx |
| `files/php-cs-fixer.dist.php` | php-cs-fixer config: `@Symfony` + the user's house style |
| `files/env.test` | Lines to merge into `.env.test` |
| `examples/AGENTS.md`, `examples/CLAUDE.md` | TCU's versions, to show the shape. Don't copy them as is |

Lines marked `ADAPT` in the files are project-specific: read the project before filling them in.

---

## 0. The user's test policy (agreed, don't relitigate)

The user dislikes big test suites. On past projects they made deploys "atrociously slow" and failed
on false positives "99% of the time". Sentry reports production errors. So:

- **Static checks**: php-cs-fixer, phpstan, `lint:*`. Seconds, no false positives.
- **One smoke test**: every page answers without a 5xx, as each kind of user. No assertions on
  markup, wording, redirects or dates.
- **Unit tests only for tricky services**, case by case, typically after a bug.
- **Nothing in the deploy script, no commit hook.** Claude runs the checks before committing.
  The user never has to.
- **The test database is never rebuilt on every run.** On some projects fixtures take ~3 min
  (image processing). Make heavy fixtures skip that work in the test environment (tiny placeholder
  image) if the rebuild itself is too slow.

Why it helps Claude: without it, checking a page means a built-in PHP server, a scratch database,
curl with CSRF tokens and cookies by hand, and a risk of sending real emails. The smoke test replaces
all that with `php bin/phpunit` (TCU: ~6 s), so the check actually gets run after each change.

## 1. Before you start

- Read `composer.json`, `symfony.lock`, `src/Kernel.php`, `config/packages/security.yaml` (firewalls,
  hosts), `config/packages/doctrine.yaml` (`when@test` → `dbname_suffix: '_test…'`), the fixtures
  (custom command or `doctrine/doctrine-fixtures-bundle`), and `.env` (which values are empty
  defaults: in test, `.env.local` is **not** loaded).
- Check the project already passes `vendor/bin/phpstan analyse` (or install phpstan first, with a
  baseline). Save the result: you will compare against it.
- Packagist may be blocked from Claude's container. Then the user runs Composer:
  ```
  ! composer require --dev symfony/test-pack dama/doctrine-test-bundle friendsofphp/php-cs-fixer
  ```
  `symfony/test-pack` is unpacked into phpunit, browser-kit and css-selector. dama/doctrine-test-bundle
  wraps each test in a rolled-back transaction, so the database is built once and never drifts. It
  is not in the test pack because Doctrine is optional in Symfony and the bundle is third-party.
- Commit the stock recipes as they are, as one commit, before changing anything.

## 2. php-cs-fixer

1. Dry run and count which rules would fire, to spot house-style conflicts:
   ```bash
   vendor/bin/php-cs-fixer fix --dry-run -v --format=json > /tmp/csf.json
   php -r '$d=json_decode(file_get_contents("/tmp/csf.json"),true);$r=[];foreach($d["files"] as $f)foreach($f["appliedFixers"]??[] as $x)$r[$x]=($r[$x]??0)+1;arsort($r);print_r($r);'
   ```
2. **Ask the user** about rules that go against the code's current habits. Count both styles with
   grep to show them the numbers. In TCU the user chose to keep:
   - `concat_space: one` (`$a . '/x'`, 74 lines vs 8)
   - `yoda_style: false` (`$x === null`, 192 vs 12)
   - `phpdoc_summary: false` (no trailing dot forced on docblock summaries; the user asked for this
     after seeing the diff)
3. Commit the config, then run `fix` and **check that nothing but formatting changed** before
   committing it as a separate, formatting-only commit:
   - `php -l` on every changed file; phpstan must give the same result as before; `lint:container`
     (per host); `lint:twig`; `doctrine:schema:validate --skip-sync`; a smoke pass of the pages.
   - Where phpstan changes, fix the source **before** the reformat, in its own commit, then redo the
     reformat (`git checkout -- .`, fix, commit, `fix` again). TCU had two:
     - a malformed docblock (`@var $precision string ...` where `@param` was meant), already in the
       baseline, which `phpdoc_align` re-aligned into a mess → proper `@param`;
     - `no_useless_else` exposed an always-true constant condition, so phpstan reported dead code
       → removed the dead branch.
   - Update the baseline by hand for entries that disappear (`ignore.unmatched`, counts). Don't
     regenerate it blindly.
   - Run `fix` a second time: it must report 0 files. Remove imports it left unused, if any.

## 3. Test environment

- `src/Kernel.php`: Symfony 8 refuses environments not listed in `getAllowedEnvs()`. Add `'test'`.
- `.env.test`: merge `files/env.test`. A null mailer whatever `.env` says (TCU's `.env` ends with an
  empty Postmark `MAILER_DSN`), and `.test` hosts if the app reads host env vars.
- `.env.test.local` (git-ignored): `DATABASE_URL`, usually the same as `.env.local`'s. Doctrine adds
  `_test` to the database name. The database user needs CREATEDB. The user has to create this file
  on every other machine.
- Check: `APP_ENV=test bin/console lint:container` (and per host), and
  `APP_ENV=test bin/console debug:dotenv` to see the values actually used.
- `tests/bootstrap.php`: copy `files/tests/bootstrap.php`, set its inputs and fixtures command.
  Drop `date('Y-m-d')` from the checksum if fixtures don't depend on "today". TCU: migrations 0.5 s +
  fixtures 1.8 s to build. `TEST_DB_REBUILD=1 php bin/phpunit` forces a rebuild.

## 4. Smoke test

Copy `files/tests/Smoke/PagesTest.php` and fill in the `ADAPT` parts:

- **Visitors**: one per (host, user). Users come from the fixtures; firewalls from `security.yaml`.
- **Host-dependent kernels**: `$_SERVER['HTTP_HOST']` must be set *before* `createClient()` (the
  kernel reads it while building the container). Each visitor is its own test, so the kernel reboots
  between hosts. Two kernel variants in one PHP process worked fine in TCU.
- **Admin on its own host**: its kernel also loads the public routes. `hostRoutesOnly: true` keeps
  only the routes whose host requirement is that host (compared after parameter resolution).

The first run is a triage. In TCU it reported, sorted into:

| Kind | Examples | Do |
|---|---|---|
| Not pages | `/login-check` (firewall-intercepted, controller throws), EasyAdmin `…/autocomplete` (needs query params) | exclude |
| Production health checks | `/_check/*`: OPcache, php.ini values, a deliberate Sentry exception, a messenger-worker check **that waits 10 s** | exclude (the 10 s waits were 20 of 27 s) |
| CLI-only artefacts | `/status/opcache` (OPcache off on the CLI) | exclude |
| **Real bugs** | `/check-your-email` always 500 since the first commit (an unconfigured `bool $deliverabilityIssues` controller argument); the bot version of the page rendering a template without its `@namespace`; a `str_contains(null)` deprecation on requests without an `Accept` header | report to the user, fix in separate commits (ask when the fix involves a product choice) |

Keep `failOnDeprecation` from the phpunit recipe: the one it raised was real. If a run is slow but
CPU time is low (`time`: real ≫ user), something is waiting (a health check, an HTTP call): find it.

Then add `tests/` to phpstan's `paths` (the recipe's `method_exists(Dotenv::class, 'bootEnv')` in the
bootstrap is then reported as always true: drop the check, as in `files/tests/bootstrap.php`).

Watch out when writing docblocks: a path like `*/autocomplete` inside `/** */` closes the comment.

## 5. AGENTS.md / CLAUDE.md

The framework-bundle 8.1 recipe adds a generic `AGENTS.md`. Claude Code loads it on its own (it
shows up as project instructions), so don't `@import` it from CLAUDE.md: it would load twice.
Rewrite it for the project (TCU's is in `examples/`):

- **Keep** what steers code: attributes, no YAML routing; autowiring, then `#[Autowire]`/`#[Target]`,
  YAML services last; thin controllers; Symfony components before hand-written infrastructure;
  `LockFactory`; `#[MapRequestPayload]`; promoted `readonly` DTOs; Flex recipes, never hand-editing
  `bundles.php`; makers with `--no-interaction`; migrations only; never edit an executed migration;
  secrets in `.env.local`/vault; "discover, don't guess" (`debug:*`, `config:dump-reference`, `vendor/`).
- **Cut** generic hedges the project has answered ("don't assume Doctrine", "ask which persistence /
  UI / auth"), `symfony serve`, "install test-pack, every feature needs a WebTestCase", a code-style
  section about a tool that isn't installed.
- **Add** the stack, the layout quirks (host-dependent kernels, extra code dirs, allowed
  environments), the pre-commit checks, the test policy above, and tooling that must stay because
  server-side deploy scripts use it.
- `CLAUDE.md` holds only what's specific to Claude's container (Packagist blocked, never experiment
  on the dev database).
- AGENTS.md belongs to the recipe: a later `recipes:update symfony/framework-bundle` will conflict on
  it. Keep the project's version.

## 6. Commits (TCU, for reference)

1. Install the test pack, DAMA doctrine test bundle and php-cs-fixer (stock recipes)
2. Keep the house style in php-cs-fixer
3. Fix two spots php-cs-fixer would have tripped on
4. Apply php-cs-fixer to the whole codebase
5. Bug fixes found by the smoke test (one commit each)
6. Set up the test environment and its database
7. Add a smoke test: every page answers without a server error
8. Rewrite AGENTS.md for this project, add CLAUDE.md

## 7. Checks before committing (to put in AGENTS.md)

```bash
vendor/bin/php-cs-fixer fix
vendor/bin/phpstan analyse                     # no new errors; don't add to the baseline
bin/console lint:container                     # per host for host-dependent kernels
bin/console lint:twig templates                # plus any other template dirs
php bin/phpunit                                # after changes to controllers, templates, queries or forms
```
