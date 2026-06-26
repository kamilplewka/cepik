# Plan raportowania CEPiK

- Endpoint: `/pojazdy`
- Cel przykładowego raportu: aktywne pojazdy Toyota Celica z lat 1990-1993

## Filtry dla pojedynczego zapytania

- `filter[marka]=TOYOTA`
- `filter[model]=CELICA`
- `filter[rok-produkcji]` -> cztery wartości: 1990, 1991, 1992, 1993
- `tylko-zarejestrowane=true`

## Wymagane parametry

- `wojewodztwo` (16 kodów TERYT)
- `data-od` i `data-do` dla daty pierwszej rejestracji
- domyślnie `typ-daty=1`
- maksymalny zakres jednego zapytania: 2 lata

## Strategia budowania zapytań

- Pokrycie okien dat od `1990-01-01` do dziś w przedziałach dwuletnich.
- Dla przykładu: `1990-1991`, `1992-1993`, ..., aż do bieżącego okresu.
- 18 okien dat x 4 lata produkcji x 16 województw = 1152 wywołania API.
- Pole `meta.count` z każdej odpowiedzi służy do końcowej agregacji.

## Szkic schematu bazy danych

### `reports`

| Kolumna | Typ | Opis |
| --- | --- | --- |
| `id` | UUID/PK | identyfikator raportu |
| `code` | string | unikalny krótki kod, np. `toyota-celica-active` |
| `name` | string | nazwa czytelna dla użytkownika |
| `description` | text | opis definicji raportu |
| `config_schema` | jsonb | schemat oczekiwanego payloadu wejściowego |
| `created_at` | datetime | data utworzenia |
| `updated_at` | datetime | data modyfikacji |

### `report_runs`

Przechowuje pojedyncze uruchomienia raportu z konkretnym zestawem parametrów.

| Kolumna | Typ | Opis |
| --- | --- | --- |
| `id` | UUID/PK | identyfikator uruchomienia |
| `report_id` | FK -> `reports.id` | powiązany raport |
| `input_payload` | jsonb | pełen zestaw filtrów od użytkownika |
| `status` | string | `pending`, `building_queries`, `queued`, `fetching`, `aggregating`, `completed`, `failed` |
| `status_message` | text | dodatkowy opis bieżącego etapu |
| `queued_at` | datetime | moment gotowości do pobierania danych |
| `started_at` | datetime | pierwsze podjęcie przez worker |
| `finished_at` | datetime | moment zakończenia |
| `error_payload` | jsonb | ostatni błąd krytyczny |
| `created_at` | datetime | data utworzenia |

### `report_queries`

Lista zapytań API wygenerowanych dla danego uruchomienia raportu.

| Kolumna | Typ | Opis |
| --- | --- | --- |
| `id` | UUID/PK | identyfikator zapytania |
| `report_run_id` | FK -> `report_runs.id` | powiązane uruchomienie |
| `sequence` | integer | deterministyczna kolejność wykonania |
| `request_params` | jsonb | kanoniczny zestaw parametrów żądania |
| `status` | string | `pending`, `queued`, `in_progress`, `succeeded`, `failed`, `retrying`, `skipped` |
| `attempts` | integer | liczba wykonanych prób |
| `last_attempt_at` | datetime | czas ostatniej próby |
| `response_payload` | jsonb | surowa odpowiedź JSON przy sukcesie |
| `aggregated_count` | integer | kopia `meta.count` dla wygody |
| `error_payload` | jsonb | ostatni zapisany błąd |
| `created_at` | datetime | data utworzenia |
| `updated_at` | datetime | data modyfikacji |

### `report_results`

Końcowe wyniki zagregowane dla danego uruchomienia raportu.

| Kolumna | Typ | Opis |
| --- | --- | --- |
| `id` | UUID/PK | identyfikator wyniku |
| `report_run_id` | FK -> `report_runs.id` | powiązane uruchomienie |
| `status` | string | `pending`, `calculating`, `ready`, `failed` |
| `result_payload` | jsonb | finalne metryki i podsumowania |
| `created_at` | datetime | data utworzenia |
| `updated_at` | datetime | data modyfikacji |

