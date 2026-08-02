# Vendor Marketplace - Refactor Report

## What changed
- Added a lightweight Application/Kernel/Container bootstrap flow.
- Introduced Config repository files under app/Config.
- Added DI container support with bind/singleton/instance/has/make/forget.
- Added compatibility helpers for config() and App boot flow.
- Added initial DTO, validator, exception, action, and contract scaffolding.

## Fixed issues
- Prevented the plugin from executing heavy boot logic directly from the main file.
- Stabilized the plugin bootstrap for modern PHP and WordPress loading order.
- Preserved legacy helper compatibility for old templates and modules.

## Architectural improvements
- Moved toward PSR-4 structure under app/.
- Separated configuration, contracts, DTOs, validators, exceptions, and actions.
- Kept existing module and repository files intact for backward compatibility.
