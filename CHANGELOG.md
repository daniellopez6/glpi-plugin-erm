# Changelog

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]
### Planned
- Notifications (review due, KRI alert, overdue actions) and PDF report for the Board.

## [2.0.3] - 2026-11-22
### Changed
- Without GLPI GRC Manager, the plugin's own top-level menu is called "Corporate GRC"; its first entry opens the
  dashboard.

## [2.0.2] - 2026-11-22
### Security
- REST API lists of KRIs, measurements, reviews and links (tables without their own entity) only return the rows of
  the risks and events the user can see, also for "see own" profiles.
- Links between risks or loss events and other items: item-level rights follow the risk or the event (view, create,
  update, delete) and only their comment can be changed.
- A KRI or a measurement cannot be moved to another risk or KRI; risks, loss events, AI systems and entity settings
  cannot be moved to an entity the user has no access to.
- Staff risk reports can only be created and reviewed through their workflow (not with a generic API add/update).
- The automatic KRI action never runs a saved search of a disabled or deleted user.
- Security review document: docs/revision-seguridad.md.

### Changed
- Without GLPI GRC Manager, ERM has its own top-level menu "Corporate risks (ERM)" instead of adding its entries to
  "Management".

## [2.0.1] - 2026-11-22
### Security
- The list of editors of an AI impact assessment is stored in a TEXT column and an update that would exceed it is
  refused, so it can never be truncated (which could allow an editor to approve).

## [2.0.0] - 2026-11-22
### Added
- AI governance (ISO/IEC 42001, EU AI Act):
  - AI system inventory: purpose, type, deployment, provider and model, owner, personal and sensitive data, affected
    people, human oversight, AI Act role and class, lifecycle status; documents, notes, history and corporate risks.
  - AI impact assessment (42001 6.1.4 / 8.4, ISO/IEC 42005 criteria): nine dimensions (bias, privacy, transparency,
    security, reliability and hallucinations, human oversight, misuse, societal impact, legal) with score and
    rationale, overall level, mitigations and residual impact, next assessment. Draft, submitted, approved or
    rejected; submitted and approved assessments are read only, and nobody who wrote or edited an assessment may
    approve it (editors are recorded). A corporate risk can be
    created from an approved assessment.
  - Governance gaps: systems in development or production of limited or high AI Act class without a current
    approved assessment, and prohibited practices; AI governance card on the dashboard.
  - "Artificial intelligence" risk category branch (created on update).
  - New right "AI systems and impact assessments" (`plugin_erm_ai`) with "Approve assessments", and new role profile
    "ERM - AI officer"; existing role profiles get the AI right of their role.
- Risk reports from the staff: any user, also from the simplified interface (new menu entry), reports an incident, a
  near miss or a risk; the risk team turns it into a loss event or a corporate risk, or closes it with a comment;
  the reporter follows the status in "My reports". Pending reports are shown on the dashboard.
- Unit tests for the AI impact rules (86 tests).

### Fixed
- Purging a risk or a loss event left its links in the database (and a warning in the GLPI log); purging a linked
  GLPI object (ticket, problem, change, supplier...) now removes its links to risks and loss events.

## [1.9.2] - 2026-11-21
### Security
- The saved search of an automatic KRI is also validated when only the saved search is changed (for example through
  the API).

## [1.9.1] - 2026-11-21
### Security
- An automatic KRI can only use the user's own saved searches (users with the ERM configuration right can use any
  visible one): the automatic action runs the search with the rights of its owner.

## [1.9.0] - 2026-11-21 (not released, superseded by 1.9.1)
### Added
- Formal acceptance with validity: "Acceptance valid until" (12 months by default), set only by the acceptance right;
  extending it records a new acceptance (who and when, never the risk owner). Alerts when it expires within 30 days
  or has expired; "Acceptances to renew" on the dashboard with drill-down. Existing acceptances are migrated.
- Tests of the ISMS controls (GLPI GRC Manager, read only): the risk form summarises the last test of the controls
  linked to the risk or to its linked ISMS risks (effective, partial, ineffective, not tested); daily alert when a
  control failed its last test.
- Automatic KRIs: a KRI can take its value from a GLPI saved search (number of results), measured by the automatic
  action "ermkri" according to its frequency (with the rights of the saved search owner) or on demand.
- Quantitative analysis: expected annual loss according to the assessment and to the loss history, and Monte Carlo
  simulation of the annual loss (median, P90, P99) on the loss events tab; portfolio expected annual loss on the
  dashboard.
