# Security policy

## Supported versions
Only the latest release receives security fixes.

## Reporting a vulnerability
Please **do not open a public issue**. Report it privately through GitHub
("Security" tab → "Report a vulnerability") with the affected version, steps to reproduce and impact.
You will get an answer within 7 days; a fix and an advisory will be published once a patched release is available.

## Security design
- Rights are enforced server-side on every entry point (`front/`), in `can*Item()` methods and in search
  (`plugin_erm_addDefaultWhere`). The REST API uses the same checks.
- Formal risk acceptance enforces segregation of duties (owner cannot accept; acceptor and date are recorded
  by the server, never taken from the request).
- Input validation for dates, decimals, scales and linked items; queries through the GLPI DB iterator.
