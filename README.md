# Lystrup Plugin

Version 0.1.0 — første udviklingsversion.

## Funktioner
- Selvregistrering via shortcode `[lystrup_registration]`
- Nye brugere får rollen `lystrup_pending` og kan ikke logge ind før godkendelse
- Godkend/afvis under **Brugere → Borgeransøgninger** (kun administratorer)
- Borgerportal: `[lystrup_portal area="citizen"]`
- Bestyrelsesportal: `[lystrup_portal area="board"]`
- Login: `[lystrup_login]`

**IKKE PRODUKTIONSKLAR:** Ingen beskyttet dokumentlagring endnu. Kortkoder beskytter kun deres eget output, ikke øvrigt sideindhold. Sider med privat information skal først publiceres, når fuld server-side adgangskontrol, tests og sikkerhedsgennemgang er gennemført. Opsæt rate limiting/WAF, HTTPS, backups og SMTP før offentlig registrering.
