# Changelog

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]
### Planned
- English base strings with gettext translations (`locales/`), Spanish (es_AR) translation.
- Per-entity risk numbering, per-entity appetite and consolidated multi-entity dashboard.
- Notifications (review due, KRI alert, overdue actions) and PDF report for the Board.

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
