# Changelog

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
