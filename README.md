# ERM - Enterprise Risk Management for GLPI

![logo](erm.png) Enterprise risk register for **GLPI 12**, based on **ISO 31000** and **COSO ERM**.
Manage strategic, financial, operational, compliance, fraud, reputational and ESG risks next to your IT and
ISMS data, with optional integration with [GLPI GRC Manager](https://github.com/parime/glpi-iso27001-management).

> Status: **beta**. User interface strings are currently in Spanish; English base strings with gettext
> translations are planned (see [CHANGELOG](CHANGELOG.md)).

![Dashboard](docs/screenshots/dashboard.png)

## Features

- **Configurable risk taxonomy** (category tree) with **risk appetite, tolerance** and appetite statement per
  category, inherited by sub-categories.
- **Risk register**: causes, consequences, owner and responsible group, **5x5 inherent and residual assessment**,
  existing controls and their effectiveness, **economic impact** (min/max), velocity and trend.
- Levels: 1-4 low, 5-9 medium, 10-16 high, 20-25 critical; automatic position against appetite/tolerance.
- **Treatment plans** (owner, due date, cost, status, overdue highlight).
- **Key Risk Indicators (KRI)** with warning/critical thresholds, direction and history chart.
- **Formal risk acceptance with segregation of duties**: only profiles with the acceptance right can accept,
  never the risk owner; who and when are recorded automatically.
- **Related items**: link corporate risks with GRC Manager risks, business processes, controls, objectives and
  obligations, and with suppliers, contracts, appliances, computers, projects, groups and changes. A reverse tab
  "Corporate risks" is added to those objects.
- **Dashboard**: inherent and residual heat maps, exposure per category, risks above appetite, top residual
  risks, overdue actions and KRIs in alert.
- **Role based access** (per profile and entity): see all / see own risks, update all / update own, treatment,
  taxonomy & appetite, acceptance. Search, dashboard and selectors are filtered accordingly.
- Native GLPI features: entities, search engine, documents, notes, history, REST API (`GlpiPlugin\Erm\*`).

## Requirements

| Component | Version |
|---|---|
| GLPI | 12.0.x |
| PHP | 8.2 or later |
| GLPI GRC Manager | optional (menu and object integration when active) |

No third-party runtime libraries.

## Installation

```bash
cd /path/to/glpi/plugins
tar xjf glpi-erm-x.y.z.tar.bz2          # creates the "erm" directory
php ../bin/console plugin:install -u glpi erm
php ../bin/console plugin:activate erm
php ../bin/console cache:clear
```

Logged-in users must log in again (or switch profile) to see the new menu entries.
See the [administration guide](docs/guia-administracion.md) and the [user manual](docs/manual-usuario.md)
(Spanish).

## Development

```bash
composer install            # dev tools: GLPI coding standard (phpcs) and PHPStan
composer lint
composer analyse            # run from a plugin checked out inside glpi/plugins
```

Contributions are welcome, see [CONTRIBUTING](CONTRIBUTING.md). Security issues: see [SECURITY](SECURITY.md).

## License

[GPL-3.0-or-later](LICENSE). Copyright (C) 2026 daniellopez6.

---

## Resumen en español

Registro de riesgos corporativos para GLPI 12 (ISO 31000 / COSO ERM): taxonomía configurable con apetito y
tolerancia, evaluación inherente y residual 5×5 con impacto económico, planes de tratamiento, indicadores clave
de riesgo, aceptación formal con segregación de funciones, permisos por rol (todos / propios) y tablero con
mapas de calor. Integración opcional con GLPI GRC Manager. Documentación:
[manual de usuario](docs/manual-usuario.md) y [guía de administración](docs/guia-administracion.md).