### `api_error_logs`

| Kolumna | Typ | Opis |
| --- | --- | --- |
| `id` | UUID/PK | identyfikator wpisu |
| `report_run_id` | FK nullable | powiązanie z uruchomieniem raportu |
| `report_query_id` | FK nullable | powiązanie z zapytaniem |
| `endpoint` | string | np. `/pojazdy` |
| `request_params` | jsonb | parametry wysłane do API |
| `response_status` | integer | kod HTTP |
| `error_body` | jsonb | treść błędu lub odpowiedzi |
| `error_class` | string | rodzaj błędu: transport, walidacja itp. |
| `created_at` | datetime | data utworzenia |

## Przepływ przetwarzania

1. Użytkownik lub API zapisuje payload wejściowy, a system tworzy `report_run` ze statusem `pending`.
2. Proces generowania buduje pełną siatkę parametrów, zapisuje rekordy w `report_queries` i ustawia status `queued`.
3. Dispatcher lub cron pobiera oczekujące zapytania w małych partiach i oznacza je jako gotowe do wykonania.
4. Worker wykonuje zapytania do API, zapisuje odpowiedzi albo błędy i zarządza retry.
5. Gdy wszystkie zapytania zakończą się sukcesem lub wyczerpią limit prób, system agreguje dane do `report_results`.
6. Każdy wyjątek lub błąd integracji trafia również do `api_error_logs`.

## Sygnały statusów

### Status uruchomienia raportu (`report_runs.status`)

- `pending` -> `building_queries` -> `queued` -> `fetching` -> `aggregating` -> `completed` / `failed`

### Status pojedynczego zapytania (`report_queries.status`)

- `pending` -> `queued` -> `in_progress` -> `succeeded`
- `pending` -> `queued` -> `in_progress` -> `retrying` -> `failed`
- `pending` -> `queued` -> `skipped`

### Status wyniku (`report_results.status`)

- `pending` -> `calculating` -> `ready` / `failed`

## Uwagi operacyjne

- Worker powinien ograniczać równoległość, żeby nie przekroczyć limitów API CEPiK.
- Należy obsłużyć paginację przez `limit` i `page`, jeśli `meta.count > limit`.
- Pola `request_params` i `response_payload` warto przechowywać w JSON dla audytu i łatwego replayu.
- Agregacja powinna weryfikować spójność `meta.count` i pełności paginacji.
- Log błędów można później zastąpić zewnętrznym monitoringiem bez przebudowy modelu domenowego.

## Elementy runtime

Najważniejsze komendy CLI dostępne w `app/bin/console`:

- `app:reports:create-run <code> <payload>` - tworzy rekordy w `reports` i `report_runs`
- `app:reports:generate-queries <runId>` - rozwija payload do listy `report_queries`
- `app:reports:process-queue [--limit=N] [--ignore-attempts]` - przetwarza kolejkę zapytań, zapisuje odpowiedzi i błędy
- `app:reports:aggregate-run <runId>` - agreguje udane odpowiedzi do wyniku końcowego
- `app:reports:vehicles:queue <runId>` - wyciąga identyfikatory pojazdów i odkłada je do kolejki szczegółów
- `app:reports:vehicles:process-queue [--limit=N] [--ignore-attempts]` - pobiera szczegóły pojedynczych pojazdów z CEPiK
- `app:reports:vehicles:export-excel <runId>` - eksportuje dane pojazdów do Excela

## Wniosek projektowy

Ten model nadaje się do dalszego rozwijania jako aplikacja raportowa, panel administracyjny albo usługa backendowa pod kolejny system. Najmocniejsze strony projektu to rozbicie procesu na etapy, utrwalanie stanu w bazie i możliwość wznawiania pracy po błędach integracji.
