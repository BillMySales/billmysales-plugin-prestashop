BillMySales for PrestaShop
==========================

PrestaShop module that sends orders to [BillMySales](https://www.billmysales.com)
when they reach the order states you choose (BillMySales then issues the
billing document), and adds custom billing fields to the checkout's address
form (e.g. tax id, business activity, receipt or invoice).

- Deliveries are queued when an order reaches a selected state (or the
  merchant asks to send it again), never sent from that same request: the
  checkout and the admin never wait for BillMySales. They are sent by the
  module's own cron controller, by PrestaShop's `actionCronJob` hook when
  the `cronjobs` module is active, or opportunistically from the back
  office. Failed deliveries are retried on network errors, timeouts, rate
  limits and server errors (after 1 min, 5 min, 30 min, 2 h and 12 h); other
  HTTP errors are logged, not retried. BillMySales is idempotent, so a
  repeated delivery is harmless.
- Each notification is signed with HMAC-SHA256 with the secret shared with
  BillMySales.
- The order detail page shows the last known delivery status, and a "Send to
  BillMySales" button to send it again.
- The custom fields work in the checkout's address form and in "My
  addresses"; they can also be filled in from the order detail page (for an
  order not placed through the checkout) and from the "Add new order" form.

Requirements: PrestaShop 8.0+ (tested up to 9.1), PHP 7.4+ (tested up to
8.5).

Installation
------------

