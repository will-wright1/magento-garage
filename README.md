# Magento Customer Garage

A standalone Magento 2 module with customer garages, structured vehicle selection, merchant-managed product fitment, and vehicle-specific shopping results. Customers can save multiple vehicles, edit or remove them, select a vehicle and set a default.

**Beta release:** intended for staging evaluation. Complete the [target-store acceptance checks](docs/testing.md#target-store-acceptance-checks) before deploying to a live merchant store.

UK and US specification markets are supported. Plate and VIN entry remain manual, with no DVLA, VIN decoding or external lookup service. Merchants define the available makes, models, model years and variants, and map products to those vehicle definitions. Customers can keep free-text vehicles or match them to the catalogue to shop for compatible products.

## Package and platform

- Magento module: `Automotive_Garage`.
- Composer package: `will-wright1/module-garage`.
- Source repository: [will-wright1/magento-garage](https://github.com/will-wright1/magento-garage).
- Designed for Magento Open Source / Adobe Commerce 2.4 installations using a supported PHP 8.2–8.5 combination. Composer constraints do not replace platform compatibility testing.
- Account management has a server-rendered manual-entry fallback. The cascading catalogue selector uses Magento's RequireJS initializer and targets Luma/custom Magento storefronts. Hyvä needs a selector adapter; its compatibility has not been verified.
- Licence: proprietary, all rights reserved. Public source availability does not make this an open-source package. See [LICENSE](LICENSE).

## Install for development

Copy this repository's contents into `app/code/Automotive/Garage` in an existing Magento application. From the Magento root:

```sh
bin/magento module:enable Automotive_Garage
bin/magento setup:upgrade
bin/magento cache:clean
```

Follow your normal DI compilation and static-content deployment steps for the deployment mode.

For Composer development, place the checkout inside your Magento application, for example `packages/will-wright1/module-garage`, then run:

```sh
composer config repositories.automotive-garage path packages/will-wright1/module-garage
composer require will-wright1/module-garage:@dev
bin/magento module:enable Automotive_Garage
bin/magento setup:upgrade
bin/magento cache:clean
```

Install using one method only; an additional `app/code` copy would register the module twice.

## Customer experience

Open **My Account → My Garage**, or `/garage/account/index`. A static My Garage header link appears on themes exposing Magento's `header.links` block. Guests are directed to sign in.

- Make and model are required. US-specification vehicles also require model year. Plate entry defaults to required for UK registrations and optional for US plates; merchants can override this per store view. VIN/chassis number and nickname are optional.
- UK registrations are uppercased and spaces/hyphens removed for comparison: `ab12 cde` becomes `AB12CDE`. Their display text is preserved. US plates retain spacing and hyphens, accept up to 20 supported characters, and require an issuing state/jurisdiction when provided. These checks do not verify legal plate issuance.
- The first vehicle becomes both default and selected. Adding another vehicle preserves those choices.
- Selection persists in the customer account across devices and sign-ins until changed. Default is a separate fallback preference.
- Removing the selected vehicle selects the remaining default. Removing the default makes the oldest remaining vehicle the new default. Removing the last vehicle leaves an empty garage.
- Duplicate plates are rejected within the same account, issuing country and jurisdiction. Multiple plate-free vehicles are allowed. VIN/chassis duplicates are also account-scoped. Different accounts may save the same identifiers.
- The default limit is 50 vehicles per account. A merchant integration module can change the `maxVehicles` DI argument.
- Vehicles follow Magento's customer-account sharing rules. Independent Magento installations do not share records.
- UK catalogue selection uses make → model → model year → specification; US selection uses model year → make → model → specification. Vehicle specification market and plate country are independent, including imported vehicles. Canonical details are resolved on the server; submitted make/model labels cannot override a selected definition.
- Existing free-text vehicle records survive schema upgrades. Use Edit to match them to a catalogue definition. They are not automatically matched by similar names.
- **Shop for this vehicle** selects an owned, mapped vehicle and opens `/garage/shop/index`. Products explicitly mapped to that definition and products marked universal for its market (or explicitly both markets) are included. Unknown products are excluded. Products retain Magento's store, visibility, enabled-status and stock filtering.

## Merchant setup

Open **Catalog → Garage Vehicles & Fitment**. The admin role needs the `Automotive_Garage::manage` permission.

1. Create one vehicle definition for each market, model year and variant, with a globally unique stable vehicle code. Optional structured fields record generation, trim, engine, fuel, steering, cab/body, drivetrain, transmission, wheelbase and bed length.
2. Load a product by SKU and assign one or more vehicle codes, or explicitly mark it as universal for GB, US or both markets.
3. Import larger datasets using the documented CSV formats. Each file is processed in a transaction. Fitment imports replace mappings for the included SKUs; other SKUs remain untouched.
4. Match a saved customer vehicle and use its Shop button to see the results.

Configure default market, plate country, plate requirement and badge style under **Stores → Configuration → Catalog → Customer Garage**. Defaults apply only to new records. Use Magento store-view locales `en_GB` and `en_US` for British/American wording.

See the [merchant guide](docs/merchant-guide.md) for examples and migration notes. No sample fitment data is automatically installed.

## Data and security

Dedicated tables store customer vehicles, canonical vehicle definitions, explicit product rules and product-to-vehicle mappings. This module does not modify core files or product attributes. Customer deletion cascades to garage records. Product deletion removes its fitment records.

All storefront writes use POST, validate the form key and obtain customer identity from the signed-in session. Reads and mutations are scoped to the owner. Submitted data cannot assign customer IDs or preference flags. User text is escaped when rendered.

The garage account and shopping results pages are uncacheable. Global header markup contains no personal data. The optional private `garage` customer-data section contains only the signed-in customer's vehicles and an empty response for guests. The public selector endpoint returns vehicle definitions only.

Mutations run in a transaction with a customer-row lock, including when the garage is empty. This serialises writes per customer to protect the account limit and single-default/single-selection invariants. Database unique constraints additionally protect account/jurisdiction/plate and account/VIN pairs. Empty identifiers are stored as NULL, permitting multiple vehicles without identifiers. Full VINs are excluded from the browser customer-data section.

## Extension points

Inject `Automotive\Garage\Model\Garage` for `getList`, `get`, `getSelected`, `save`, `delete`, `select` and `setDefault`. Obtain customer IDs from trusted authenticated context, never submitted input. `save` accepts registration, make, model, optional model year, variant and nickname. Supplying `vehicle_type_id` resolves the canonical vehicle details on the server. An optional separate vehicle ID updates an owned record.

Additional inputs are `vehicle_market` (`GB`/`US`), `registration_country` (`GB`/`US`), `registration_region`, `vin` and `vin_format` (`standard`/`legacy`). New records use store defaults when market/country are omitted. Edits preserve omitted market/jurisdiction choices; supply the full editable field set to retain the other values. `SettingsInterface` exposes the configured defaults and plate policy.

`Automotive\Garage\Api\VehicleStorageInterface` isolates persistence and `VehicleTypeProviderInterface` supplies available canonical definitions. These are internal PHP interfaces, not public REST APIs. Registration providers can later resolve a vehicle to the same market-qualified canonical definition IDs. ACES/PIES adapters and licensed reference datasets are not included.

Luma integrations can read `Magento_Customer/js/customer-data`'s `garage` section. Keep registration numbers out of shared full-page-cache HTML. A dynamic header vehicle dropdown is outside this first release.

## Validation

These checks run without a Magento installation:

```sh
php dev/validate.php
php Test/run.php
composer validate --strict --no-check-publish
npm ci --prefix dev/js
npm test --prefix dev/js
```

The standalone PHP tests use persistence doubles and evaluate the production account template with block/escaping doubles. Selector tests execute the production JavaScript in jsdom, including asynchronous response races and error handling. Database and HTTP integration tests are provided in `Test/Integration`. See [testing and release checks](docs/testing.md) for the validation status and remaining runtime checks.

## Beta distribution

The first release candidate is `0.1.0-beta.1`. Once the repository is registered on Packagist, install it with:

```sh
composer require will-wright1/module-garage:0.1.0-beta.1
bin/magento module:enable Automotive_Garage
bin/magento setup:upgrade
bin/magento cache:clean
```

Complete the target-store acceptance checks before a live deployment. A Packagist listing distributes Composer metadata; it does not grant permissions beyond the proprietary licence. Follow your normal DI compilation and static-content deployment steps.
