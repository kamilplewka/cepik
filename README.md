# CEPiK Reporter

Projekt backendowy w PHP 8.2+ i Symfony 7 służący do budowania raportów na bazie danych CEPiK. Aplikacja generuje zestaw zapytań do API, przetwarza je partiami, zapisuje wyniki w PostgreSQL, agreguje rezultat końcowy i pozwala wyeksportować szczegóły pojazdów do Excela.

Repozytorium pokazuje przede wszystkim:
- projektowanie procesów wsadowych,
- integrację z zewnętrznym API,
- model domenowy oparty o Doctrine ORM,
- pracę z kolejką zadań i retry,
- generowanie raportów i eksport danych,
- dodatkową warstwę NLP do zamiany opisu naturalnego na JSON wejściowy raportu.

## Stack

- PHP 8.2+
- Symfony 7
- Doctrine ORM + Doctrine Migrations
- PostgreSQL
- Docker / Docker Compose
- PhpSpreadsheet
- OpenAI API do planowania payloadu raportu

## Co robi aplikacja

Główny scenariusz wygląda tak:

1. Tworzony jest `report_run` z wejściowym payloadem raportu.
2. System generuje pełną siatkę zapytań do API CEPiK i zapisuje ją jako `report_queries`.
3. Worker przetwarza zapytania partiami, obsługuje błędy oraz ponowienia.
4. Wyniki są agregowane do `report_results`.
5. Identyfikatory pojazdów mogą zostać dodane do osobnej kolejki szczegółów.
6. Szczegóły pojazdów można wyeksportować do pliku Excel.

## Uruchomienie lokalne

```bash
docker compose up --build -d
docker compose exec app bash
cd /var/www/html
composer install
php bin/console doctrine:migrations:migrate
```

Aplikacja będzie dostępna pod adresem `http://localhost:8080`.

## Najważniejsze komendy

### Tworzenie i przetwarzanie raportu

```bash
php bin/console app:reports:create-run <code> <payload-json>
php bin/console app:reports:generate-queries <runId>
php bin/console app:reports:process-queue --limit=50
php bin/console app:reports:aggregate-run <runId>
```

Szczegółowy przebieg jest opisany w pliku `REPORTING_PLAN.md`.

### Kolejka szczegółów pojazdów i eksport do Excela

1. Przeprowadź pełny cykl raportu aż do statusu `completed` / `ready`.
2. Dodaj pojazdy do kolejki:

```bash
php bin/console app:reports:vehicles:queue <runId>
```

3. Przetwórz kolejkę szczegółów:

```bash
php bin/console app:reports:vehicles:process-queue --limit=50
```

4. Wygeneruj plik Excel:

```bash
php bin/console app:reports:vehicles:export-excel <runId> [--output=var/exports/custom.xlsx]
```

Domyślna ścieżka zapisu:

```bash
var/exports/report-run-<runId>-vehicles.xlsx
```

## NLP: opis naturalny -> JSON raportu

Projekt zawiera dodatkowy moduł, który zamienia pytanie w języku naturalnym na JSON zgodny z wejściowym schematem raportu.

Ustaw w `app/.env`:

```bash
OPENAI_API_KEY=
OPENAI_MODEL=
```

### Wersja CLI

```bash
php bin/console app:reports:nlp:plan "Znajdź wszystkie Mazdy 3 po 2018 roku w Małopolsce"
```

### Wersja HTTP

```http
POST /api/reports/nlp/plan
Content-Type: application/json

{
  "question": "Ile jest BMW X5 w województwie łódzkim?"
}
```

Odpowiedź zawiera:
- `question`
- `needs_clarification`
- `clarifying_questions`
- `report_payload`
