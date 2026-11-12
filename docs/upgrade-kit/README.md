# Upgrade kit: PHP 8.5 + Symfony 7.4 → 8

A field guide distilled from upgrading **Kot Market** on 2026-11-10 (PHP 8.3/8.4 → 8.5, Symfony 7.4 → 8.1.11, ~50 commits). Written to be copied into a sibling Symfony project and followed there — by a person or by Claude running in that project's container.

Contents of this folder:

| File | What it is |
|---|---|
| `README.md` | This guide |
| `smoke.php` | Request pages through the app's own Kernel from the CLI, anonymous or logged in — no browser, web server or browser-kit |
| `deprecations.php` | Prints the compile-time deprecations of every cached container (they are **not** in `dev.log`) |

Kot Market specifics appear as *Example:* lines. Adapt them; don't copy them blindly.

---

## 0. Rules of the game

- **Staged, one commit per step, each step deployable.** Never mix a bundle major with the Symfony jump. When something breaks you want exactly one suspect.
- **Move every bundle major while still on Symfony 7.4.** All the majors needed for Symfony 8 also support 7.4. Done this way, the final `symfony/*` jump touches Symfony only and is small.
- **Reach zero deprecations before each major.** That is Symfony's own upgrade rule, and it keeps the deprecation log useful: each step then shows only its own new ones.
- **Remove deprecated options; don't keep them "for visibility".** A deprecated option fires on every container compile and becomes a hard error (unknown key) in the next major. Document the behaviour in a YAML comment instead.
- **`composer update <pkg> -W`.** `-W` (`--with-all-dependencies`) lets the package's dependencies move too, including ones you require directly. Without it most major bumps fail for no good reason.
- **Commit recipes separately**, one commit per recipe, after the package update that made them outdated (Flex refuses `recipes:update` on a dirty tree).
- **Write a "why" + doc link in every config commit.**
- **Verify on a cold cache** (`rm -rf var/cache/*`) at least once per step: some deprecations (Doctrine proxy generation) only fire then.

## 1. Before you start

1. **Know the production PHP version.** If `vendor/` is built locally (a deploy script running `composer install` in the dev container), prod must satisfy `vendor/composer/platform_check.php`. Symfony 8 needs PHP ≥ 8.4.
2. Branch from an up-to-date `main`: `git switch -c upgrade/symfony-8`.
3. Copy this folder into the project (`docs/upgrade-kit/`).
4. Record a baseline: run the verification battery (§9) and save the results, so "new error" vs "was already there" is answerable later.
5. Check what runs where (§10): can the Claude container reach packagist? Does it have the same PHP version as the app container?

## 2. Phase 1 — PHP 8.5

1. Bump every Dockerfile that installs PHP (app container **and** the Claude sidecar): packages `php8.4-*` → `php8.5-*`, ini paths `/etc/php/8.4/` → `/etc/php/8.5/`, `CMD ["php-fpm8.5"]`.
2. Check `docker-compose.yaml` **mounts** into `/etc/php/X.Y/…` (php-fpm pool, xdebug ini). *Example:* they still pointed at 8.3 two PHP versions later — the files silently stopped applying.
3. `composer.json`: `"php": "^8.5"`.
4. **If prod is still on an older PHP for a while:** keep developing on 8.5 but resolve dependencies for prod with
   ```json
   "require": { "php": "^8.3" },
   "config": { "platform": { "php": "8.3" } }
   ```
   Composer then only locks versions that work on 8.3 and `platform_check.php` targets 8.3. Remove the pin (and restore `^8.5`) once prod is upgraded. Nothing stops 8.4/8.5-only syntax from passing locally meanwhile.
