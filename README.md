# CEPiK tooling

## Vehicle Excel export

1. Przeprowadź pełny cykl raportu opisany w `REPORTING_PLAN.md` aż do statusu `completed`/`ready`.
2. Upewnij się, że kolejka pojazdów (`app:reports:vehicles:queue` → `app:reports:vehicles:process-queue`) zakończyła się powodzeniem – rekordy w `report_vehicles` powinny mieć status `succeeded`.
3. Uruchom eksport do Excela:

```bash
php bin/console app:reports:vehicles:export-excel <runId> [--output=var/exports/custom.xlsx]
```

Domyślnie plik zostanie zapisany w `var/exports/report-run-<runId>-vehicles.xlsx`. Arkusz zawiera identyfikator pojazdu oraz pełen zestaw atrybutów zwróconych z CEPiK.
