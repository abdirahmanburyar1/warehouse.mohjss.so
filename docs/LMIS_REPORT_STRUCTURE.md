# LMIS Report – Current vs Target Structure

## 1. Where it lives

| Layer | Location |
|-------|----------|
| **Route** | `GET /reports/lmis-monthly-report` → `reports.lmis-monthly` |
| **Controller** | `App\Http\Controllers\ReportController::lmisMonthlyReport()` |
| **Page** | `resources/js/Pages/Report/LMISMonthlyReport.vue` |
| **Report model** | `App\Models\FacilityMonthlyReport` (table: `facility_monthly_reports`) |
| **Item model** | `App\Models\FacilityMonthlyReportItem` (table: `facility_monthly_report_items`) |

---

## 2. Current backend

### `FacilityMonthlyReport` (parent)
- `facility_id`, `report_period`, `status`, `comments`
- Workflow: `submitted_at/by`, `reviewed_at/by`, `approved_at/by`, `rejected_at/by`

### `FacilityMonthlyReportItem` (one row per product, no batch)
- `parent_id` → facility_monthly_reports.id  
- `product_id` → products.id  
- `opening_balance`, `stock_received`, `stock_issued`  
- `positive_adjustments`, `negative_adjustments`  
- `closing_balance`, `stockout_days`  

**Not present:** `uom`, `batch_number`, `expiry_date`, `beginning_balance` (alias of opening), `other_quantity_out`, `total_closing_balance`, `average_monthly_consumption`, `months_of_stock`, `quantity_in_pipeline`.

### Controller behaviour
- `lmisMonthlyReport()`: loads **first** report where `report_period = request->month_year` (no `facility_id` filter).
- Returns: `report` with `items`, each item with `product` and `product.category`.

---

## 3. Current frontend (LMISMonthlyReport.vue)

### Filters
- Facilities grouped by district (from `facilitiesGrouped`).
- Month/Year (`month_year`).
- “Get Report” calls same route with `facility` + `month_year` (backend currently ignores `facility` when loading the report).

### Report table (one row per product)
| Column | Source |
|--------|--------|
| Item | `item.product?.name` |
| Opening Balance | `item.opening_balance` |
| Stock Received | `item.stock_received` |
| Stock Issued | `item.stock_issued` |
| + Adjustments | `item.positive_adjustments` |
| – Adjustments | `item.negative_adjustments` |
| Closing Balance | `item.closing_balance` |
| Stockout Days | `item.stockout_days` |

### Not shown
- Category, UoM, Batch No., Expiry Date  
- Item-level totals (Total Closing Balance)  
- AMC, MOS  
- Batch-level rows (multiple rows per item)

### Export
- Excel and PDF use the same 8 columns above.

---

## 4. Target design (from LMIS spec)

### Table layout (batch-level rows, item-level summary)
- **Item** – product name (row span over batches).
- **Category** – product category (row span).
- **UoM** – unit of measure (row span).
- **Item details**
  - **Batch No.** – one row per batch.
  - **Expiry Date** – per batch (e.g. MM/YYYY).
- **Beginning Balance** – per batch.
- **QTY Received** – per batch.
- **QTY Issued** – per batch.
- **Adjustments**
  - **(−)** – per batch.
  - **(+)** – per batch.
- **Closing Balance** – per batch.
- **Total Closing Balance** – sum of batch closing balances for that item (row span).
- **AMC** – average monthly consumption (item level, row span).
- **MOS** – months of stock (item level, row span).
- **Stockout Days** – item level (row span).

So: **data is batch-level** (batch number + expiry per row), with **item-level** fields (Total Closing Balance, AMC, MOS, Stockout Days) shown once per product (e.g. row span).

---

## 5. Gap summary

| Aspect | Current | Target |
|--------|--------|--------|
| Granularity | One row per **product** | Multiple rows per **product** (one per **batch**) |
| Batch | No batch_number / expiry_date | Batch No. + Expiry Date per row |
| Columns | 8 columns | Item, Category, UoM, Batch/Expiry, Beginning Balance, Received, Issued, Adj(-), Adj(+), Closing, Total Closing, AMC, MOS, Stockout Days |
| Item-level totals | Not shown | Total Closing Balance, AMC, MOS (with row span) |
| Report filter | Backend ignores `facility` | Report should be for selected facility + month_year |

---

## 6. Implementation options

1. **DB/schema**  
   - Either add batch-level fields to `facility_monthly_report_items` (e.g. `batch_number`, `expiry_date`, `uom`, and optionally `total_closing_balance`, `average_monthly_consumption`, `months_of_stock`) and store one row per batch,  
   - Or keep one row per product and add a separate batch-detail table (e.g. `facility_monthly_report_item_batches`) linked to `facility_monthly_report_items`.

2. **Controller**  
   - Filter report by `facility_id` and `report_period`.  
   - Return items (and batches if separate table) so the frontend can group by product and render batch rows + item-level totals.

3. **Frontend**  
   - Change table to grouped layout: one block per product, batch rows with Batch No., Expiry, Beginning Balance, Received, Issued, Adj(-), Adj(+), Closing; then one row (or merged cell) for Total Closing Balance, AMC, MOS, Stockout Days.  
   - Update Excel/PDF export to match this layout.

If you tell me whether you prefer “one row per batch” in the same table vs “product + batch child table”, I can outline the exact migration and model changes next.
