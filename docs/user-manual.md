# Efact User Manual

This guide describes the current features of Efact, how to navigate the UI, and the business rules enforced by the backend. It is organized by everyday tasks so you can work faster.

---

## 1) Roles & Core Rules
- Roles: **CASHIER** (consults, sells, opens/closes own cash), **SUPERVISOR** (can authorize cancellations/discounts), **ADMIN** (full access).
- Cash register must be **OPEN** to record sales. One active cash session per user. Closing requires summary, counted cash, and acknowledgment of differences.
- Issued receipts cannot be edited; cancellations require supervisor authorization. Data must match SUNAT rules.
- Inventory changes only through sales, returns, inbound/outbound/transfer operations, or authorized adjustments.

---

## 2) Navigation & Layout
- **Header:** menu toggle, branch title, theme switch, notifications (bell), and **Assistant** icon (robot) to open the AI helper popover anywhere.
- **Side menu:** access to modules (Dashboard, Sales, Products, Inventory, Cash, etc.). Items respect user role.
- **Content area:** pages use grids/forms with create/edit/delete actions and filters.

---

## 3) Assistant (AI)
- Click the robot icon in the header to open/close the popover. It does not block the page.
- Ask in Spanish; the assistant uses tools to search products, check stock, summaries of sales, recent sales, and cash register status.
- If a tool fails or lacks permission, the assistant will say so; it will not guess system data.

---

## 4) Daily Operations

### 4.1 Cash Register
- **Dashboard:** `/cashbox` shows registers and status.
- **Open shift:** `/cash-register/{id}/open` (enter opening balance). Required before selling.
- **Movements:** `/cash-shift/{id}/movements` to log incomes/expenses.
- **Close shift:** `/cash-register/{id}/close` (expected vs counted cash; record differences).
- **Shifts history:** `/cash-register/{id}/shifts`.

### 4.2 Sales
- **Sales page:** `/sale` — create/edit sales. Cash must be open.
- **Steps:** select customer, warehouse, price list, add items, apply discounts (supervisor if restricted), choose payment.
- **Print/Email:** ticket/receipt options per sale; receipt views at `/receipt/{payload}` and related endpoints.
- **Consultation:** `/sale-consult` for reports by type (detailed, payment-method daily, daily summary).
- **Credit notes:** `/credit-note` for cancellations/notes (needs supervisor).

### 4.3 Products & Pricing
- **Products:** `/product` — manage code, name, category, unit, prices, stock minima, photo.
- **Categories:** `/category`; **Units:** `/unit-of-measure`; **Price lists:** `/price-list`.
- Stock per warehouse is visible on product detail; server enforces stock types.

### 4.4 Inventory Movements
- **Inbound:** `/inbound` (receiving/adjusting in); **Outbound:** `/outbound` (issues/adjusting out).
- **Transfers:** `/transfer` (between warehouses).
- **Kardex:** `/kardex` — per product movement log; includes master-detail view.
- Stock is updated only through these flows or sales; no manual free-form edits.

### 4.5 Cash & Payments
- **Payment methods:** `/payment-method`.
- **Cash movement categories:** `/cash-movement-category`.
- **Cash registers:** `/cash-register` config and linkage to branches.

### 4.6 Customers & Suppliers
- **Customers:** `/customer` — add/edit; validation for RUC/DNI and email.
- **Suppliers:** `/supplier` — similar flow.

### 4.7 Company & Locations
- **Company profile:** `/company`.
- **Branches:** `/branch`; **Warehouses:** `/warehouse`.
- **Departments/Positions:** `/department`, `/position`.

### 4.8 Configuration & Security
- **Users/Roles:** `/user`, `/role` — manage access; roles drive permissions.
- **Voucher types/series:** `/voucher-type`, `/voucher-series`.
- **Config:** `/config` for general settings.
- **Initial setup:** `/setup` (when not configured).

---

## 5) How to Perform Common Tasks
- **Create a product:** Products → “Nuevo”, fill required fields (code, name, category, unit, price), set min stock if needed, save.
- **Open a cash shift:** Cash Registers → select register → Open → enter opening balance → confirm.
- **Record a sale:** Ensure shift is open → Sales → “Nuevo” → pick customer/warehouse/price list → add items → set payment → save → print/email as needed.
- **Register cash movement:** Cash Movements → choose shift → add income/expense with category and description.
- **Transfer stock:** Transfers → pick source/destination warehouses → add items/qty → save.
- **Close shift:** Cash Registers → select register → Close → enter counted cash → confirm differences → close.

---

## 6) Business Rules the UI Enforces
- Cannot post sales without an open cash shift.
- Discounts/cancellations may require supervisor role.
- Receipts/tickets are immutable after issuing; cancellations via credit note.
- Stock validation: outbound/sale checks configured stock rules; min stock alerts via reports/assistant.

---

## 7) Troubleshooting Tips
- **Unauthorized/Forbidden:** check role; admin or supervisor may be required.
- **Stock errors:** verify warehouse selection and available quantity; use Kardex/stock views.
- **Cash shift errors:** ensure you opened the shift for the selected register.
- **Assistant not responding:** reopen the popover via header icon; ensure backend AI config and OpenAI key are set.

---

## 8) Shortcuts for Navigation (menu names)
- Dashboard: `/dashboard`
- Sales: `/sale`
- Sale Consult: `/sale-consult`
- Products: `/product`
- Categories: `/category`
- Units: `/unit-of-measure`
- Price Lists: `/price-list`
- Customers: `/customer`
- Suppliers: `/supplier`
- Warehouses: `/warehouse`
- Branches: `/branch`
- Cashbox dashboard: `/cashbox`
- Cash Register config: `/cash-register`
- Cash movements: `/cash-shift/{id}/movements`
- Inbound/Outbound/Transfer: `/inbound`, `/outbound`, `/transfer`
- Kardex: `/kardex`
- Users/Roles: `/user`, `/role`
- Config: `/config`

Use the side navigation for access; URLs above match the menu entries.
