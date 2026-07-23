# Security Policy

## Supported versions

| Version | Supported |
| --- | --- |
| 1.0.x | Yes |

## Reporting a vulnerability

**Please do not open a public issue for a security problem.**

Report it privately to the maintainer at `abdelwahied.fx@gmail.com`, with:

- what the problem is, and what an attacker gains from it;
- the exact input that triggers it, if there is one;
- the module and Drupal core versions you saw it on.

You can expect an acknowledgement within a few days, and an assessment of
whether it is exploitable and how severe it is. If a fix is needed, a release
will follow and the report will be credited unless you prefer otherwise.

If the module is installed on a Drupal.org site and the issue affects Drupal
core rather than this module, follow
[Drupal's own security process](https://www.drupal.org/drupal-security-team/security-team-procedures)
instead.

## What this module does and does not protect

It is worth being precise, because the difference matters when assessing a
report.

**What it guarantees.** A value that passes validation is a *well-formed* Saudi
identification number: exactly ten ASCII digits, a leading digit of 1 or 2, and
a correct check digit.

**What it does not guarantee.** That the number belongs to a real person, that
the person exists, or that the person submitting it is the person it belongs to.
The module never contacts a registry or any government service. Treating a
passing number as proof of identity is a misuse of it, not a vulnerability in it.

## Input handling

These behaviours are deliberate. A change to any of them would be a security
change, not a cosmetic one:

- **ASCII digits only.** Arabic-Indic digits (`١٢٣٤٥٦٧٨٩٠`), zero-width
  characters and non-breaking spaces are rejected rather than normalised into
  ASCII. A value that renders as a valid number but carries invisible characters
  is exactly what should not reach storage or a downstream system.
- **Surrounding whitespace is trimmed; inner whitespace is not.** Leading and
  trailing spaces are a copy-and-paste artefact. A space inside the number means
  the value is wrong.
- **No output is produced from input.** The validator returns a verdict, never
  an echo of what was submitted, so it is not itself an XSS surface. The value on
  a dispatched event is the raw submission and must be escaped by any subscriber
  that renders it.
- **No settings weaken a rule.** There is no toggle that disables the checksum
  or the leading-digit test. If you find a configuration that lets an invalid
  number through, that is a bug — please report it.

## Denial of service

Validation is O(n) over ten characters, allocates nothing meaningful, and
performs no I/O: no database query, no HTTP request, no filesystem access, no
cache. It is not a plausible amplification target. Rate limiting the *form* that
calls it remains the host site's responsibility.
