# Changelog

All notable changes to this project will be documented in this file.

## [v2.1.0] - 2026-03-06

### Added

- Shell operator controls with granular fluent methods to selectively allow blocked shell operators:
  - `allowPipes()` - Allow pipe operators (`|`) for piping output between commands
  - `allowRedirection()` - Allow redirection operators (`>`, `<`, `>>`, `<<`) for file I/O
  - `allowChaining()` - Allow chaining operators (`;`, `&&`, `||`, `&`) for running multiple commands
  - `allowExpansion()` - Allow expansion operators (`$`, `` ` ``, `$()`, `${}`) for variable/command substitution
  - `allowAllShellOperators()` - Global toggle to allow all operator groups at once
- Shell operator flags supported across all layers: Schema fluent API, Livewire component, and CommandSanitizer
- `allowChaining` and `allowExpansion` params added to `ValidCommand` attribute for consistency
- Documentation for shell operator controls with risk levels and usage examples

## [v2.0.0] - 2026-03-04

### Changed

- Upgraded to v2.x targeting Filament 5, Laravel 12, Livewire 4
