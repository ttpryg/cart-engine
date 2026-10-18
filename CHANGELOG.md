# Changelog

All notable changes to `ttpryg/cart-engine` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-10-18

### Added

- Root `ExceptionInterface` marker interface (`Ttpryg\CartEngine\Exceptions\ExceptionInterface`)
  that extends `Throwable` and is implemented by `CartEngineException` (and, through
  inheritance, all specific cart exceptions), so consumers can catch all
  library-specific errors with a single type.
- `.editorconfig` for consistent editor settings across the project.

### Changed

- Added `declare(strict_types=1)` to all source files that were missing it.

## [1.1.0] - 2026-10-16

### Added

- `CartCheckedOutEvent` and idempotent `checkout()` that marks the cart `completed`,
  persists it (never deletes), returns a snapshot, and dispatches the event once.

### Changed

- Improved cart status management and refined the storage mechanisms
  (`MemoryCartStorage`, `SessionCartStorage`, `FileCartStorage`, `PdoCartStorage`).

## [1.0.0] - 2026-09-17

### Added

- Initial release: framework-agnostic, item-agnostic (polymorphic) cart engine with
  support for physical products, digital items, bookings, and services.
- Decimal quantities, cart/item-level conditions (discounts, taxes, shipping fees,
  deposits), and a totals engine that computes taxes after discounts.
- Domain events: `ItemAddedToCartEvent`, `ItemRemovedFromCartEvent`, `CartClearedEvent`,
  and `ConditionAppliedEvent`.
- Pluggable storage drivers and optional PDO schema with `DatabaseMigrator`.

[Unreleased]: https://github.com/ttpryg/cart-engine/compare/1.1.0...HEAD
[1.1.0]: https://github.com/ttpryg/cart-engine/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/ttpryg/cart-engine/releases/tag/1.0.0
