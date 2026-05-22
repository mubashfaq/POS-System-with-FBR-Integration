<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Services\FbrTokenService;
use Illuminate\Support\Facades\Log;

class BulkFilerCheckController extends Controller
{
    // ─── Show the upload page ─────────────────────────────────────────────────
    public function index()
    {
        return view('frontend.filer.bulk-check');
    }

    // ─── AJAX: process file and return JSON results ───────────────────────────
    public function checkBulk(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $file        = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, true);

            if (empty($rows)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The uploaded file is empty.'
                ], 422);
            }

            // ── Detect header row (row 1) ──────────────────────────────────────
            $headerRow = $rows[1] ?? [];
            $headers   = array_map(fn($h) => strtolower(trim((string)$h)), $headerRow);

            $cnicCol = null;
            $nameCol = null;

            foreach ($headers as $col => $heading) {
                if (str_contains($heading, 'cnic'))                          $cnicCol = $col;
                if (str_contains($heading, 'name') && $nameCol === null)     $nameCol = $col;
            }

            if (!$cnicCol) {
                return response()->json([
                    'success' => false,
                    'message' => 'No CNIC column found. Please make sure one column header contains the word "cnic".'
                ], 422);
            }

            // ── Process each row ───────────────────────────────────────────────
            $results    = [];
            $registered = 0;
            $notReg     = 0;
            $skipped    = 0;

            foreach ($rows as $rowIndex => $row) {
                if ($rowIndex === 1) continue; // skip header

                $rawCnic = trim((string)($row[$cnicCol] ?? ''));
                $cnic    = str_replace(['-', ' '], '', $rawCnic);

                if (empty($cnic)) {
                    $skipped++;
                    continue;
                }

                // Validate CNIC length (13 digits)
                if (!preg_match('/^\d{13}$/', $cnic)) {
                    $results[] = [
                        'row'        => $rowIndex,
                        'name'       => $nameCol ? trim((string)($row[$nameCol] ?? '')) : '-',
                        'cnic'       => $rawCnic,
                        'status'     => 'Invalid CNIC',
                        'registered' => null,
                    ];
                    $skipped++;
                    continue;
                }

                $name       = $nameCol ? trim((string)($row[$nameCol] ?? '')) : '-';
                $isReg      = $this->fetchStatus($cnic);

                if ($isReg === true)  $registered++;
                else                 $notReg++;

                $results[] = [
                    'row'        => $rowIndex,
                    'name'       => $name,
                    'cnic'       => $rawCnic,
                    'status'     => $isReg ? 'Registered' : 'Not Registered',
                    'registered' => $isReg,
                ];
            }

            // Store results in session for download later
            session(['bulk_filer_results' => $results]);

            return response()->json([
                'success'    => true,
                'total'      => count($results),
                'registered' => $registered,
                'not_reg'    => $notReg,
                'skipped'    => $skipped,
                'data'       => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing file: ' . $e->getMessage()
            ], 500);
        }
    }

    // ─── Download result as Excel ─────────────────────────────────────────────
    public function downloadResult(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $file        = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, true);

            $headerRow = $rows[1] ?? [];
            $headers   = array_map(fn($h) => strtolower(trim((string)$h)), $headerRow);

            $cnicCol = null;
            foreach ($headers as $col => $heading) {
                if (str_contains($heading, 'cnic')) $cnicCol = $col;
            }

            if (!$cnicCol) {
                abort(422, 'CNIC column not found.');
            }

            // ── Find next available column ─────────────────────────────────────
            $colLetters  = range('A', 'Z');
            $lastColIdx  = array_search(array_key_last($headers), $colLetters);
            $statusCol   = $colLetters[$lastColIdx + 1] ?? 'Z';

            // ── Style header cell ──────────────────────────────────────────────
            $sheet->setCellValue($statusCol . '1', 'Registration Status');
            $sheet->getStyle($statusCol . '1')->applyFromArray([
                'font' => [
                    'bold'  => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1a56db'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ]);

            // ── Process each row ───────────────────────────────────────────────
            foreach ($rows as $rowIndex => $row) {
                if ($rowIndex === 1) continue;

                $cnic = str_replace(['-', ' '], '', trim((string)($row[$cnicCol] ?? '')));

                if (empty($cnic)) continue;

                if (!preg_match('/^\d{13}$/', $cnic)) {
                    $statusText  = 'Invalid CNIC';
                    $bgColor     = 'FFFFC107'; // yellow
                } else {
                    $isReg       = $this->fetchStatus($cnic);
                    $statusText  = $isReg ? 'Registered' : 'Not Registered';
                    $bgColor     = $isReg ? 'FF00C851' : 'FFFF4444'; // green / red
                }

                $cell = $statusCol . $rowIndex;
                $sheet->setCellValue($cell, $statusText);
                $sheet->getStyle($cell)->applyFromArray([
                    'font' => ['color' => ['argb' => 'FFFFFFFF']],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => $bgColor],
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }

            $sheet->getColumnDimension($statusCol)->setWidth(22);

            // ── Write and stream ───────────────────────────────────────────────
            $writer   = new Xlsx($spreadsheet);
            $filename = 'filer_status_' . now()->format('Ymd_His') . '.xlsx';
            $tempDir  = storage_path('app/temp');

            if (!file_exists($tempDir)) mkdir($tempDir, 0755, true);

            $tempPath = $tempDir . '/' . $filename;
            $writer->save($tempPath);

            return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return back()->with('error', 'Download failed: ' . $e->getMessage());
        }
    }

    // Private: call FBR API 
    private function fetchStatus(string $cnic): bool
    {
        try {
            // Global token for non-logged-in / bulk public check
            $token = config('services.fbr.global_token');

            if (empty($token)) {
                Log::warning('FBR global token not configured for bulk check.');
                return false;
            }

            $response = Http::timeout(10)->withHeaders([
                'Authorization'   => "Bearer $token",
                'Content-Type'    => 'application/json',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept'          => '*/*',
            ])->get('https://gw.fbr.gov.pk/dist/v1/Get_Reg_Type', [
                'Registration_No' => $cnic,
            ]);

            $data = $response->json();

            return isset($data['REGISTRATION_TYPE'])
                && $data['REGISTRATION_TYPE'] === 'Registered';
        } catch (\Exception $e) {
            return false;
        }
    }
}
