<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Imports\FacilityInventoryImport;
use App\Exports\FacilityInventoryTemplateExport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class FacilityInventoryController extends Controller
{
    public function index(Request $request)
    {
        if (!$this->canUpload()) {
            abort(403, 'You do not have permission to access Facility Inventory upload.');
        }

        $facilities = Facility::select('id', 'name', 'facility_type')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('FacilityInventory/Index', [
            'facilities' => $facilities,
            'canUpload' => true,
        ]);
    }

    public function import(Request $request)
    {
        if (!$this->canUpload()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to upload facility inventory.',
            ], 403);
        }

        $request->validate([
            'facility_id' => 'required|integer|exists:facilities,id',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200',
        ]);

        $file = $request->file('file');

        if (!$file->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file. Please try again.',
            ], 422);
        }

        $extension = $file->getClientOriginalExtension();
        if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file type. Please upload an Excel file (.xlsx, .xls) or CSV file.',
            ], 422);
        }

        if ($file->getSize() > 50 * 1024 * 1024) {
            return response()->json([
                'success' => false,
                'message' => 'File size too large. Maximum allowed size is 50MB.',
            ], 422);
        }

        try {
            set_time_limit(120);

            $import = new FacilityInventoryImport((int) $request->facility_id);
            Excel::import($import, $file);

            if ($import->createdCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No inventory items were imported. Please check your file.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully imported {$import->createdCount} inventory item(s) for the facility.",
                'created_count' => $import->createdCount,
            ]);
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            if ($code === 422) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        } finally {
            if ($file->isValid() && file_exists($file->getRealPath())) {
                unlink($file->getRealPath());
            }
        }
    }

    public function downloadTemplate(Request $request)
    {
        if (!$this->canUpload()) {
            abort(403, 'You do not have permission to download facility inventory template.');
        }

        $request->validate([
            'facility_id' => 'required|integer|exists:facilities,id',
        ]);

        $facility = Facility::find($request->facility_id);
        $filename = 'facility_inventory_template_' . Str::slug($facility->name ?? 'facility') . '_' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new FacilityInventoryTemplateExport((int) $request->facility_id),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    private function canUpload(): bool
    {
        $user = auth()->user();
        return $user->isAdmin() || $user->hasRole('Supply Chain');
    }
}
