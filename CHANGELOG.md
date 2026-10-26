# Changelog

All Zana packages share one version number and are released together. A release that did
not change this package is not listed.

## 2.3.0 — unreleased

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