1. Download `billmysales-prestashop-<version>.zip` from the
   [releases](https://github.com/BillMySales/billmysales-plugin-prestashop/releases)
   (not the repository's own zip).
2. Back office > Modules > Module Manager > Upload a module: upload it.
3. Modules > BillMySales > Configure > Settings: the notification URL and
   the secret given by BillMySales, the order states that notify, and
   "Deliveries".
4. Optionally, Modules > BillMySales > Configure > Checkout fields: each
   field has a label (its key is derived from it), optional comma-separated
   values (then it's a list) and whether it's required.
5. For the deliveries to run without depending on an employee browsing the
   back office, point a system cron at the module's own controller (shown
   on the settings page once configured, or built as
   `<shop URL>/index.php?fc=module&module=billmysales&controller=cron&token=<the module's cron token>`),
   e.g. every 5 minutes.

Notification
------------

A `POST` to the configured URL, in either of two formats (chosen in the
settings):

- **Standard**: the order data BillMySales has parsed since the module's
  1.x versions (the order's own fields, plus `customer`, `cart`, `address`
  the delivery address, `billing` the invoice address, `carrier`,
  `products`, `detail` and `shop`), with the customer's password hash and
  autologin token, and the shop's theme configuration, removed.
- **Webservice**: PrestaShop's own webservice representation of the order
  (as `GET /api/orders/<id>` would return it), built by the module without
  an HTTP call or a webservice key.

In both formats, the checkout fields' values are attached under
`billmysales_custom_fields` (`{key: value}`).

Headers:

| Header | Value |
|---|---|
| `X-BillMySales-Signature` | base64 of the HMAC-SHA256 of the raw body with the secret |
| `X-BillMySales-Platform` | `prestashop` |
| `X-BillMySales-Platform-Version` | PrestaShop version |
| `X-BillMySales-Plugin-Version` | plugin version |
| `X-BillMySales-Source` | store URL |
| `X-BillMySales-Event` | `order.created` (an order created directly in a selected state), `order.status_changed` or `order.resent` ("Send to BillMySales") |
| `X-BillMySales-Delivery` | UUID of the notification (the same on retries) |
| `User-Agent` | `BillMySales-prestashop/<version>` |
| `X-PRESTASHOPBMS-HMAC-SHA256` | the same signature, as the module's 1.x versions sent it |

Verifying the signature (Python):

```python
expected = base64.b64encode(hmac.new(secret, body, hashlib.sha256).digest()).decode()
hmac.compare_digest(expected, headers["X-BillMySales-Signature"])
```

Development
-----------

Layout: `plugin/` is the module (what the zip installs, as the
`billmysales` folder); the repository root has the development tools.

```
plugin/                 billmysales.php (the module class; thin, delegates to src/)
  src/                  classes (BillMySales\PrestaShop\...), unit-tested
  controllers/front/    cron.php (the module's own cron controller)
  assets/{css,js}/      settings page and checkout fields script
  translations/         es.php (classic per-module catalog)
tests/src/              PHPUnit unit tests
docker/Dockerfile       tools image (PHP CLI, Composer, Xdebug)
scripts/                i18n extraction and translation tooling (make i18n)
```

Every task runs in Docker containers (the host's PHP and Node.js aren't
used): PHP 7.4, the lowest the plugin supports (so PHPUnit 9.6;
`PHP_VERSION=8.5` runs them on another one), and ESLint in `node:24-alpine`:

```shell
make install        # composer install (dev tools) and npm ci
make lint           # PHP CS Fixer (PSR-12), dry run; required docblocks (phpcs.xml); ESLint (rules, JSDoc, style)
make fix            # PHP CS Fixer's and ESLint's fixes (style)
make analyse        # PHPStan (community PrestaShop stubs, PHP 7.4)
make test           # PHPUnit, with coverage (var/tests-coverage.txt): 100% of plugin/src
make check          # all of the above + version consistency
make i18n           # rescans the plugin's source strings and applies plugin/translations/es.php's translations
make build           # dist/billmysales-prestashop-<version>.zip
make clean
```

The version lives in the module's own metadata (`$this->version` in
`plugin/billmysales.php`); `CHANGELOG.md` must match it (`make check`).

Source strings are English. After changing them: `make i18n`, translate any
new entry it reports in `scripts/i18n-es.php`, then run `make i18n` again.

### End-to-end tests

```shell
make e2e            # E2E_KEEP=1 keeps the stack running (then make e2e-clean)
```

`tests/e2e/run.sh` clones the
[PrestaShop Docker stack](https://github.com/BillMySales/billmysales-docker-prestashop)
into `var/e2e/stack` (`STACK_REPO`, `STACK_REF`, default `master`), starts it
with the module mounted and a webhook receiver on port 8099
(`tests/e2e/receiver.php`, standing in for BillMySales: it stores each
request as received and answers the status a case asks for), runs the cases
and removes the stack and the receiver. It needs only Docker. **The stack's
development ports (8102, 8402, 8025) and 8099 must be free: stop the
PrestaShop development stack first** (the script checks them before
starting).

Each case does something in PrestaShop and `tests/e2e/check.php` checks what
reached the receiver: the number of requests, the signature (recomputed from
the raw body with the secret, in `X-BillMySales-Signature` and
`X-PRESTASHOPBMS-HMAC-SHA256`), the headers (platform, versions, event,
source, delivery UUID, User-Agent, the secret in none), the payload against
the order's JSON Schema (`tests/e2e/order-schema.json`), and the order's id,
state and checkout fields.

The deliveries stay in `var/e2e/webhooks` until the next run or `make
clean`: `<case>-<time>.body` is the raw body, `.json` has the headers, the
decoded payload and the status the receiver answered.

### Releases

Bump the version (`$this->version` in `plugin/billmysales.php`), add its
`CHANGELOG.md` entry, commit and push a `vX.Y.Z` tag. The release workflow
(`.github/workflows/release.yml`) calls the tests (`ci.yml`, PHP 7.4 and
8.5) and then the end-to-end tests (`e2e.yml`), and only when both passed
checks the tag matches the version, runs `make build` and publishes the
GitHub Release with the zip. `ci.yml` and `e2e.yml` also run on every push
and pull request (the end-to-end deliveries are uploaded as the
`e2e-results` artifact). Dependabot (`.github/dependabot.yml`) opens weekly
pull requests for the Composer and npm tools and the workflows' actions.

Platform decisions
-------------------

Recorded here (not compared against anything else) so the reasoning behind
each choice stays with the code:

- **License**: the European Union Public Licence v. 1.2 (EUPL-1.2). The
  Addons Marketplace's technical validation checklist accepts Apache-2.0,
  AFL-3.0, MIT, BSD, ISC and EUPL; among those, EUPL-1.2 is explicitly
  listed as compatible with (and convertible to) the GNU Affero General
  Public License v3, making it the closest strong-copyleft option the
  Marketplace allows. PrestaShop's own contribution guidelines separately
  suggest AFL-3.0 as a default for modules contributed to its own ecosystem;
  that is a suggestion for PrestaShop's own repositories, not a Marketplace
  distribution requirement, so it didn't override the above.
- **Minimum version**: PrestaShop 8.0, tested up to 9.1, matching the
  versions this project's own PrestaShop stack runs. PrestaShop's release
  policy backports fixes only one major/minor generation back, so older
  lines (1.6, 1.7) no longer receive security patches; there is no
  PrestaShop-published usage-share data (unlike wordpress.org) to weigh a
  lower floor against. PHP 7.4+ (tested up to 8.5): still within PrestaShop
  8.0's own supported range (7.2-8.1), chosen so one toolchain (PHP CS
  Fixer, PHPStan, PHPUnit) runs unmodified from the floor to the latest PHP
  tested, without a version-specific gap in any of them.
- **No PHPStan stubs package**: no official stub package for PrestaShop core
  classes exists; `stancer/php-stubs-prestashop` (community) is used
  instead. A couple of its type hints reference classes it doesn't itself
  declare (`InstallLanguage`, `LegacyControllerBridgeInterface`); the
  resulting two error patterns are ignored in `phpstan.neon`, with the
  reasoning recorded there.
- **No automated security ruleset**: PrestaShop has no public
  PHP_CodeSniffer ruleset for its Addons Marketplace checklist (SQL
  sanitization, no `serialize()`/`unserialize()`, escaped Smarty output,
  `index.php` in every folder, ...); followed by convention and checked in
  code review. `validator.prestashop.com` (the Marketplace's own validator)
  is a hosted tool, not a package: run it by hand on `dist/*.zip` before a
  release.
- **Delivery queue and cron**: PrestaShop's core has no built-in
  asynchronous job queue. `actionCronJob` exists only when the separate,
  optional `cronjobs` module is installed and configured, so it can't be
  the only way to run deliveries. The module keeps its own small queue
  table (`billmysales_delivery_queue`, one row per pending delivery, removed
  once it's no longer pending — not a growing log) and three ways to drain
  it: its own cron controller (`plugin/controllers/front/cron.php`, a
  token-protected URL meant for a real system cron), `actionCronJob` when
  available, and a bounded batch run opportunistically on every back office
  page load, so a shop with no cron configured still delivers eventually.
  An order state change or a manual resend only ever queues a delivery,
  never sends it in the same request.
- **Per-order delivery status**: PrestaShop has no equivalent to a growing,
  per-order private note log. `billmysales_order_status` keeps one row per
  order that has ever had a delivery attempt (replaced on each new attempt,
  bounded by the shop's order count, not by attempts), shown on the order
  detail page; the technical log (every attempt, with its detail) goes to
  PrestaShop's own logger (Advanced Parameters > Logs), which has no
  built-in retention — clear it by hand from there if it grows large.
- **Payload format**: kept the exact "standard" shape (the order's own
  fields, plus `customer`, `cart`, `address`, `billing`, `carrier`,
  `products`, `detail`, `shop`) the 1.x module already sent, since changing
  it without being able to check it against BillMySales' own parser for
  this platform risks breaking existing integrations silently. The
  "webservice" format is an additive alternative for a newer BillMySales
  integration meant to read PrestaShop's own webservice representation
  instead: it doesn't touch the standard format at all, and is opt-in.
- **Webservice format, built without HTTP**: `Order::getWebserviceParameters()`
  and `WebserviceOutputBuilder`/`WebserviceOutputJSON` are the classes
  PrestaShop's `/api/orders/<id>` endpoint itself uses internally; the
  module drives them the same way `WebserviceRequest::run()` does
  (`setObjectRender()`, the object under an `'empty'` key, `VIEW_DETAILS`,
  `getContent()`'s default `$override` already returning the final JSON
  string), without the webservice's own authentication layer. This is
  undocumented, internal usage (no public "call the webservice in-process"
  API exists); verified against a running instance, and by comparing the
  result's shape with a real `/api/orders/<id>` response.
- **Translations**: the classic per-module system (`$this->l()`,
  `{l s='...' mod='billmysales'}`), matching what the module already used.
  Its catalog file is keyed by a 2-letter ISO language code
  (`translations/<iso_code>.php`), not by a full locale, so a shop's
  Spanish-language employees see the same catalog whichever Spanish variant
  they're set to; `translations/es.php` is written for Chile (BillMySales'
  primary market). PrestaShop's newer, Symfony-based translation system
  does support locale-specific catalogs, but would mean moving every string
  in the module away from `$this->l()`/`{l}`, for a distinction (es-CL vs.
  es-ES) most employees using this module won't need.
- **No JSDoc build step for `plugin/assets/js/admin.js`**: it's loaded as
  is by the back office (no bundler), so it stays plain, browser-ready
  JavaScript; ESLint (with `eslint-plugin-jsdoc`) only checks it, it
  doesn't transform it.
- **End-to-end fixtures without a CLI**: PrestaShop has no WP-CLI-alike
  first-party CLI. `tests/e2e/fixtures.php`, copied into the stack's
  `prestashop` container and run with plain `php` (not the console, whose
  Symfony kernel prints deprecation noise ahead of a command's own output,
  making its stdout unsafe to capture as a value), bootstraps PrestaShop
  itself for the handful of setup steps that need it (creating a product;
  changing an order's state through `Order::setCurrentState()` so
  `actionOrderStatusPostUpdate` fires exactly as it would from the back
  office; reading/writing Configuration values). Everything a real shopper
  or merchant would do (checkout, the settings form, resending an order)
  goes through plain HTTP with curl, cookies and the page's own CSRF tokens
  instead, exactly as a browser would submit them; the console's
  `prestashop:module install/uninstall` covers the module's own lifecycle.
- **`run.sh`'s health-check retries**: a fresh install can leave a few
  `var/cache` entries root-owned (their ownership isn't fixed recursively),
  which crashes the installer's own post-install cache clear before it can
  rename the (installer-randomized) admin folder to `PS_FOLDER_ADMIN`, and
  later 500s on any back-office request touching one of those entries
  (found: `ps_accounts`' own cache file). `run.sh` fixes ownership,
  re-runs `setup` until the folder is renamed, and restarts `prestashop`
  (its already-running PHP-FPM workers keep the pre-fix state compiled in
  their own opcache otherwise). Storefront and admin login requests also
  carry curl's own `--retry`, since the very first hit after a fresh
  install (or after that restart) can 404 briefly while a route or object
  cache warms up. `docker compose up -d --wait` itself is avoided: its exit
  code can report failure from one transient health check, or dislike the
  one-shot `setup` container exiting even successfully; `run.sh` polls the
  services it actually needs instead.

License
-------

Copyright (c) 2026 BillMySales. Licensed under the
[European Union Public Licence v. 1.2](LICENSE) (EUPL-1.2).
