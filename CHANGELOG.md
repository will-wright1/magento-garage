# Changelog

## 0.1.0-beta.2 — 2026-11-10

- Customer Garages admin search with website/market filters and SQL pagination.
- Customer record garage tab and customer-scoped vehicle editing, catalogue linking, deletion and preference controls.
- Separate view/manage role permissions, masked summary VINs, protected POST actions and operational change logging.
- Customer store-view configuration for admin UK/US defaults and validation, with website-default fallback.
- Repeatable admin runtime checks for ownership, role separation, data exposure, filtering, pagination and CSRF/method guards.

Validated on the disposable Magento 2.4.9 installation with 45 standalone PHP tests, 13 JavaScript tests, 20 admin runtime checks, 17 market runtime checks and seven original runtime checks. Magento XML schema validation and DI compilation pass. Browser checks cover customer garage creation/editing, duplicate errors, cancellation of deletion and the customer-record tab. Merchant-specific acceptance testing remains required; see [the validation record](docs/testing.md).

## 0.1.0-beta.1 — 2026-11-09

Initial beta prepared for staging evaluation on Magento 2.4 with Luma-compatible storefronts.

- Customer garages with multiple vehicles, account-scoped editing, selection and default preferences.
- Manual UK/US plate entry, issuing jurisdictions, optional VIN/legacy chassis numbers and plate-free vehicles.
- Separate vehicle specification markets with UK make-first and US year-first catalogue selection.
- Merchant-managed canonical vehicle definitions and structured fitment specifications.
- Specific product mappings and GB, US or explicitly shared universal fitment rules.
- Dedicated vehicle shopping results with native product pricing, sorting and pagination.
- Transactional CSV imports, scoped admin permissions and per-store garage configuration.
- British/American wording and configurable registration badge styles.
- Upgrade preservation checks, standalone PHP/JavaScript tests and GitHub Actions validation.

### Validation and release limits

The recorded Magento 2.4.9 checks cover database upgrades, runtime services, rendered templates, selector responses and DI compilation. Interactive browser validation of the latest market changes, full Magento PHPUnit suites, production caching/performance and merchant-specific themes remain pending. See [the validation record](docs/testing.md).

Vehicle and product compatibility data must be supplied and verified by the merchant. No external registration/VIN lookup or ACES/PIES integration is included.
