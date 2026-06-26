<?php

namespace App\Service\Report;

use App\Entity\ReportRun;
use App\Entity\ReportVehicle;
use App\Enum\ReportResultStatus;
use App\Enum\ReportRunStatus;
use App\Repository\ReportVehicleRepository;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class ReportVehicleExporter
{
    public function __construct(private readonly ReportVehicleRepository $vehicleRepository)
    {
    }

    /**
     * @return array{path:string, rows:int, columns:int}
     */
    public function export(ReportRun $run, string $targetPath): array
    {
        $this->assertRunReady($run);
        $vehicles = $this->vehicleRepository->findSucceededByRun($run);

        if ($vehicles === []) {
            throw new InvalidArgumentException('No successful vehicle details were found for the provided run. Ensure the vehicle queue has been processed.');
        }

        $vehicleRows = [];
        $attributeIndex = [];

        foreach ($vehicles as $vehicle) {
            $attributes = $this->extractAttributes($vehicle);
            $vehicleRows[] = [
                'vehicle_id' => $vehicle->getVehicleId(),
                'attributes' => $attributes,
            ];

            foreach ($attributes as $key => $_) {
                $attributeIndex[$key] = true;
            }
        }

        $attributeColumns = array_keys($attributeIndex);
        sort($attributeColumns);

        $columns = array_merge(['vehicle_id'], $attributeColumns);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(sprintf('Run %d vehicles', $run->getId()));
        $sheet->fromArray($columns, null, 'A1');

        $row = 2;

        foreach ($vehicleRows as $vehicleRow) {
            $rowValues = [];

            foreach ($columns as $column) {
                if ($column === 'vehicle_id') {
                    $rowValues[] = $vehicleRow['vehicle_id'];
                    continue;
                }

                $value = $vehicleRow['attributes'][$column] ?? null;
                $rowValues[] = $this->normalizeValue($value);
            }

            $sheet->fromArray($rowValues, null, sprintf('A%d', $row));
            ++$row;
        }

        foreach (range(1, \count($columns)) as $columnIndex) {
            $sheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
        }

        $this->ensureDirectory(dirname($targetPath));

        $writer = new Xlsx($spreadsheet);
        $writer->save($targetPath);
        $spreadsheet->disconnectWorksheets();

        return [
            'path' => $targetPath,
            'rows' => \count($vehicleRows),
            'columns' => \count($columns),
        ];
    }

    private function assertRunReady(ReportRun $run): void
    {
        if ($run->getStatus() !== ReportRunStatus::Completed) {
            throw new InvalidArgumentException(sprintf('Report run %d is not completed yet (current status: %s).', $run->getId(), $run->getStatus()->value));
        }

        foreach ($run->getResults() as $result) {
            if ($result->getStatus() === ReportResultStatus::Ready) {
                return;
            }
        }

        throw new InvalidArgumentException('Report result is not ready yet. Run aggregation before exporting vehicles.');
    }

    /**
     * @return array<string, scalar|null>
     */
    private function extractAttributes(ReportVehicle $vehicle): array
    {
        $payload = $vehicle->getResponsePayload();

        if (!\is_array($payload)) {
            return [];
        }

        $data = $payload['data'] ?? null;

        if (\is_array($data) && isset($data['attributes']) && \is_array($data['attributes'])) {
            return $data['attributes'];
        }

        if (\is_array($data) && array_is_list($data)) {
            $first = $data[0] ?? null;

            if (\is_array($first) && isset($first['attributes']) && \is_array($first['attributes'])) {
                return $first['attributes'];
            }
        }

        if (isset($payload['attributes']) && \is_array($payload['attributes'])) {
            return $payload['attributes'];
        }

        return [];
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (\is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (\is_scalar($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function ensureDirectory(string $directory): void
    {
        if ($directory === '' || $directory === '.') {
            return;
        }

        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create directory for export: %s', $directory));
        }
    }
}
