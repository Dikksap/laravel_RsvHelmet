---
kind: external_dependency
name: External Barang API (third-party product catalog service)
slug: external-barang-api
category: external_dependency
category_hints:
    - sdk_real_api
scope:
    - '**'
---

### External Barang API
- Role: Remote product/variant catalog consumed by this app for external item mapping, sync, and BOM building. Addressed via `services.external_barang.base_url` (default `http://host.docker.internal:8000`), timeout via `EXTERNAL_BARANG_API_TIMEOUT`, excluded statuses via `excluded_statuses` (e.g. `REGISTER`), default warehouse via `EXTERNAL_BARANG_DEFAULT_WAREHOUSE_ID`.
- Endpoints used:
  - `GET /api/public/barang` — list barang with pagination params `(page, limit, variantId, status)`; returns `{data, meta}`.
  - `GET /api/public/barang/{kodeBarang}` — single item detail.
  - `GET /api/public/barang/rekap-variant?tanggalAwal&tanggalAkhir` — aggregated variant recap returning `{data, statuses, total}`.
- All calls go through `Illuminate\Http\Client\PendingRequest` with JSON accept header; failures return empty structures rather than throwing, so callers must check for empty `data` arrays.
- Verify exact response shapes against the external service docs before changing consumers.