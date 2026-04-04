<?php

namespace App\Imports;

use App\Models\Asset;
use App\Models\AssetItem;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\FundSource;
use App\Models\Region;
use App\Models\Facility;
use App\Models\SubLocation;
use App\Models\Assignee;
use App\Models\District;
use App\Models\User;
use App\Models\AssetApprovalItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class AssetsImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected $userId;
    protected $regionId;
    protected $districtId;
    protected $facilityId;

    public function __construct($userId = null, $regionId = null, $districtId = null, $facilityId = null)
    {
        $this->userId = $userId ?? auth()->id();
        $this->regionId = $regionId;
        $this->districtId = $districtId;
        $this->facilityId = $facilityId;
    }

    public function collection(Collection $rows)
    {        
        // Filter out completely empty rows
        $validRows = $rows->filter(function($row) {
            return !empty($row['asset_tag']) && !empty($row['asset_name']);
        });

        if ($validRows->isEmpty()) {
            return;
        }

        // 1. Determine shared Region, District and Facility (needed for scoping)
        $user = User::find($this->userId);
        $firstRow = $validRows->first();

        if ($this->regionId) {
            $region = Region::find($this->regionId);
        } else {
            $regionName = trim($firstRow['region'] ?? 'Unknown');
            $region = Region::firstOrCreate(['name' => $regionName]);
        }

        if ($this->districtId) {
            $district = District::find($this->districtId);
        } else {
            $districtName = trim($firstRow['district'] ?? 'Unknown');
            $district = District::where('name', $districtName)->where('region', $region->name)->first() 
                        ?? District::create(['name' => $districtName, 'region' => $region->name]);
        }

        if ($this->facilityId) {
            $assetLocation = Facility::find($this->facilityId);
        } else {
            $assetLocationName = trim($firstRow['asset_location'] ?? 'Unknown');
            $assetLocation = Facility::where('name', $assetLocationName)->where('district', $district->name)->first()
                        ?? Facility::create([
                            'name' => $assetLocationName,
                            'district' => $district->name,
                            'region' => $region->name,
                            'facility_type' => 'Other',
                            'has_cold_storage' => 0,
                            'is_active' => 1
                        ]);
        }

        // --- VALIDATION: Global Asset Tag Uniqueness ---
        $errors = [];
        $tagsInFile = [];

        foreach ($validRows as $index => $row) {
            $rowNum = $index + 2; // Excel row number
            $tag = trim($row['asset_tag'] ?? '');

            if ($tag) {
                // 1. Check within the file itself
                if (isset($tagsInFile[$tag])) {
                    $errors[] = "Row {$rowNum}: Duplicate Asset Tag '{$tag}' found within this file (previously at Row {$tagsInFile[$tag]}).";
                }
                $tagsInFile[$tag] = $rowNum;

                // 2. Check against database (Global Uniqueness)
                $existingItem = AssetItem::where('asset_tag', $tag)->with('asset.facility')->first();
                if ($existingItem) {
                    $locationName = $existingItem->asset->facility->name ?? 'Unknown Location';
                    $errors[] = "Row {$rowNum}: Asset Tag '{$tag}' already exists in the system (at {$locationName}).";
                }
            }
        }

        if (!empty($errors)) {
            // Throw first 5 errors to avoid overwhelming, then summarize
            $displayErrors = array_slice($errors, 0, 5);
            if (count($errors) > 5) {
                $displayErrors[] = "...and " . (count($errors) - 5) . " more conflicts found.";
            }
            throw new \Exception(implode('<br>', $displayErrors));
        }
        // --- END VALIDATION ---

        try {
            DB::transaction(function () use ($validRows, $user, $region, $district, $assetLocation) {
                $firstRow = $validRows->first();

                // 1. Determine shared Region, District and Facility
                if ($this->regionId) {
                    $region = Region::find($this->regionId);
                } else {
                    $regionName = trim($firstRow['region'] ?? 'Unknown');
                    $region = Region::firstOrCreate(['name' => $regionName]);
                }

                if ($this->districtId) {
                    $district = District::find($this->districtId);
                } else {
                    $districtName = trim($firstRow['district'] ?? 'Unknown');
                    $district = District::where('name', $districtName)->where('region', $region->name)->first() 
                                ?? District::create(['name' => $districtName, 'region' => $region->name]);
                }

                if ($this->facilityId) {
                    $assetLocation = Facility::find($this->facilityId);
                } else {
                    $assetLocationName = trim($firstRow['asset_location'] ?? 'Unknown');
                    $assetLocation = Facility::where('name', $assetLocationName)->where('district', $district->name)->first()
                                ?? Facility::create([
                                    'name' => $assetLocationName,
                                    'district' => $district->name,
                                    'region' => $region->name,
                                    'facility_type' => 'Other',
                                    'has_cold_storage' => 0,
                                    'is_active' => 1
                                ]);
                }

                // Sub Location from first row as default for the batch "roof"
                $subLocation = SubLocation::firstOrCreate([
                    'name' => trim($firstRow['sub_location'] ?? 'N/A'),
                    'facility_id' => $assetLocation->id,
                ]);

                // Fund Source and Date from first row as default for legacy fields in assets table
                $firstFundSource = FundSource::firstOrCreate(['name' => trim($firstRow['fund_source'] ?? 'Unknown')]);
                $firstAcquisitionDate = $this->parseDate($firstRow['acquisition_date'] ?? null);

                // 2. Create the ONE "roof" Asset
                $asset = Asset::create([
                    'acquisition_date' => $firstAcquisitionDate,
                    'organization' => $user->organization ?? 'PSI',
                    'fund_source_id' => $firstFundSource->id,
                    'region_id' => $region->id,
                    'district_id' => $district->id,
                    'facility_id' => $assetLocation->id,
                    'sub_location_id' => $subLocation->id,
                    'status' => 'pending_approval',
                    'submitted_by' => $this->userId,
                    'submitted_at' => now(),
                ]);

                // 3. Create all Asset Items linked to this Asset
                foreach ($validRows as $row) {
                    $category = AssetCategory::firstOrCreate(['name' => trim($row['category'])]);
                    
                    $type = AssetType::where('name', trim($row['type']))->first();
                    if (!$type) {
                        $type = AssetType::create(['name' => trim($row['type']), 'asset_category_id' => $category->id]);
                    }

                    $assignee = null;
                    if (!empty($row['assignee'])) {
                        $assignee = Assignee::firstOrCreate(['name' => trim($row['assignee'])]);
                    }

                    // Specific Fund Source and Date for this item
                    $itemFundSource = FundSource::firstOrCreate(['name' => trim($row['fund_source'] ?? 'Unknown')]);
                    $itemAcquisitionDate = $this->parseDate($row['acquisition_date'] ?? null);
                    $status = $this->mapStatus($row['status'] ?? 'in_use');

                    AssetApprovalItem::create([
                        'asset_id' => $asset->id,
                        'asset_tag' => trim($row['asset_tag']),
                        'asset_name' => trim($row['asset_name']),
                        'serial_number' => !empty($row['serial_number']) ? trim($row['serial_number']) : 'SN-' . uniqid(),
                        'asset_category_id' => $category->id,
                        'asset_type_id' => $type->id,
                        'assignee_id' => $assignee?->id,
                        'fund_source_id' => $itemFundSource->id,
                        'acquisition_date' => $itemAcquisitionDate,
                        'status' => $status,
                        'original_value' => is_numeric($row['value']) ? (float)$row['value'] : 0,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            // Check for Column Data Truncation (Warning 1265)
            if (str_contains($e->getMessage(), '1265 Data truncated')) {
                // Try to find which column it was
                $column = 'status'; // Most common in this specific import
                if (str_contains($e->getMessage(), 'for column')) {
                    preg_match("/column '([^']+)'/", $e->getMessage(), $matches);
                    $column = $matches[1] ?? $column;
                }
                
                throw new \Exception("<b>Import Failed: Data Format Error</b><br><br>" . 
                    "One of your rows has an invalid value for the <b>'{$column}'</b> field.<br>" .
                    "Please ensure all status values match the allowed options: <b>Functioning</b>, <b>Not Functioning</b>, <b>Disposed</b>, or <b>Maintenance</b>.");
            }
            throw $e;
        }
    }

    private function parseDate($dateValue)
    {
        if (empty($dateValue)) return now();

        try {
            if (is_numeric($dateValue)) {
                return Date::excelToDateTimeObject($dateValue);
            }
            return \Carbon\Carbon::parse((string)$dateValue);
        } catch (\Exception $e) {
            return now();
        }
    }

    private function mapStatus(?string $status): string
    {
        if (empty($status)) return 'functioning';
        
        $status = str_replace([' ', '-'], '_', strtolower(trim($status)));

        // User requested specific mappings:
        // - Functioning => functioning
        // - Not Functioning => not_functioning
        // - Disposed => disposed
        // - Maintenance => maintenance
        
        $map = [
            'functioning' => 'functioning',
            'not_functioning' => 'not_functioning',
            'disposed' => 'disposed',
            'maintenance' => 'maintenance',
            'maintenence' => 'maintenance', // Handle common typo
            'broken' => 'not_functioning',
            'in_use' => 'functioning',
            'active' => 'functioning',
            'new' => 'functioning',
            'repair' => 'maintenance',
            'pending_approval' => 'pending_approval',
            'pending_review' => 'pending_approval',
            'to_be_approved' => 'pending_approval',
        ];

        return $map[$status] ?? 'functioning';
    }

    public function chunkSize(): int
    {
        return 100;
    }
}
