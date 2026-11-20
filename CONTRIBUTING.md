# Contributing

Thanks for your interest in improving the ERM plugin.

## Workflow
1. Open an issue describing the bug or the feature before large changes.
2. Fork the repository and create a branch from `main` (`fix/...`, `feat/...`).
3. Keep pull requests small and focused; update `CHANGELOG.md` under **[Unreleased]**.

## Coding rules
- PSR-12 (`composer lint`) and PHPStan level 5 (`composer analyse`); both run in CI.
- New PHP classes in `src/` under the `GlpiPlugin\Erm` namespace, with a docblock on every method.
- Database access only through the GLPI DB iterator (`$DB->request()`, `$DB->insert()`, ...). No raw SQL with
  user data.
- Every `front/` or `ajax/` entry point must check rights (`Risk::canView()`, `$item->check()`, ...).
- Templates in Twig (`templates/`); do not use `|raw` on user-provided data.
- User-facing strings in English through `__('...', 'erm')` / `_n(...)`; then run `tools/extract_locales.py`
  and add the Spanish translation in `tools/translations_es.py` before `tools/build_po.py`.
- Keep the plugin working without GLPI GRC Manager (guard every GRC reference with `class_exists`).

## Testing a change
Install the plugin in a GLPI 12 test instance, run `php bin/console cache:clear`, and check the affected screens
with at least two profiles (full access and "own risks only").
