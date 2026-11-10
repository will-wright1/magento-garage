# Garage administration and product fitment

## Manage customer garages

Use **Customers → Customer Garages** for the cross-customer saved-vehicle list. Search names, email addresses, plate text, makes, models and nicknames; narrow results by website or specification market. The list uses 50-row SQL pagination and does not include full VINs. It lists saved vehicles, so customers with an empty garage will not appear. Open those customers using their numeric customer ID or **Customers → All Customers → Edit → Customer Garage**.

Open **View garage** to see one customer's vehicles and their current default and selected flags. Default is the fallback when selection is removed; Selected controls the vehicle-specific parts results. VINs are masked in summaries. Staff with management permission can add a vehicle, edit its details, choose default/selected vehicles or delete it after the confirmation dialog. Removing a default/selected vehicle uses the same reassignment rules as the customer account.

Set roles under **System → Permissions → User Roles → Role Resources → Customers**:

- **View Customer Garages** grants read-only garage access, including registrations and customer contact details.
- **Manage Customer Garages** additionally permits writes and full VIN access in the edit form. Give managers both garage permissions.
- **All Customers** is also needed to open Magento's customer edit screen. It is not needed for the dedicated Customer Garages screen.
- **Manage Garage Vehicles and Product Fitment** under Catalog remains separate; it does not grant access to customer garages.

After installing/updating, clean Magento's configuration/layout caches and sign in again if the menu or role resources have not refreshed. Existing unrestricted administrators inherit these permissions; restricted roles should be reviewed explicitly.

For manual entry, leave **Vehicle catalogue code** blank and supply make/model (plus model year for US specifications). For known fitment, enter an enabled code from **Catalog → Garage Vehicles & Fitment**; the code supplies canonical make/model/year/variant. Leaving a previously linked code blank removes the fitment association. Changing or adding a disabled code is rejected. A plate does not automatically identify a vehicle.

UK/US defaults and plate requirements come from the customer's `store_id` when that store belongs to the customer's website, otherwise the website default store. The screen names this store. Saved records keep their existing market and jurisdiction when edited; the form allows staff to change them deliberately. US plates require their issuing state/jurisdiction; GB plates must have no US state selected. Current database and input validation still apply, including duplicate identifiers and the vehicle limit.

Garage forms save independently of Magento's customer form. Save customer changes before following the garage link. Each successful change records administrator ID, customer ID, submitted vehicle ID (empty for a new record) and operation in the configured PSR logger, normally `var/log/system.log`. This is an operational record, not a before/after revision history or undo system. Vehicle plates, VINs and personal text are omitted from success logs.

Changes are visible when the customer next opens/reloads My Garage. An already-open customer tab or a custom integration caching private sections may need refresh. No customer email is sent. These permissions apply across the Magento installation; website filters narrow the display and are not website-based access restrictions.

## Upgrade an existing garage installation

Deploy the updated module and run `bin/magento setup:upgrade`, DI compilation and the store's normal static-content deployment. Existing customer records remain intact. Existing pre-market records and definitions receive `GB` specification/registration defaults. Record IDs, selections, defaults and mappings are preserved. Added specification fields start empty; no automatic name matching or compatibility is inferred. Existing universal rules receive `GB` scope, so launching US selection does not broaden them automatically.

Customers can edit a saved vehicle and choose a catalogue definition. This retains its garage record ID, registration and preference flags. Switching back to manual entry clears its canonical association, so it cannot keep using an outdated fitment match.

## Define vehicles

Open Catalog → Garage Vehicles & Fitment. Create one definition per specification market, make, model, model year and variant. For example:

| Code | Make | Model | Model year | Variant |
| --- | --- | --- | --- | --- |
| ford-ranger-2020-double | Ford | Ranger | 2020 | Double Cab |
| ford-ranger-2021-double | Ford | Ranger | 2021 | Double Cab |
| toyota-hilux-2020-extra | Toyota | Hilux | 2020 | Extra Cab |

These are format examples, not a verified fitment dataset. The merchant must choose the detail needed by its actual products. Variant can include generation/facelift, engine, trim, cab, wheelbase or body details. A generic vehicle definition should only be used when that precision is sufficient for the products linked to it.

Codes are stable merchant identifiers and must be unique. A market/make/model/year/variant combination must also be unique. Identical names may exist in GB and US with separate IDs and mappings. Use distinct variant labels for configurations with different specifications; the selector includes the additional specification details in each option. Use one row for each year rather than a free-text year range. Products spanning several years can have several mappings.

Once a definition is referenced by a customer or product, its market, identity and structured specification fields cannot be rewritten. Disable it and create a new definition instead. Disabled definitions cannot be newly selected or used to shop; their existing saved customer details remain available for correction. Re-enabling the same definition restores availability.

## Assign products

Load an existing SKU in Product fitment. Enter compatible vehicle codes, separated by new lines or commas, then save. Saving replaces that SKU's complete mapping list. Map the storefront-visible parent SKU for configurable or grouped products; mappings are not automatically inherited between parent and child products.

A universal product must be explicitly marked Universal with no vehicle codes. Choose `GB`, `US` or `all` (both) as its universal market. A US-specific universal product is excluded from GB vehicle results and vice versa. The default, including for older CSV files, is `GB`. Clearing fitment confirmation returns the SKU to an unknown state, which excludes it from vehicle-specific results. These operations do not alter the product's normal visibility elsewhere in the store.