- Unit tests for acceptance validity, control evaluation and quantitative analysis (82 tests).

## [1.8.1] - 2026-11-21
### Security
- A manual snapshot only replaces the snapshot of the user's active entities (the automatic action still covers all
  entities).
- Users who only see own risks only see the number of their own risks in the list of snapshots.

## [1.8.0] - 2026-11-21 (not released, superseded by 1.8.1)
### Added
- Board report (ISO 31000 6.7) as PDF: executive summary with the variation against a previous quarter, changes
  (risks that increased, decreased, are new, went outside or came back within the appetite, closed), residual heat
  map, risks outside the appetite, top 10, treatment, losses and the integrated view.
- Quarterly snapshots of the risk profile: automatic action "ermsnapshot" (first run of each quarter) or manual
  snapshot from the report page.
- Integrated risk map: corporate risks together with the ISMS and supplier risks of GLPI GRC Manager by level, the
  high and critical risks of all sources, and a coherence check (corporate risks assessed below their linked
  risks). GRC Manager data is only read, when the plugin is active and the user may see it.
- Unit tests for periods, snapshot comparison and consolidation (77 tests).

## [1.7.1] - 2026-11-21
### Added
- Notice to the risk owner when a treatment action is completed (not when the owners completes it); when it was the
  last open action, it also says whether the risk reached its target. New notification "A treatment action of your
  risk has been completed".

## [1.7.0] - 2026-11-21
### Added
- Ready-to-assign role profiles created at installation: Risk manager, Risk owner, Treatment responsible, Risk
  Committee and Internal audit. They use the standard interface without any inventory right (risk management only)
  plus the minimum GLPI rights each role needs (documents, knowledge base, and tickets/problems/changes read access
  to link incidents for the manager, owners and audit). Each profile is created once, recognised by a marker in its
  comment, and never modified afterwards.

### Changed
- When installed from the command line, texts (taxonomy, profiles, notifications) are created in the default
  language of GLPI.

## [1.6.0] - 2026-11-21
### Added
- Target risk (probability, impact, level and date to reach with the treatment).
- Risk reviews (ISO 31000 6.6): each review keeps a snapshot of the assessment, conclusion, participants (6.2
  consultation) and next review date; reviews cannot be modified. "Evolution of the risk" chart (inherent, residual
  and target over time).
- Treatment effectiveness (6.5): plan progress and residual vs. target (target reached, in progress, plan completed
  without reaching the target, above target without plan), on the treatment tab, the dashboard and the drill-down.
- E-mail notifications (GLPI notifications, "Corporate risk" type):
  - immediate notice when a risk is assigned to its owner and when a treatment action is assigned;
  - daily digest per risk (automatic action "ermalerts"): actions overdue or due within 7 days, review overdue or due
    within 14 days, residual above appetite or tolerance, KRIs in alert or with a pending measurement, treatment
    completed without reaching the target and assessment below the loss history. Each alert is repeated at most
    once a week. Recipients: risk owner, responsible group, action and KRI responsibles (configurable).
- Unit tests for treatment evaluation and alert rules (74 tests).

## [1.5.1] - 2026-11-21
### Security
- The loss history statistics of a risk only use events of the entities visible to the user.

## [1.5.0] - 2026-11-21
### Added
- Loss event database (ISO 31000 6.4.3, Basel / BCRA operational loss data): losses, near misses and unexpected
  gains with occurrence and detection dates, root cause, gross loss, recoveries and net loss; documents, notes and
  history. New right "Loss events and incidents" (`plugin_erm_event`).
- Events are linked with corporate risks and with GLPI tickets, problems and changes; an event can be recorded
  directly from a ticket, problem, change or risk (prefilled).
- Economic impact criteria per entity (minimum net loss of each impact level), inherited by sub-entities.
- "Assessment vs. loss history" on each risk: annual frequency of loss events over the last 3 years, annual
  probability (Poisson) and largest loss, compared with the assessed probability and impact.
- Dashboard card with the risks underestimated according to their history (drill-down) and net losses of the last
  12 months.
- Unit tests for loss statistics (69 tests).

## [1.4.1] - 2026-11-21
### Security
- Treatment actions can no longer be moved to another risk or entity through a crafted request; their entity is
  always taken from the risk, and adding an action to a risk that cannot be viewed is rejected.
- The drill-down list ignores an entity filter for entities the user has no access to.

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
