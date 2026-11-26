---
kind: design
name: Aggregate batch pull into one stock-in movement per RSV item via `syncBatchOutput`
source: session
category: adr
---

# Aggregate batch pull into one stock-in movement per RSV item via `syncBatchOutput`

_Source: coding plans from commit period 2370198 → f95361f — records intent at planning time; the implementation may lag or differ._

**Status:** accepted

## Context
Operators need to choose an external batch code, unit status, and quantity cap when pulling stock, and want all eligible units from a batch recorded as one aggregated stock-in movement per RSV item rather than one movement per individual unit.

## Decision drivers
- operator control over batch/status/quantity
- audit simplicity (one movement per RSV item)
- parity with existing `syncBatchOutput` flow

## Considered options
- **Extend the existing modal to call `syncBatchOutput` with optional `max_units` cap** — pros: Reuses proven aggregation logic; keeps console command's plain per-unit `sync()` untouched; live preview replaces dry-run UX
- **Keep per-unit movements and only change the UI controls** — pros: Less service-side change; cons: Violates operator expectation of one movement per RSV item; creates noisy audit trail

## Decision
Rework the 'Sinkronkan Stok Sekarang' modal to require `production_batch_id`, `external_batch_code`, and `external_status`, add an optional `max_units` cap, and route the action through `syncBatchOutput()` which groups eligible units per variant and trims groups to respect the cap before creating one aggregated movement per RSV item.

## Consequences
The page modal now drives batch-aggregated pulls while the console `sync:external-barang` command retains the original per-unit behavior. A `ValidationException` (e.g., no eligible units after capping) surfaces as a danger notification. Live preview mirrors `InventoryMovementResource::externalBatchPreview()` for total eligible units, per-item groups, and unmapped-variant warnings.