5. Rebuild, then `composer update`. **New in Composer 2.9+:** versions affected by a security advisory are refused (`… were not loaded, because they are affected by security advisories`). *Example:* `firebase/php-jwt ^6.11` was blocked (CVE-2025-45769); the fix was `^7.0`, whose new key-size check required rotating a 22-byte HS256 secret to ≥ 32 bytes. Fix the dependency rather than adding `config.policy.advisories.ignore-id`, unless you can't yet.
6. PHP 8.5 itself deprecated nothing in Kot Market's code. Quick scan: old cast names `(boolean)/(integer)/(double)/(binary)`, `__sleep`/`__wakeup`, backtick shell exec.

## 3. Phase 2 — zero deprecations on Symfony 7.4

Run `php docs/upgrade-kit/deprecations.php` after `bin/console lint:container` (for every kernel variant you have — see *multi-host kernels* in §10) and `grep "User Deprecated" var/log/dev.log` after smoking pages. Typical fixes:

| Deprecation | Fix |
|---|---|
| `Implicitly marking parameter $x as nullable` (PHP 8.4) | `?Type $x = null`. Find **all** of them, not just loaded classes: `find src … -name '*.php' -print0 \| xargs -0 -n1 php -d error_reporting=E_ALL -l \| grep Deprecated` |
| `Voter::voteOnAttribute()` will require `Vote\|null $vote` | add `, ?Vote $vote = null` (BC-safe on 7.4) |
| `UserCheckerInterface::checkPostAuth()` will require `?TokenInterface $token` | add `, ?TokenInterface $token = null` |
| `AbstractType::buildForm()/configureOptions()` might add `void` | add `: void` |
| `twig.exception_controller: null` | delete the option |
| Foundry: `enable_auto_refresh_with_lazy_objects` not set | set `true` (Foundry 3 default) |
| `Request::get()` | `$request->attributes->get()` / `query` / `request` explicitly |

## 4. Phase 3 — bundle majors, still on 7.4

**Order matters.** Each line was discovered by a failed `composer update`:

| # | Package | To | Why first / gotchas |
|---|---|---|---|
| a | `symfony/monolog-bundle` | `^4.1` | Removed `type: sentry` handler → use `type: service` with `id: Sentry\Monolog\Handler` (define the service with `$hub`, `$level`, `$fillExtraContext`). Removed `excluded_404s`, `elasticsearch`, `mongo`. |
| b | `symfony/mercure-bundle` | `^0.7` | Protocol 1.0 became the default in 0.6 → pin `protocol_version: '0.x'` to keep tokens/cookie unchanged; switch protocol + hub together later. Defer its recipe (§6). |
| c | `easycorp/easyadmin-bundle` | `^5.10` | **Before UX 3:** EasyAdmin 4 pins `symfony/ux-twig-component ^2.21`. See §4.1. |
| d | `symfony/stimulus-bundle` + all `symfony/ux-*` | `^3.7` | Needs EasyAdmin 5. Read UX's `UPGRADE-3.0.md`; usually no code change. Check JS peers (Turbo `^7.1 \|\| ^8`, Chart.js `^3.4 \|\| ^4`) and that `vendor/` paths in `importmap.php` still exist. `controllers.json` may gain autoimports. |
| e | `symfonycasts/dynamic-forms` | `^0.2` | Symfony 8 support only. |
| f | Doctrine native lazy objects | — | Cold-cache deprecations `Doctrine\ORM\Proxy\Autoloader`, `LazyGhostTrait`, `ProxyHelper::generateLazyGhost()` → `doctrine.orm.enable_native_lazy_objects: true` (var-exporter 8 removes LazyGhostTrait). ORM 3.6: `#[ORM\OrderBy(['x' => 'ASC'])]` → `\SortDirection::Ascending` (PHP 8.6 enum, polyfilled by `symfony/polyfill-php86`). |
| g | `doctrine/doctrine-bundle` | `^3.3` | 2.x caps Symfony at `^7.0`. Drop `dbal.use_savepoints`, `orm.report_fields_where_declared`, `enable_lazy_ghost_objects`, `auto_generate_proxy_classes`, `proxy_dir`; in 3.1 also `enable_native_lazy_objects` and `controller_resolver.auto_mapping` (always on/off now). Its recipe: `naming_strategy.underscore` (= number-aware in ORM 3; `schema:validate` proves columns unchanged), drop mapping `alias:` (no-op). |
| h | `doctrine/doctrine-migrations-bundle` | `^4.0` | 3.x caps `http-kernel` at `^7.0`; needs DoctrineBundle 3. Container-aware migrations are gone. |

