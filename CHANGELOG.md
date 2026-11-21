# Changelog

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]
### Planned
- Notifications (review due, KRI alert, overdue actions) and PDF report for the Board.

## [1.4.0] - 2026-11-21
### Added
- Dashboard drill-down: every figure (level cards, heat map cells, categories, per entity counters, outside
  appetite, KRIs in alert) opens the list of the risks behind it.
- Action plan register: all treatment actions of the visible risks in the GLPI search engine (filters, export,
  saved searches) with their own form and history; "See all action plans" from the dashboard.
- "My ERM workspace": my pending actions (quick status update), the KRIs I measure (next measurement and quick
  value entry) and the risks I own with their next review.
- Unit tests for drill-down filters and measurement schedule (57 tests).

### Changed
- Treatment actions store the entity of their risk (migrated automatically).

## [1.3.0] - 2026-11-20
### Added
- Multi-company support: "Corporate risks (ERM)" tab on entities with a risk code prefix and appetite/tolerance
  overrides per category; both are inherited by sub-entities.
- Risk codes numbered per entity (`PREFIX-001`), unique within the entity.
- Dashboard "Risks by entity" breakdown when several entities are visible (consultant view), with links to the
  filtered register.
- PHPUnit unit tests (numbering, appetite resolution, scales, validation) run in CI.

## [1.2.0] - 2026-11-20
### Added
- Internationalization: English source strings through gettext (`erm` domain), `locales/erm.pot` template and
  Spanish translations (`es_AR`, `es_ES`). Tools `tools/extract_locales.py` and `tools/build_po.py`.
- Continuous integration (GitHub Actions): PHP syntax, PSR-12 (phpcs), PHPStan level 5 against GLPI 12, locales check.
### Changed
- Default taxonomy is generic (not industry specific) and created in the language of the installing user.
- Impact scale and amounts no longer hard-code a currency; numbers use the user number format.
- Code style aligned with PSR-12; code comments in English.
### Removed
- Legacy `front/category*.php` files: GLPI 12 serves plugin dropdowns through its generic controllers
  (removes the "Unexpected output detected" warnings).
### Fixed
- Tab handlers check the item type before using it (PHPStan findings).

## [1.1.1] - 2026-11-20
### Security
- Input validation for dates, decimals, scale ranges and mandatory fields (risks, actions, KRIs, measurements).
- Linked items must exist and be visible to the current user.
- Table removal on uninstall through the native `DBmysql::dropTable()` API.
- Marketplace auto-install disabled (`plugin_erm_options`).
### Changed
- GPL-3.0-or-later license headers in every source file.

## [1.1.0] - 2026-11-20
### Added
- Role based access model: `plugin_erm` (read all / read own, update / update own), `plugin_erm_treatment`,
  `plugin_erm_config` and `plugin_erm_accept`. Search, dashboard and selectors filtered for "own only" profiles.
- Formal risk acceptance with segregation of duties (the risk owner cannot accept their own risk).

## [1.0.0] - 2026-11-20
### Added
- Enterprise risk register with configurable category tree, 5x5 inherent/residual assessment, economic impact,
  velocity, trend, appetite and tolerance per category.
- Treatment plans, key risk indicators with history, related items (GLPI and GRC Manager objects).
- Dashboard with inherent/residual heat maps, exposure per category, out-of-appetite risks, overdue actions and KRI alerts.
