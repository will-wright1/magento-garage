# Validation and release checks

## Customer administration verified on 10 November 2026

- 45 standalone PHP tests pass. All 19 module XML files validate against Magento 2.4.9 schemas. Composer metadata and PHP/template syntax validation pass; Magento DI compilation passes.
- The disposable Magento 2.4.9 application passes 20 admin runtime checks in `dev/admin-smoke.php`: owner resolution, ignored posted identity/preferences, real UK/US store policies, cross-customer mutation rejection, canonical code linking/manual unlinking, literal/full-name search, website/market filtering, full-VIN omission, real read-only/manage template output, customer-tab access control, independent Magento ACL resources, core controller authorization, stable SQL pagination, preference fallback and invalid form-key/GET rejection.
- Interactive Chrome verification covers the native Customers menu entry, garage list/detail screens, saving/editing a synthetic vehicle through the admin POST/redirect flow, duplicate rejection with form-value preservation, cancellation of the delete dialog, and the Customer Garage tab inside Magento's customer edit screen. Fixtures are isolated from merchant installations.
- All 13 selector JavaScript tests, 17 UK/US runtime checks and seven original Magento runtime checks also pass after these changes.
- These additions are included in `0.1.0-beta.2`; the earlier `0.1.0-beta.1` tag does not include them. Full Magento PHPUnit suites, custom admin themes, production performance/caching and other Magento/PHP combinations still require acceptance testing.

## UK/US changes verified on 28 October 2026

- 43 standalone PHP behaviour/template tests and 13 JavaScript selector tests pass. Syntax/metadata validation passes; all 16 XML files validate against Magento 2.4.9 XSDs.
- An existing disposable Magento Open Source 2.4.9 database was upgraded, preserving its two saved vehicles, definitions, preference flags and product mappings. The whitelist now includes Magento-generated names for historical constraints so the old uniqueness rules can be removed safely.
- 25 market runtime assertions passed with the pre-upgrade snapshot: row preservation, GB defaults, distinct market definitions, scoped plate duplicates, plate-free records, UK-registered US imports, changing plate jurisdiction, immutable specifications, market-scoped universal products, VIN omission from customer-data, real US store-view settings, `en_US` translations, rendered US account fields, selector-controller JSON and guest redirect results.
- The seven original runtime checks still pass. Magento DI compilation passes. Admin management templates, virtual configuration sources and the Customer Garage configuration section render through real Magento services.
- Browser automation was disabled in Work settings for this session. The new customer flows have JavaScript DOM tests and real Magento template/controller/service checks, but have not been exercised in an interactive browser. The earlier Luma browser checks below predate these market changes. Full Magento PHPUnit suites, production mode/CDN/Varnish, custom themes and other Magento/PHP combinations still require acceptance testing.

## Verified on 21 October 2026

- PHP 8.5.9: 29 standalone behaviour/template tests passed. PHP/template syntax, JSON, XML and Composer metadata checks passed.
- Seven JavaScript selector tests passed in jsdom, including restoration, cascading selection, stale responses, request failures, incomplete submissions and escaped labels.
- All 14 Magento XML files passed XSD validation against official Magento 2.4.8 and 2.4.9 sources.
- The module installed successfully in an isolated Magento Open Source 2.4.9 application using MariaDB 11.8 and OpenSearch 3.2. Magento DI compilation passed.
- The merchant management block and template rendered successfully through Magento's admin layout services.
- Seven checks against the installed application passed: canonical persistence through Magento DI, real database rollback, customer ownership, specific/universal fitment, filtering before pagination, the storefront product collection, and atomic CSV rollback.
- Browser verification on Luma covered guest sign-in, matching an existing manual vehicle to the catalogue, saving a second vehicle, retaining the original default/selection, and switching shopping results. The 2020 fixture returned its specific product plus a universal product; the 2021 fixture returned only the universal product. An unmapped product was excluded from both results.

The installed runtime checks use synthetic fixtures in an isolated disposable installation. Local runtime files, backups and snapshots are excluded from version control. No live merchant store was modified. Full Magento PHPUnit integration suites, the catalogue/fitment admin browser workflow, Hyvä, Adobe Commerce, Varnish and other PHP/Magento combinations have not been verified. The initial beta's five GitHub validation jobs passed; new unreleased changes require another remote run. The checks above do not constitute a production compatibility guarantee.

## Repeatable checks

These commands run from this repository without Magento:

```sh
php dev/validate.php
php Test/run.php
composer validate --strict --no-check-publish
npm ci --prefix dev/js
npm test --prefix dev/js
```

GitHub Actions runs the PHP checks across PHP 8.2–8.5 and the selector tests on Node 22. Standalone PHP tests use persistence doubles; their rollback checks do not exercise InnoDB locking.

Validate XML against the exact Magento source release used by the target store:

```sh
php dev/validate-schemas.php /path/to/magento-source
```

After installing the module in a **disposable** Magento application with its default website/store enabled, run:

```sh
php -d memory_limit=2G dev/magento-smoke.php /path/to/magento --disposable
```