Specific mappings use globally unique vehicle IDs, including specification market, across websites in a Magento installation. Shopping results still respect the current store's product assignment, enabled status, catalogue visibility and stock settings. Separate website-specific fitment rules are outside this milestone.

## CSV imports

Files must be UTF-8, with a maximum upload size of 1 MiB and at most 5,000 data rows. Fitment files require `sku,vehicle_code,universal`; `universal_market` is optional. Vehicle files require the six legacy columns and optionally accept `market` and the specification columns below. With optional columns present, known columns may be reordered; duplicate, unknown or missing required headers are rejected. UTF-8 BOMs and quoted comma-containing fields are accepted. Use `0` or `1` for boolean columns.

Vehicle definition headers:

```csv
code,market,make,model,model_year,variant,is_enabled
gb-ford-ranger-2020-double,GB,Ford,Ranger,2020,Double Cab,1
us-ford-ranger-2020-crew,US,Ford,Ranger,2020,Crew Cab,1
```

Vehicle imports add definitions or update existing codes, subject to the identity protection above. Definitions absent from the file are preserved. A duplicate code or invalid row fails the file instead of applying a partial import.

Fitment headers:

```csv
sku,vehicle_code,universal,universal_market
EXAMPLE-UK-COVER,gb-ford-ranger-2020-double,0,GB
EXAMPLE-US-COVER,us-ford-ranger-2020-crew,0,US
EXAMPLE-CLEANER,,1,all
```

Replace example SKUs with existing products and review all compatibility before importing. Repeated rows for a SKU build its complete replacement mapping list; codes are deduplicated. Unlisted SKUs retain their mappings. Mixing universal and specific mappings, unknown SKUs or unknown codes rejects the file.

## Customer journey

1. Sign in and open My Garage.
2. Choose the specification market and enter plate/VIN details if applicable. Plate issuing country is independent of specification market.
3. Choose a catalogue vehicle. UK selection starts with make; US selection starts with model year. Complete the specification and save.
4. Use Shop for this vehicle to select it and open matching results.
5. Open a product to view its details and buy through the normal Magento product page.

Customers can still save manually entered vehicles when the catalogue is incomplete. Those records offer a matching link instead of a Shop button. Selection does not claim that the registration has been verified. Product fitment is based on the merchant's mappings.

## Current boundaries

This milestone provides a dedicated vehicle-specific product results page with standard sorting and pagination. It does not automatically filter ordinary category/search pages, add a dynamic header switcher, attach vehicles to orders or display product-page fitment badges. These remain subsequent extensions to the same canonical mapping model.


## UK and US store configuration

Under Stores → Configuration → Catalog → Customer Garage, choose the store view and configure:

| Setting | Default | Behaviour |
| --- | --- | --- |
| Default vehicle specification market | GB | Initial choice for a new vehicle, independent of address or plate country. Customers can choose either supported market. |
| Default plate issuing country | GB | Initial registration jurisdiction. Set US for a US store view. |
| Plate entry | Auto | UK plates required, US plates optional. Required/Optional overrides apply to both issuing countries. Enforced on the server as well as in the form. |
| Registration badge style | Auto | Yellow for UK plates; neutral for US plates and vehicles without a plate. Can be forced to either style. |

For a US store view, set the first two defaults to US and the Magento locale to `en_US`. For UK wording use `en_GB`. Changing store defaults never reclassifies existing saved vehicles. Product pricing/currency/tax/checkout continue to use Magento configuration.

A US-specification import registered in the UK must keep vehicle market `US` and plate country `GB`. Never infer specification market from store language, customer address, or registration country. The selector endpoint always filters by the chosen market, and saving a definition from another market is rejected.

US plate entries require an issuing state/jurisdiction when non-empty; the list includes DC, US territories and military postal jurisdictions. Plate-free vehicles are allowed when store policy permits, even when make/model/year match another vehicle. Each retains its own stable garage ID and preferences. UK comparison removes spaces/hyphens; US spacing, hyphens and the supported special characters are retained. The input checks are not legal plate-format verification.

VIN is optional. Standard mode accepts 17 letters/digits excluding I, O and Q. Legacy mode accepts 1–40 letters/digits plus dot, slash and hyphen for older/imported chassis identifiers. Neither mode decodes the vehicle, verifies a VIN check digit, proves ownership or alters fitment. Full identifiers are editable only in the private account form; cards show the last four characters, and the customer-data section omits VINs.

## Structured fitment specifications

Vehicle CSVs optionally accept `generation,trim,engine,body_style,transmission,fuel_type,steering,drivetrain,wheelbase_mm,bed_length_mm`.

- Text specification fields accept up to 80 characters. Engine text is a supplier specification, not an automatically converted numeric value.
- `fuel_type`: blank, `petrol`, `diesel`, `electric`, `hybrid`, `other`.
- `steering`: blank, `LHD`, `RHD`. Do not assume steering position from market.
- `drivetrain`: blank, `FWD`, `RWD`, `AWD`, `4WD`.
- Dimensions: blank or a whole number from 1 to 50000 millimetres. US vehicle options/cards also show inches; stored values and identity never change through display rounding.

The merchant must supply enough detail for its products and verify compatibility. These fields do not infer missing fitment, automatically transfer mappings across markets, or import ACES/PIES data. Legacy vehicle CSVs without a market use GB; use the full fields when updating a definition that already has structured specifications.

Keep the historical constraint names in `db_schema_whitelist.json`. They allow Magento to remove the old plate-only and market-agnostic uniqueness constraints during an upgrade. Regenerating the whitelist must not erase this history.