Every other bundle Kot Market uses already allowed Symfony 8 (KnpMenu, KnpTime, LiipImagine, Pagerfanta, Sentry 5, reset-password, verify-email, Foundry 2, minify, fixtures). Check yours: `jq` over `composer.lock` for each package's `symfony/http-kernel` / `framework-bundle` constraint.

### 4.1 EasyAdmin 4 → 5

Read `UPGRADE.md` **and** `UPGRADE-AI.md` (a version written for AI agents) in the bundle repo.

- `MenuItem::linkToCrud('Label', 'icon', Entity::class)` → `MenuItem::linkTo(EntityCrudController::class, 'Label', 'icon')`.
- **Remove any plain `#[Route]` on the dashboard's `index()`** kept from EA4 days: in 5.x it matches first, without an admin context → `Impossible to access an attribute ("i18n") on a null variable in "@EasyAdmin/layout.html.twig"`.
- **Custom Symfony pages rendered with EasyAdmin layouts** (status pages, lists…) also crash with that error: replace their `#[Route]` with `#[AdminRoute('/path', name: 'x')]` (class-level prefix allowed). Route names become `<dashboard>_<name>`; link with `MenuItem::linkTo(Controller::class, …)->setAction('method')`. They are then generated only for the dashboard (host) — check you aren't relying on a public route. *Example:* a `config/routes.yaml` import exposed `/status/phpinfo` on the public host; moving to `#[AdminRoute]` closed it.
- Login page: render `@EasyAdmin/page/login.html.twig` directly instead of keeping a copy of the EA4 template (`ea` global variable is gone → `ea()`).
- Adding an action that already exists throws (`The "detail" action already exists in the "index" page`) — e.g. the dashboard adds `DETAIL` globally and a CRUD adds it again.
- Overridden `bundles/EasyAdminBundle/exception.html.twig` gets `exception` (`statusCode`, `publicMessage`) and `layout_template_path`, not `status_code`/`status_text`. Test it with `SMOKE_DEBUG=0` (the EA error page only renders without debug).
- `setIcon(false)` → `setIcon(null)`; typed arguments are stricter.
- 5.9 redesign: neutral colours, Inter font, new buttons/badges. `Theme::new()` in `configureDashboard()` can bring back an older look.
- Smoke every CRUD: index, a detail, an edit — as admin (`smoke.php` with `SMOKE_USER` + `SMOKE_FIREWALL`).

### 4.2 Mercure protocol 1.0 + hub 1.x (optional, can wait)

mercure-bundle ≥ 0.6 defaults to protocol 1.0. Keep `protocol_version: '0.x'` during the Symfony upgrade; switch later, hub and app together:

