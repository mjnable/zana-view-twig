# Changelog

All Zana packages share one version number and are released together. A release that did
not change this package is not listed.

## 2.35.0 — 2026-11-21

### Security

- Compiled templates are signed when `security.secret` is set (`SignedTwigCache`): they are PHP files Twig includes,
  so code written to the cache directory ran on the next page that used the template. Each file carries an
  HMAC-SHA256 of its contents and of its path in the directory; a file without it, or with another, is not loaded, and
  Twig compiles the template again from its source. The bytes checked are evaluated, unless
  `app.include_compiled_caches` (`ApplicationBuilder::includeCompiledCaches()`) asks to include them, for OPcache.
- `cache:clear` empties the signed cache's directory as before.

## 2.4.0 — 2026-10-26

### Changed

- Factory registrations declare the services they resolve with `uses()`, so the container checks
  their lifetimes at startup (FE-40).
- Requires `mj-zana/view` ^2.4.

## 2.3.0 — 2026-10-26

### Changed

- Tests and tooling (`tests/`, `phpunit.xml.dist`, `phpstan.neon.dist`) are no longer in Composer
  archives, so they stay out of production `vendor/` folders (FE-17).

## 2.2.0 — 2026-10-26

### Added

- `TwigCache`: clears and warms the compiled templates for `cache:clear` and `cache:warm`
  (FE-18).

## 2.1.0 — 2026-10-26

First release. Twig as the engine of mj-zana/view: the same template folders and names,
view helpers as Twig functions, autoescape, and the compiled-template cache in production.