This script creates and removes synthetic customers, products, vehicle definitions and mappings through real Magento services. It reindexes its fixture products. Use it only on a disposable development installation. The storefront check narrows its collection to the fixture products so unrelated universal products do not affect the assertion.

For the UK/US checks, run:

```sh
php -d memory_limit=2G dev/market-smoke.php /path/to/magento --disposable
```

For customer administration, run:

```sh
php -d memory_limit=2G dev/admin-smoke.php /path/to/magento --disposable
```

This creates/removes synthetic customers, a store view, its garage configuration, vehicle definitions and saved vehicles. It loads real admin services/templates and checks the Magento ACL tree and controller authorization. It does not replace browser testing with actual restricted-role logins or a full Magento integration-test dispatch.

For a repeatable migration audit, take a snapshot on the older **disposable** installation before deploying the market changes:

```sh
php dev/snapshot-garage.php /path/to/magento /path/to/new-snapshot.json --disposable
# Deploy the updated module and run Magento setup:upgrade.
php -d memory_limit=2G dev/market-smoke.php /path/to/magento --disposable /path/to/new-snapshot.json
```

The snapshot contains synthetic garage rows, not application credentials. It must not be taken from a live merchant database. The market script creates/removes its own customers, products, definitions and temporary US store view; it restores the current store and deletes its temporary configuration. Assertion counts depend on the number of pre-existing fixture rows in the snapshot.

To run the fuller PHPUnit tests, install Magento's development dependencies, configure its disposable integration test database, then run from the Magento root:

```sh
vendor/bin/phpunit -c dev/tests/integration/phpunit.xml.dist app/code/Automotive/Garage/Test/Integration
```

Use the Composer-installed module path instead if applicable. Most integration tests rely on Magento's database isolation; the failed-CSV test deliberately runs without an outer test transaction to check a real rollback.

Run `bin/magento setup:di:compile` and the store's usual static-content deployment. Check installation/upgrade logs and compare the schema whitelist in an unprefixed development database before release.

## Target-store acceptance checks

1. Configure separate `en_GB` and `en_US` store views and their garage defaults. Check year-first US and make-first UK selection, optional/required plate policies, VIN formats, state handling, import scenarios, badge overrides, regional terminology and disabled/missing catalogues. Confirm selection market is independent of plate jurisdiction.
2. Sign in as customer A and open My Account → My Garage. Check the empty state, manual entry and catalogue selection. Confirm a second vehicle persists after signing in on another browser.
3. Edit manual and mapped vehicles. Reject duplicates, invalid IDs, disabled definitions, empty required fields and overlong values. Confirm failed submissions preserve useful form values. Switching to manual entry must clear the old fitment association.
4. Switch Selected and Default independently. Remove them in different orders, then remove the last vehicle. Check the account limit and concurrent adds/default/selection requests.
5. Sign in as customer B. A's vehicles must be absent. Submit A's IDs to edit/save/delete/select/default/shop routes; verify rejection and unchanged records. B may separately save the same registration.
6. Confirm guests cannot read or mutate private vehicles/results. GET requests to mutation routes and POSTs without valid form keys must not change data, including XMLHttpRequests.
7. In admin, create/disable vehicle definitions, map a SKU to multiple definitions, mark another SKU universal and clear a mapping. Confirm in-use definitions cannot silently change identity. Verify roles without the Garage permission cannot access these routes.
   For customer administration, test an unrestricted admin, a view-only garage role and a role with neither garage permission. Check menu/tab visibility and direct read/write URLs. Search/filter/page through saved vehicles; open an empty customer's garage; add/edit/link/unlink/select/default/delete vehicles in both markets. Check invalid input redisplay, customer account visibility, masked VINs and operational logs. Verify store policy uses the named customer store view and that filters do not imply website-specific access control.
8. Import both sample CSV formats using real test SKUs. Check replacement for included SKUs, preservation of other SKUs, malformed/unknown rows, file limits and whole-file rollback.
9. Check vehicle results across years/variants, unknown products, disabled products, out-of-stock settings, website assignment, sorting and multiple pages. Map visible parent SKUs explicitly for configurable/grouped/bundle products.
10. Enter HTML-like text in vehicle labels/nicknames; it must display as text. Check keyboard navigation, focus, mobile widths and zoom in the target theme.
11. With full-page cache/Varnish enabled, switch between A, B and a guest. Cached pages must not contain registrations. Check private-section invalidation after mutations and account changes.
12. Delete a disposable customer through Magento and confirm its vehicle rows disappear. Include garage data in merchant-specific export/anonymisation workflows where applicable.

## Current boundaries

Shopping results use merchant-provided fitment data. Registration numbers are manually entered and do not identify a vehicle automatically. No DVLA or external lookup is included. Ordinary category/search pages are not automatically filtered; this release provides a dedicated vehicle results page. Product compatibility badges, a dynamic header dropdown, before/after admin revision history, REST/GraphQL APIs and cross-installation synchronisation remain outside this milestone.
