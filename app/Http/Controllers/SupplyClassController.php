<?php

namespace App\Http\Controllers;

use App\Models\SupplyClass;
use App\Http\Resources\SupplyClassResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SupplyClassImport;
use App\Exports\SupplyClassTemplateExport;

class SupplyClassController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->hasPermission('product-view') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the products module.');
        }

        $query = SupplyClass::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('supply_class', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%");
            });
        }

        $supplyClasses = $query->orderBy('supply_class')
            ->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $supplyClasses->setPath(url()->current());

        return Inertia::render('Product/SupplyClass/Index', [
            'supplyClasses' => SupplyClassResource::collection($supplyClasses),
            'filters' => $request->only('search', 'per_page', 'page'),
        ]);
    }

    public function create()
    {
        if (!auth()->user()->hasPermission('product-view') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the products module.');
        }
        return Inertia::render('Product/SupplyClass/Create');
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage products.');
        }
        try {
            $request->validate([
                'supply_class' => 'required|string|max:255',
                'category' => 'nullable|string|max:255',
                'source' => 'nullable|string|max:255',
            ]);

            SupplyClass::create([
                'supply_class' => trim($request->supply_class),
                'category' => $request->filled('category') ? trim($request->category) : null,
                'source' => $request->filled('source') ? trim($request->source) : null,
                'is_active' => true,
            ]);

            return response()->json(['message' => 'Supply class created successfully'], 200);
        } catch (Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function edit(SupplyClass $supplyClass)
    {
        if (!auth()->user()->hasPermission('product-view') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the products module.');
        }
        return Inertia::render('Product/SupplyClass/Edit', [
            'supplyClass' => new SupplyClassResource($supplyClass),
        ]);
    }

    public function update(Request $request, SupplyClass $supplyClass)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage products.');
        }
        try {
            $request->validate([
                'supply_class' => 'required|string|max:255',
                'category' => 'nullable|string|max:255',
                'source' => 'nullable|string|max:255',
            ]);

            $supplyClass->update([
                'supply_class' => trim($request->supply_class),
                'category' => $request->filled('category') ? trim($request->category) : null,
                'source' => $request->filled('source') ? trim($request->source) : null,
            ]);

            return response()->json(['message' => 'Supply class updated successfully'], 200);
        } catch (Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function destroy(SupplyClass $supplyClass)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage products.');
        }
        try {
            $supplyClass->delete();
            return response()->json('Supply class deleted successfully', 200);
        } catch (Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function toggleStatus(SupplyClass $supplyClass)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage products.');
        }
        try {
            $supplyClass->update(['is_active' => !$supplyClass->is_active]);
            $status = $supplyClass->is_active ? 'activated' : 'deactivated';
            return response()->json(['message' => "Supply class {$status} successfully"], 200);
        } catch (Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function import(Request $request)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to import supply classes.');
        }
        try {
            if (!$request->hasFile('file')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No file was uploaded',
                ], 422);
            }

            $file = $request->file('file');
            if (!$file->isValid() || !in_array($file->getClientOriginalExtension(), ['xlsx', 'xls', 'csv'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file type. Please upload an Excel file (.xlsx, .xls) or CSV file',
                ], 422);
            }

            $import = new SupplyClassImport();
            Excel::import($import, $file);
            $results = $import->getResults();

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Import completed: %d record(s) imported, %d skipped.',
                    $results['imported'],
                    $results['skipped']
                ),
                'imported' => $results['imported'],
                'skipped' => $results['skipped'],
                'errors' => $results['errors'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function downloadTemplate()
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to download templates.');
        }
        return Excel::download(new SupplyClassTemplateExport(), 'supply_class_import_template.xlsx');
    }
}