- Hub image 1.x (pin a version). No `dev.Caddyfile` any more (drop a `command: … dev.Caddyfile` override); health check `http://127.0.0.1:2019/mercure/health/ready`; `MERCURE_TRUSTED_ISSUERS` = the app's JWT issuer; no `anonymous` unless you have public subscriptions.
- **Behind a reverse proxy** that rewrites the Host header, pin `resource_identifier <public hub URL>` (in `MERCURE_EXTRA_DIRECTIVES`): the bundle puts `public_url` in the token `aud`, the hub would derive another audience → every token rejected.
- Bundle: `jwt.claims` `iss`/`sub`/`client_id` (container won't compile without them), cookie becomes `__Secure-mercure_access_token` (prefix-less under `kernel.debug` unless you set `cookie_name`). Apply the deferred recipe now.
- Subscribe with `turbo_stream_from(topics, private: true)` (UX Turbo 3.1+). `turbo_stream_listen()` and the `mercure-turbo-stream` Stimulus controller only speak 0.x. One call = one EventSource: never on repeated elements; pass a topics list per chunk.
- **One subscriber cookie per hub**: if responses authorize only their own topics, the last one replaces the cookie and other open streams lose access on reconnect. Set the cookie with *all* the user's private topics in each request that renders a private source (`Authorization::setCookie`), and let `turbo_stream_from` merge into it (symfony/mercure 0.10+). Publish every update `private: true`.
- Verify the cookie by decoding it (`SMOKE_COOKIES=1` shows names; decode the JWT payload: `iss`, `aud`, grants). It is scoped to the hub (path `/.well-known/mercure`), so DevTools shows it on the Mercure request's Cookies tab, not among the page cookies. Logout should expire it (`clear_cookie_on_logout`, bundle 0.7).

## 5. Phase 4 — Symfony 7.4 → 8.1

1. All `"symfony/*": "7.4.*"` and `extra.symfony.require` → `"8.1.*"`, then `composer update "symfony/*" -W`.
2. **If Composer lists every `symfony/http-kernel` version as "affected by security advisories"**, it's misleading: the advisories are fixed in the latest patch releases; Composer just reports them for every rejected version. Read the problems for a *non-symfony* package capped at `^7` — that is the real blocker. Move it on 7.4 first (step h above was found this way), restore `7.4.*`, retry.
3. Fixes needed after the jump:

| Symptom | Cause | Fix |
|---|---|---|
| Routes of one controller silently missing (`Unable to generate a URL for the named route …`) | `use Symfony\Component\Routing\Annotation\Route;` — namespace removed in 8.0 | `Routing\Attribute\Route`. `grep -rn 'Routing\\Annotation' src` |
| `Passing an array of options to configure the "…NotBlank" constraint is no longer supported` | array options removed from constraints in 8.0 | named arguments: `new Length(min: 6, minMessage: '…')` |
| `TypeError: Image::__construct(): Argument #21 ($mimeTypesMessage) must be of type ?string, TranslatableMessage given` | the old array form let objects through | plain message string; move its translation to the `validators` domain |
| `Relying solely on the name of parameter "$fooLogger" … use the "#[Target]" attribute` (8.1) | named autowiring by parameter name | `#[Target('foo')]` (`bin/console debug:autowiring LoggerInterface` lists the names); verify with `debug:container <service>` that the same service is injected |
| `framework.profiler.collect_serializer_data` deprecated (8.1) | old web-profiler recipe | update the recipe |

4. Recipes for 8.1 (§6): `framework-bundle` adds `getAllowedEnvs()` to `src/Kernel.php` — **list every environment you use** (`staging`!), or that environment won't boot. It also adds `AGENTS.md` (good generic agent guidelines; reconcile with `CLAUDE.md`, which Claude Code loads — `CLAUDE.md` can import it with a line `@AGENTS.md`).
5. Symfony 8.1 is maintained until 01/2027; 8.2 is due end of November 2026 — a minor upgrade.

## 6. Recipes

- `composer recipes` lists outdated ones; the list can be long — don't `tail` it and miss entries.
- One at a time: `composer recipes:update vendor/package`, review, resolve, commit. Flex needs a clean tree before each.
- Conflicts are mostly *your customised config vs. the recipe's stock file*. Usual resolution: keep yours, take only the real change (new option, renamed key, schema/doc comment header).
- Watch for:
  - `*.updates-for-deleted-files.patch` at the project root (recipe changes for a file you deleted, e.g. `templates/base.html.twig`) — read, then delete; don't commit.
  - Files the recipe renamed but kept because you modified them (*Example:* `assets/bootstrap.js` → `stimulus_bootstrap.js`; a second entry point still imported the old file).
  - **Recipe lines that assume newer infrastructure.** *Example:* the mercure-bundle recipe added `protocol_version_compatibility 8` to the hub's Caddy config; the locally cached `dunglas/mercure:latest` was a 0.x hub ("compatibility mode only supports protocol version 7"), crashed — and took nginx down with it (`proxy_pass http://mercure` must resolve at startup). Pin hub images to a version.
  - Recipes that belong to a later step: *Example:* the mercure-bundle 0.6 recipe *is* the protocol-1.0 setup — abort it (`git reset --hard`) and run it with that step.
  - `.env` additions you don't need (e.g. `APP_SHARE_DIR`, which moves cache pools to a shared `var/share` — think twice with per-host cache dirs or deploy scripts that copy `var/` between releases; `DEFAULT_URI` when `default_uri` is already derived from another env var).

## 7. Phase 5 — tooling

- **phpstan 1.12 cannot read Symfony 8 classes** (reports `HttpFoundation\Request`, `Console\Command` as unknown classes). Upgrade to `^2.3`. It may come in through `rector/rector` 1.x, which pins phpstan 1 → bump rector to `^2.8` and require `phpstan/phpstan` directly.
- Make `phpstan.dist.neon` cover **all** code dirs (*Example:* `packs/`, `controlroom/src/`, `components/` had never been analysed) and remove paths that don't exist (`tests/` makes phpstan abort).
- Adopt with a baseline: `vendor/bin/phpstan analyse --generate-baseline=phpstan-baseline.neon`, include it from `phpstan.dist.neon`; from then on, 0 new errors. List the real issues it finds as follow-ups instead of fixing them inside the upgrade.
- `phpstan/phpstan-doctrine` + `phpstan/phpstan-symfony` remove the "undefined method on EntityRepository" false positives.

## 8. Symptom → cause → fix

| You see | It means | Do |
|---|---|---|
| `curl error 28 … repo.packagist.org` from the Claude container | egress firewall allowlists IPs resolved at start; packagist is a CDN with 10+ rotating IPs (TTL 5 s) | run Composer in the app container on the host; `recipes:update` works from the sidecar (GitHub) |
| Composer refuses `php ^8.x` in the sidecar | sidecar PHP older than the app's | align the sidecar Dockerfile |
| `Your Composer dependencies require a PHP version ">= 8.4.0". You are running 8.3` during deploy | vendor built locally on newer PHP | `config.platform.php` pin (§2.4) until prod is upgraded |
| `… were not loaded, because they are affected by security advisories` for one package | real advisory | upgrade that package |
| … for **every** version of `symfony/http-kernel` | a different package conflicts | find the non-symfony package capped at `^7` |
| `X requires symfony/ux-twig-component ^2.21` when bumping UX | EasyAdmin 4 | EasyAdmin 5 first |
| `ea()` null / `Impossible to access an attribute ("i18n")` | page rendered outside EasyAdmin's admin context | §4.1: remove plain `#[Route]` on dashboard; `#[AdminRoute]` on custom pages |
| `Controller "…" has required constructor arguments and does not exist in the container` on one host only | host-dependent kernel compared `HTTP_HOST` *with port* (`:8444`) to a bare hostname | strip the port like `Request::getHost()` does |
| Hub container `Exited (1)`, nginx `host not found in upstream` | hub crashed on a config directive (recipe) | §6 |
| Live updates never arrive although Mercure works | `turbo_stream_listen()` printed **inside** an element's content (it returns attributes) | `turbo_stream_from(topic)` (UX Turbo 3.1+) renders a `<turbo-mercure-stream-source>` element; `turbo_stream_listen` is deprecated and refuses Mercure protocol 1.0 |
| A page fails only in the Claude container: `Idle timeout … api.iconify.design` | icon not locked in `assets/icons`, fetched on demand | `bin/console ux:icons:lock` on the host; UX Icons 3 recipe enables `auto_lock` in dev |
| `ValueError: "T" is not a valid backing value for enum …` | data left over after removing an enum case | data fix / migration — not an upgrade issue |

## 9. Verification battery

From the project root (in the Claude sidecar if it has the app's PHP; else prefix `docker compose exec php`):

```bash
rm -rf var/cache/*                                   # cold cache, at least once per step
bin/console lint:container                           # once per kernel variant (host-dependent kernels: HTTP_HOST=… bin/console lint:container)
bin/console lint:yaml config --parse-tags
bin/console lint:twig templates
bin/console lint:translations
bin/console doctrine:schema:validate                 # proves mapping/naming changes didn't move columns
bin/console doctrine:migrations:status
vendor/bin/phpstan analyse                           # after §7: must report no new errors
php docs/upgrade-kit/deprecations.php                # compile-time deprecations (not in dev.log)
grep "User Deprecated" var/log/dev.log               # runtime deprecations, after smoking pages
```

Smoke pages (each run boots the Kernel once and handles every path):

```bash
SMOKE_HOST=app.test php docs/upgrade-kit/smoke.php / /login /some/page
SMOKE_HOST=app.test SMOKE_USER=user@example.test php docs/upgrade-kit/smoke.php /account /orders/1
SMOKE_HOST=admin.app.test SMOKE_USER=admin@example.test SMOKE_FIREWALL=admin php docs/upgrade-kit/smoke.php / /user /user/1/edit
SMOKE_DEBUG=0 …          # render error pages as in prod
SMOKE_DUMP=/tmp/pages …  # save bodies, e.g. to check a tag or count assets
SMOKE_COOKIES=1 …        # print the cookies each response sets (name, domain, path)
```

Cover: public pages in each locale, login/registration/reset-password (forms build validators), every logged-in area, every admin CRUD (index/detail/edit), pages with live components, Turbo/Mercure, charts, uploads. Check the JS too: dump a page and request each versioned `/assets/…-<hash>.js` (AssetMapper compiles them on request in dev). The un-hashed names in the import map are module names, not URLs.

Then a browser pass by a human: forms with dynamic fields, Turbo navigation, live updates (two browsers), charts, icons, the admin look.

## 10. Environment notes (Claude container)

- **Same PHP as the app** in the sidecar makes console, lints, phpstan and DB queries possible there (pdo_pgsql included); Composer updates still go through the host because of packagist (§8).
- **Probing the firewall** (curl `--resolve` to other IPs, `sudo` on the firewall script) is blocked by Claude's auto mode — ask the human instead.
- **Multi-host kernels** (one codebase, config/cache depending on `HTTP_HOST`): lint and smoke each host (`HTTP_HOST=… bin/console …`), and remember the cache dir may be per host (`rm -rf` all of them). Browsers send `Host: name:port` on non-standard ports.
- Dev data can carry leftovers that look like upgrade bugs (enum values removed without migration). Check `git log -S` before "fixing" the upgrade.

## Appendix — Kot Market, before → after

| Package | Before | After |
|---|---|---|
| PHP | 8.3 (prod) / 8.4 (dev) | 8.5 |
| symfony/* | 7.4.23 | 8.1.11 |
| easycorp/easyadmin-bundle | 4.29.16 | 5.10.0 |
| symfony/ux-* + stimulus-bundle | 2.36 | 3.7 |
| symfony/monolog-bundle | 3.11 | 4.1 |
| symfony/mercure-bundle (+ symfony/mercure) | 0.3.9 (0.6.5) | 0.7.0 (0.10.0) — protocol 0.x kept |
| doctrine/doctrine-bundle | 2.19.3 | 3.3.4 |
| doctrine/doctrine-migrations-bundle | 3.7.1 | 4.0.1 |
| symfonycasts/dynamic-forms | 0.1.3 | 0.2.0 |
| firebase/php-jwt | 6.11 | 7.x |
| phpstan/phpstan (+ rector) | 1.12 (1.2) | 2.3.5 (2.8.4) |

Mercure moved to protocol 1.0 + hub 1.1.6 afterwards (§4.2). Still open there: Symfony 8.2, phpstan extensions + baseline follow-ups.
