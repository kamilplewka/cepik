# CEPiK Reporting Plan

- Endpoint: `/pojazdy`
- Target: Active Toyota Celica vehicles produced in 1990-1993

## Filters per Request

- `filter[marka]=TOYOTA`
- `filter[model]=CELICA`
- `filter[rok-produkcji]` -> four values: 1990, 1991, 1992, 1993
- `tylko-zarejestrowane=true`

## Required Parameters

- `wojewodztwo` (16 TERYT codes)
- `data-od` & `data-do` for first registration (default `typ-daty=1`)
  - Max range: 2 years per query

## Query Strategy

- Cover registration windows starting 1990-01-01 up to present in 2-year slices (1990-1991, 1992-1993, …, 2024)
- 18 date windows × 4 model years × 16 regions = 1152 API calls
- Each response’s `meta.count` provides counts to aggregate

## Database Schema Draft

### `reports`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | UUID/PK | |
| `code` | string | Unique short name (e.g. `toyota-celica-active`) |
| `name` | string | Human readable title |
| `description` | text | How the report is defined |
| `config_schema` | jsonb | JSON schema describing expected input payload |
| `created_at` | datetime | |
| `updated_at` | datetime | |

### `report_runs`

Stores each instance of input parameters to process.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | UUID/PK | |
| `report_id` | FK → `reports.id` | |
| `input_payload` | jsonb | Full set of filters supplied by the user |
| `status` | string | `pending`, `building_queries`, `queued`, `fetching`, `aggregating`, `completed`, `failed` |
| `status_message` | text | Optional context for the current status |
| `queued_at` | datetime | When the run became ready for fetching |
| `started_at` | datetime | First worker pickup |
| `finished_at` | datetime | Finalization timestamp |
| `error_payload` | jsonb | Last fatal error (if any) |
| `created_at` | datetime | |

### `report_queries`

Generated list of API calls for a run.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | UUID/PK | |
| `report_run_id` | FK → `report_runs.id` | |
| `sequence` | integer | Deterministic ordering (e.g. województwo × rok × okno) |
| `request_params` | jsonb | Canonical query parameters (województwo, filters, data-od/data-do, limit, page, etc.) |
| `status` | string | `pending`, `queued`, `in_progress`, `succeeded`, `failed`, `retrying`, `skipped` |
| `attempts` | integer | Number of tries so far |
| `last_attempt_at` | datetime | |
| `response_payload` | jsonb | Raw JSON (meta + data) when successful |
| `aggregated_count` | integer | Convenience copy of `meta.count` |
| `error_payload` | jsonb | Last error returned by the API |
| `created_at` | datetime | |
| `updated_at` | datetime | |

### `report_results`

Aggregated outputs per run.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | UUID/PK | |
| `report_run_id` | FK → `report_runs.id` | |
| `status` | string | `pending`, `calculating`, `ready`, `failed` |
| `result_payload` | jsonb | Final metrics (counts, breakdowns) |
| `created_at` | datetime | |
| `updated_at` | datetime | |

### `api_error_logs`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | UUID/PK | |
| `report_run_id` | FK nullable | Link to run if known |
| `report_query_id` | FK nullable | Link to query if known |
| `endpoint` | string | e.g. `/pojazdy` |
| `request_params` | jsonb | Parameters sent |
| `response_status` | integer | HTTP code |
| `error_body` | jsonb | Returned payload/error message |
| `error_class` | string | Transport vs validation etc. |
| `created_at` | datetime | |

## Processing Workflow (High Level)

1. **Input capture** – user/API posts filters; new `report_run` created with `status=pending`.
2. **Query generation** – background job builds full parameter grid, persists rows in `report_queries`, updates run to `queued`.
3. **Fetch dispatcher** – scheduler/cron enqueues pending queries in small batches, marking them `queued`.
4. **Fetcher workers** – dedicated process (loop or queue worker) executes API calls, updates each `report_query` to `succeeded` with response JSON or `failed` + increments `attempts`. Retries move status to `retrying`.
5. **Aggregation** – once all queries succeed (or hit retry budget), aggregate counts into `report_results`, set `report_run.status` to `completed` or `failed`.
6. **Logging** – any API exception persists a row in `api_error_logs` for later inspection.

### Status Signals

- **Input (`report_runs.status`)**: `pending` → `building_queries` → `queued` → `fetching` → `aggregating` → `completed` / `failed`.
- **Query (`report_queries.status`)**: `pending` → `queued` → `in_progress` → `succeeded` / `retrying` → `failed` (after max attempts) / `skipped` (manually ignored).
- **Result (`report_results.status`)**: `pending` → `calculating` → `ready` / `failed`.

### Operational Notes

- Cron or worker loop should cap concurrent fetches to respect API rate limits; use `limit`/`page` to handle pagination when `meta.count > limit`.
- `request_params`/`response_payload` fields stay JSON for auditability and easy replay.
- Aggregate job should double-check `meta.count` vs pagination to ensure completeness.
- Error logger can later be replaced by observability pipeline without schema changes.

## Runtime Components

- CLI helpers (all in `/app/bin/console`):
  - `app:reports:create-run <code> <payload>` – registers a new entry in `reports`/`report_runs`.
  - `app:reports:generate-queries <runId>` – ekspanduje run do rekordów `report_queries` i pokazuje pasek postępu z bieżącym regionem/oknem.
  - `app:reports:process-queue [--limit=N]` – konsumuje zapytania, pokazuje kolorowe statusy i pasek postępu, zapisuje wyniki/błędy.
  - `app:reports:aggregate-run <runId>` – agreguje udane zapytania z wizualnym podglądem postępu.
- Services under `App\Service\Report\*` implement the workflow for injection into other entry points (e.g. HTTP controllers, workers).
