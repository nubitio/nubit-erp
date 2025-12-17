# Efact Cashier User Manual

**Version:** 1.0  
**Date:** December 2024  
**Audience:** Cashier role users  
**Purpose:** Comprehensive guide for daily cashier operations and AI assistant knowledge base

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Cashier Role Overview](#2-cashier-role-overview)
3. [Getting Started](#3-getting-started)
4. [Cash Register Operations](#4-cash-register-operations)
5. [Sales Operations](#5-sales-operations)
6. [Payment Processing](#6-payment-processing)
7. [Product Information](#7-product-information)
8. [Customer Management](#8-customer-management)
9. [Credit Notes and Cancellations](#9-credit-notes-and-cancellations)
10. [Reports and Consultations](#10-reports-and-consultations)
11. [AI Assistant Usage](#11-ai-assistant-usage)
12. [Daily Workflow Guide](#12-daily-workflow-guide)
13. [Business Rules and Validations](#13-business-rules-and-validations)
14. [Troubleshooting](#14-troubleshooting)
15. [Keyboard Shortcuts and Tips](#15-keyboard-shortcuts-and-tips)

---

## 1. Introduction

### 1.1 About Efact
Efact is an electronic billing system designed to manage sales, inventory, cash operations, and fiscal documentation in compliance with SUNAT (Peru's tax authority) regulations. This manual focuses on the cashier role and daily operations performed at the point of sale.

### 1.2 System Access
- **URL:** Access the system through your provided web address
- **Login:** Use your assigned username and password
- **Branch Selection:** After login, select your branch location if prompted
- **Role:** You must be assigned the CASHIER role to access cashier features

### 1.3 System Requirements
- Modern web browser (Chrome, Firefox, Edge, or Safari)
- Stable internet connection
- Optional: QZ Tray for thermal printer integration
- Screen resolution: Minimum 1024x768 (recommended 1920x1080)

---

## 2. Cashier Role Overview

### 2.1 Permissions and Capabilities
As a cashier, you can:
- **Open and close** your assigned cash register
- **Record sales** and process payments
- **Consult** product information and stock levels
- **Create and manage** customer records
- **Register** cash movements (income/expenses)
- **View** sales reports and daily summaries
- **Print/email** receipts and invoices
- **Access** the AI assistant for help

### 2.2 Limitations
As a cashier, you **cannot**:
- Approve discounts beyond authorized limits (requires SUPERVISOR)
- Cancel or void issued invoices without authorization (requires SUPERVISOR)
- Modify inventory directly
- Access administrative configurations
- Manage users or roles
- Modify company or branch settings

### 2.3 Key Principle
**All sales operations require an OPEN cash register session.** You must open your cash register at the beginning of your shift and close it at the end.

---

## 3. Getting Started

### 3.1 Login Process
1. Navigate to the Efact system URL
2. Enter your **username** and **password**
3. Click **"Iniciar Sesión"** (Login)
4. If prompted, select your **branch location**
5. You will be directed to the main dashboard

### 3.2 User Interface Layout

#### Header Bar (Top)
- **Menu Toggle** (☰): Expand/collapse side navigation
- **Branch Name**: Current branch location
- **Theme Switch** (🌙/☀): Toggle dark/light mode
- **Notifications** (🔔): System notifications and alerts
- **Assistant Icon** (🤖): Open AI assistant popover
- **User Menu**: Your profile and logout option

#### Side Navigation Menu
The left sidebar provides access to modules:
- **Dashboard**: Overview and quick stats
- **Sales**: Create and manage sales
- **Sales Consult**: View sales reports
- **Credit Notes**: Manage cancellations and credit notes
- **Products**: View and search products
- **Customers**: Manage customer information
- **Cash Register**: Cash register operations
- **Cash Movements**: Record income/expense transactions
- **Reports**: Various operational reports

#### Main Content Area
The center area displays:
- Data grids with filters and search
- Forms for data entry
- Reports and summaries
- Detail views of records

### 3.3 Navigation Tips
- Click menu items in the sidebar to navigate between modules
- Use the browser's back button with caution (prefer in-app navigation)
- The system autosaves form data in some screens
- Look for breadcrumbs at the top of pages for context

---

## 4. Cash Register Operations

### 4.1 Overview
The cash register module tracks your daily cash handling, including opening balance, sales, movements, and closing reconciliation. Each cashier operates their own cash session.

**URL Path:** `/cash-register-dashboard`

### 4.2 Cash Register Dashboard

#### Accessing the Dashboard
1. Click **"Cash Register"** or **"Caja"** in the side menu
2. Navigate to **"Dashboard"** or direct URL: `/cash-register-dashboard`

#### Dashboard Information
The dashboard displays:
- **Available cash registers** in your branch
- **Current status** of each register (OPEN/CLOSED)
- **Active shift details** (if open)
- **Quick action buttons** (Open/Close/View)

### 4.3 Opening a Cash Register

#### Prerequisites
- You must NOT have another active cash shift open
- The cash register must be assigned to your branch
- You need opening cash on hand to count and declare

#### Steps to Open Cash Register
1. From the **Cash Register Dashboard**, locate your assigned register
2. Click **"Open"** or **"Abrir Caja"** button
3. You will be redirected to `/cash-register/{id}/open`
4. **Enter Opening Balance** (`openingBalance`):
   - Count the physical cash in the register
   - Enter the total amount in the currency field
   - This amount is REQUIRED and must be accurate
5. Click **"Abrir caja"** (Open cash register) button
6. Wait for confirmation message: **"Caja abierta"** (Cash opened)
7. You will be redirected to the **Cash Movements** screen for that shift

#### Business Rules - Opening
- Only ONE cash shift can be OPEN per cashier at a time
- Opening balance must be a positive number or zero
- Opening balance is recorded with timestamp
- The shift status changes to **OPEN**
- You are recorded as the user who opened the shift

#### Important Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `openingBalance` | Currency | Yes | Physical cash counted at shift start |

### 4.4 Cash Movements (During Shift)

**URL Path:** `/cash-shift/{id}/movements`

#### Overview
Cash movements record any money entering or leaving the register outside of sales transactions (e.g., bank deposits, expense reimbursements, petty cash).

#### Accessing Cash Movements
- After opening cash, you're automatically directed here
- Or navigate: **Cash Register** → **Dashboard** → Select open shift → **"Movements"**

#### Recording a Cash Movement

##### Steps
1. Click **"Nuevo"** (New) button in the toolbar
2. A dialog form appears with the following fields:

**Required Fields:**
| Field | Type | Required | Description | Validation |
|-------|------|----------|-------------|------------|
| `cashShift` | Reference | Yes (auto) | Current cash shift | System-filled |
| `category` | Dropdown | Yes | Category of movement | Must select from Cash Movement Categories |
| `type` | Enum | Yes (auto) | INCOME or EXPENSE | Auto-filled from category |
| `amount` | Currency | Yes | Transaction amount | Must be positive number |
| `description` | Text | Yes | Details of movement | Max 255 characters |

3. **Select Category**: Choose from predefined categories (e.g., "Bank Deposit", "Petty Cash", "Reimbursement")
   - The `type` (INCOME/EXPENSE) is automatically set based on category
4. **Enter Amount**: Input the monetary value
5. **Enter Description**: Provide clear details (who, what, why)
6. Click **"Guardar"** (Save)

##### Movement Types
- **INCOME**: Money added to register (e.g., change brought from bank)
- **EXPENSE**: Money removed from register (e.g., petty cash payment, deposit to bank)

#### Viewing Movement History
- The grid displays all movements for the current shift
- **Balance** is shown at the bottom (sum of incomes minus expenses)
- Balance calculation: `Opening Balance + Sales + Incomes - Expenses`

#### Business Rules - Movements
- Movements can only be recorded on an OPEN shift
- Amount must be greater than zero
- Category determines whether it's income or expense
- Movements are timestamped automatically
- Cannot edit or delete movements after creation (by default)

### 4.5 Closing a Cash Register

**URL Path:** `/cash-register/{id}/close`

#### When to Close
- At the end of your shift
- Before shift handover
- When instructed by supervisor
- Before leaving for the day

#### Prerequisites
- The cash register shift must be OPEN
- All pending sales should be completed
- Physical cash must be counted

#### Steps to Close Cash Register

1. From **Cash Register Dashboard**, locate your open shift
2. Click **"Close"** or **"Cerrar Caja"** button
3. You will be redirected to the closure screen
4. The system displays a **summary grid** with:
   - **Payment Method**: Each payment method used (Cash, Card, Transfer, etc.)
   - **Expected Amount**: System-calculated total for that method
   - **Counted Amount**: (empty - you must fill)
   - **Difference**: (calculated after you enter counted)
   - **Note**: Optional field for comments

##### Entering Counted Amounts
5. For each payment method row:
   - **Count physical money/receipts** for that method
   - Enter the counted total in **"Counted Amount"** (`countedAmount`) field
   - The system calculates **Difference** automatically: `Counted - Expected`
   - If there's a difference, enter explanation in **Note** field

6. **Review Differences**:
   - Positive difference = surplus (you have more than expected)
   - Negative difference = shortage (you have less than expected)
   - Zero difference = perfect match ✓

7. Confirm all required fields are filled (no undefined/empty counted amounts)

8. Click **"Cerrar"** (Close) button

9. A confirmation dialog appears: **"¿Está seguro que desea cerrar la caja?"**
   - Click **OK** to confirm
   - Click **Cancel** to go back

10. Upon successful closure:
    - Message: **"Caja cerrada correctamente"**
    - You are redirected to the Dashboard
    - Shift status changes to **CLOSED**

#### Business Rules - Closing
- All counted amounts must be entered (no undefined values)
- Difference is auto-calculated: `countedAmount - expectedAmount`
- Differences must be acknowledged (cannot skip)
- Notes are recommended for any variance
- Closing balance is recorded as the total counted amount
- Closure timestamp and closing user are recorded
- Once closed, shift cannot be reopened (immutable)
- Closure report can be printed/viewed for records

#### Closure Summary Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `paymentMethod` | Reference | Yes (auto) | Payment method ID |
| `expectedAmount` | Currency | Yes (auto) | System-calculated expected total |
| `countedAmount` | Currency | Yes | Physically counted amount |
| `difference` | Currency | Yes (auto) | Counted minus expected |
| `note` | Text | No | Explanation for variances |

### 4.6 Viewing Cash Shift History

**URL Path:** `/cash-register/{id}/shifts`

#### Accessing History
1. Navigate to **Cash Register** → select a register
2. Click **"Shifts"** or **"Historial"**

#### Information Available
- List of all shifts (open and closed)
- Opening and closing balances
- Dates and times
- Users who opened/closed
- Total sales per shift
- Movement summaries

#### Use Cases
- Audit past shifts
- Review performance
- Identify discrepancies
- Reporting and accountability

---

## 5. Sales Operations

### 5.1 Overview
The sales module is the core of cashier operations. Here you create, process, and manage customer transactions, generate invoices, and handle payments.

**URL Path:** `/sale`

### 5.2 Sales Page Layout

#### Toolbar Actions
- **"Nuevo"** (New): Create a new sale
- **"Editar"** (Edit): Modify a draft sale (before invoice)
- **"Eliminar"** (Delete): Remove a draft sale
- **"Imprimir"** (Print): Print invoice for selected sale
- **"Clonar"** (Clone): Duplicate a sale (if enabled)
- **"Anular"** (Void): Cancel an issued invoice (requires authorization)
- **"Generar Comprobante"** (Generate Invoice): Issue fiscal document

#### Sales Grid Columns
- **Date** (`fecha`): Transaction date
- **Customer** (`cliente`): Customer name and document
- **Voucher Type**: Invoice, Receipt, Note
- **Series/Number**: Fiscal document identifier
- **Total Amount**: Sale total
- **Payment Method**: How customer paid
- **Status**: Draft, Issued, Voided, etc.
- **SUNAT Status**: Sent, Accepted, Rejected

### 5.3 Creating a Sale

#### Prerequisites
- Cash register must be **OPEN**
- Products must exist in inventory
- Customer information should be available (can be created on-the-fly)

#### Steps to Create a Sale

**Step 1: Initiate New Sale**
1. Click **"Nuevo"** (New) button
2. Sale form dialog opens

**Step 2: Select Customer**
3. **Customer Field** (`cliente`):
   - Click the dropdown or search icon
   - Search by name, RUC, or DNI
   - Select existing customer OR
   - Click **"Nuevo Cliente"** to create a new customer (see section 8.3)
   - Default: "CLIENTE VARIOS" (generic customer) may be used for quick sales

**Required:** Customer must be selected before proceeding

**Step 3: Select Warehouse**
4. **Warehouse Field** (`almacen`):
   - Select the warehouse from which products will be deducted
   - Usually your branch's main warehouse
   - This determines stock availability

**Required:** Warehouse must be selected

**Step 4: Select Price List**
5. **Price List Field** (`price_list`):
   - Choose the pricing tier (e.g., Retail, Wholesale, Special)
   - Prices displayed will be from this list
   - Default list may be pre-selected

**Optional but recommended**

**Step 5: Add Products to Sale**

The sale includes a **detail grid** (items table) where you add products.

##### Adding a Product Line Item
6. In the detail grid section, click **"Add"** or **"+"** button
7. A new row appears with fields:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `producto` | Dropdown/Search | Yes | Product code or name |
| `cantidad` | Number | Yes | Quantity to sell |
| `precio_venta` | Currency | Yes (auto) | Unit price (from price list) |
| `precio_unitario` | Currency | Yes (auto) | Price before tax |
| `valor_unitario` | Currency | Yes (auto) | Net value |
| `descuento` | Percentage | No | Line-item discount |
| `subtotal` | Currency | Yes (auto) | Line total |

8. **Select Product**:
   - Type to search by code or name
   - System filters available products
   - Select from dropdown

9. **Enter Quantity**:
   - Input number of units
   - System validates against available stock
   - Decimal quantities allowed for fractional units

10. **Price Auto-fills**:
    - Based on selected price list
    - Can be manually adjusted if permissions allow

11. **Apply Line Discount** (optional):
    - Enter discount percentage (0-100)
    - May require supervisor authorization if above limit

12. **Subtotal Calculates Automatically**:
    - Formula: `(Unit Price × Quantity) - Discount`

13. **Repeat** steps 6-12 for each product

##### Editing/Removing Line Items
- **Edit**: Click on the row, modify quantity or discount, press Enter
- **Remove**: Select row, click delete/trash icon
- Changes recalculate total automatically

**Step 6: Apply Sale-Level Discount (Optional)**
14. **Discount Field** (`descuento`):
    - Enter percentage discount for entire sale
    - Click **"Aplicar Descuento"** if there's a discount dialog
    - May trigger supervisor authorization request if above cashier limit

**Step 7: Verify Totals**
The form displays calculated totals:
- **Subtotal** (`subtotal`): Sum before taxes
- **IGV** (`igv`): Tax amount (Peru's VAT)
- **Total** (`total`): Final amount to charge

Totals auto-update as you add/edit items.

**Step 8: Select Payment Method**
15. **Payment Method** (`forma_pago`):
    - Dropdown: Cash, Card, Transfer, Mixed, Credit
    - Select how customer will pay
    - Determines accounting treatment

**Required:** Payment method must be selected

**Step 9: Additional Fields (Optional)**
- **Notes** (`observaciones`): Internal comments
- **Reference** (`referencia`): External reference number
- **Due Date** (`fecha_vencimiento`): For credit sales

**Step 10: Save Sale**
16. Click **"Guardar"** (Save) button
17. System validates:
    - Cash register is open
    - Customer is selected
    - Warehouse is selected
    - At least one product line exists
    - Stock availability for all items
    - Totals are calculated correctly

18. If validation passes:
    - Sale is saved as **DRAFT** status
    - Success message appears
    - Sale appears in the grid

19. If validation fails:
    - Error message displays (see section 14 - Troubleshooting)
    - Correct the issue and try again

### 5.4 Generating Invoice (Issuing Fiscal Document)

After creating a sale in draft status, you must generate the fiscal invoice to complete the transaction.

#### Manual Invoice Generation
1. In the sales grid, **select the draft sale** row
2. Click **"Generar Comprobante"** (Generate Invoice) toolbar button
3. System processing message: **"Generando comprobante..."**
4. Invoice is created:
   - Voucher type, series, and number assigned
   - Tax calculations finalized
   - Document sent to SUNAT (if configured)
5. Success message appears
6. Sale status updates to **ISSUED**

#### Automatic Invoice Generation
If system setting **"Auto Invoice"** is enabled:
- Invoice generates automatically upon saving sale
- No manual step required
- Print dialog may appear automatically

### 5.5 Printing and Sending Invoices

#### Printing via QZ Tray (Thermal Printer)
1. Ensure QZ Tray is installed and running on your computer
2. Configure default printer in system settings
3. Select the **issued sale** in grid
4. Click **"Imprimir"** (Print) button
5. Invoice is sent to thermal printer
6. Ticket prints (usually 80mm thermal format)

#### Printing via Browser (PDF/A4)
1. Select issued sale
2. Click **"Ver"** (View) button
3. Invoice opens in new tab/window
4. Use browser print: `Ctrl+P` or File → Print
5. Select printer or save as PDF

#### Email Invoice to Customer
1. Select issued sale
2. Click **"Enviar por correo"** (Send by email) button
3. System sends invoice PDF to customer's registered email
4. Confirmation message displays

**Note:** Customer must have valid email address in their record.

#### Receipt URL
Receipts can be accessed via direct URL:
- **Format:** `/receipt/{payload}`
- **Payload:** Base64-encoded JSON with type and sale ID
- Used for customer self-service or digital display

### 5.6 Cloning a Sale

Useful for repeat customers or similar transactions.

#### Steps
1. Select an **existing sale** in grid
2. Click **"Clonar"** (Clone) button (enabled only when one sale selected)
3. New sale form opens with:
   - Same customer
   - Same products and quantities
   - Same prices and discounts
4. Modify as needed
5. Save as new sale

### 5.7 Voiding a Sale (Cancellation)

**Important:** Voiding requires **SUPERVISOR** authorization in most cases.

#### When to Void
- Customer returns items
- Incorrect invoice issued
- Customer requests cancellation
- System error in transaction

#### Steps to Void Sale
1. Locate the **issued sale** in grid
2. Select the sale row
3. Click **"Anular"** (Void) toolbar button
4. A prompt appears: **"Motivo de anulación"** (Reason for cancellation)
5. Enter clear reason (required)
6. Click **OK**
7. System processes void:
   - May request supervisor credentials
   - Creates a credit note (nota de crédito)
   - Updates inventory (returns stock)
   - Marks original invoice as voided
8. Confirmation message appears
9. Sale status changes to **VOIDED**

**Alternative:** Use Credit Note module for more control (see section 9).

#### Business Rules - Voiding
- Only issued invoices can be voided (not drafts)
- Reason is mandatory
- May require supervisor role/PIN
- Creates a linked credit note
- Stock is returned to warehouse
- Cash movement may be required to refund customer
- Voided invoices remain in records (audit trail)

---

## 6. Payment Processing

### 6.1 Payment Methods

The system supports multiple payment methods:

| Payment Method | Code | Description | Usage |
|----------------|------|-------------|-------|
| Cash | `efectivo` | Physical currency | Most common for retail |
| Card | `tarjeta` | Credit/Debit card | POS terminal payments |
| Bank Transfer | `transferencia` | Electronic bank transfer | High-value or B2B sales |
| Check | `cheque` | Bank check | Less common |
| Credit | `credito` | Account credit (pay later) | Approved customers only |
| Mixed | `mixto` | Combination of methods | Partial payments |

### 6.2 Configuring Payment Methods
Payment methods are configured by administrators at: `/payment-method`

As a cashier, you **select** from available methods during sale.

### 6.3 Payment Method Selection in Sale

During sale creation (Step 8 in section 5.3):
1. **Payment Method** dropdown appears
2. Select the method matching customer's payment
3. For **Cash**:
   - Ensure you have sufficient change
   - Amount goes to cash register
4. For **Card**:
   - Process through POS terminal separately
   - Enter transaction as Card in system
5. For **Mixed**:
   - Not fully detailed in simple sale flow
   - May require custom handling or split

### 6.4 Cash Handling Best Practices
- Count cash in front of customer
- Announce amount received and change given
- Use counterfeit detection tools for large bills
- Record the payment method correctly
- If short on change, record as partial payment or ask customer

### 6.5 Payment Reconciliation
- Cash payments increase cash register balance
- Non-cash payments (card, transfer) tracked separately
- At closing, count physical cash only
- Card receipts should match card payment totals
- Transfers should have bank confirmation

---

## 7. Product Information

### 7.1 Accessing Product Information

**URL Path:** `/product`

#### Product Module Features
As a cashier, you can:
- **Search** products by code, name, category
- **View** product details (price, stock, description)
- **Consult** stock levels across warehouses
- **Check** pricing from different price lists

You **cannot** (without admin role):
- Create new products
- Edit product details
- Delete products
- Adjust inventory

### 7.2 Searching for Products

#### In Sales Form
1. When adding a line item, type in product field
2. Autocomplete suggests matching products
3. Filter by code or name
4. Select from results

#### In Product Module
1. Navigate to **Products** in sidebar
2. Use grid search/filter tools:
   - **Search bar** at top: searches name, code, description
   - **Column filters**: click filter icon in column header
   - **Category filter**: filter by product category
3. View results in grid

### 7.3 Product Details

#### Product Grid Columns
- **Code** (`codigo`): Internal product code/SKU
- **Name** (`nombre`): Product name
- **Category**: Product category/family
- **Unit**: Unit of measure (pieces, kg, liters, etc.)
- **Price**: Selling price (from default price list)
- **Stock**: Available quantity in selected warehouse
- **Min Stock**: Minimum stock alert level
- **Status**: Active/Inactive

#### Viewing Product Detail
1. Select a product row
2. Click **"Ver"** (View) or double-click
3. Detail view shows:
   - Full description
   - Image/photo
   - Prices from all price lists
   - Stock per warehouse (kardex)
   - Recent movements

### 7.4 Stock Availability

#### Checking Stock Before Sale
- When adding product to sale, system displays available stock
- If quantity requested exceeds stock:
  - Warning/error message appears
  - Cannot complete sale (unless override permission)
- Stock types:
  - **Physical stock**: Actual units in warehouse
  - **Available stock**: Physical minus reserved

#### Multi-Warehouse Stock
- Products may be in multiple warehouses
- Select correct warehouse in sale to see accurate stock
- Transfer requests may be needed if stock in different warehouse

### 7.5 Pricing and Price Lists

#### Price Lists
- **Retail**: Standard customer pricing
- **Wholesale**: Bulk/distributor pricing
- **Special**: Promotional or custom pricing

#### Viewing Prices
- Select price list in sale to see those prices
- Product detail view shows all prices
- Prices include or exclude tax based on config

#### Price Overrides
- Some users can manually override unit price
- Cashiers typically cannot (restricted)
- Use discount field instead

---

## 8. Customer Management

### 8.1 Customer Module Overview

**URL Path:** `/customer`

Customers (clients) represent buyers in sales transactions. Accurate customer data is essential for invoicing and SUNAT compliance.

### 8.2 Customer Information Fields

| Field | Type | Required | Description | Validation |
|-------|------|----------|-------------|------------|
| `ruc` | String (11) | Yes | Tax ID (RUC or DNI) | 11 digits for RUC, 8 for DNI |
| `nombre` | String | Yes | Full name or business name | Cannot be blank |
| `direccion` | String | Yes | Physical address | Required for invoices |
| `email` | Email | No | Email address | Valid email format |
| `telefono` | String | No | Phone number | - |
| `tipo_documento` | Enum | Yes | Document type | RUC, DNI, Passport, etc. |

### 8.3 Creating a New Customer

#### From Customer Module
1. Navigate to **Customers** (`/customer`)
2. Click **"Nuevo"** (New) button
3. Fill customer form:
   - **RUC/DNI**: Enter tax ID
   - **Name**: Full name or business name
   - **Address**: Complete street address
   - **Email**: (optional but recommended for emailing invoices)
   - **Phone**: Contact number
4. Click **"Guardar"** (Save)
5. Customer appears in grid

#### From Sales Form (Quick Add)
When creating a sale and customer doesn't exist:
1. In Customer field, click **"Nuevo Cliente"** button
2. Quick customer form opens as popup
3. Fill required fields (RUC, name, address)
4. Click **"Guardar"**
5. New customer is created and auto-selected in sale

### 8.4 Searching for Customers

#### In Sales
- Type in customer field
- Search by name, RUC, or DNI
- Autocomplete shows matches
- Select customer

#### In Customer Module
- Use grid search bar
- Filter by columns
- Export customer list if needed

### 8.5 Default Customer: "CLIENTE VARIOS"

For quick sales where customer details are not required:
- Use generic customer: **"CLIENTE VARIOS"** (Various Customers)
- Pre-configured in system
- Suitable for small cash sales, walk-in customers
- Compliant for receipts (boletas), but not for invoices (facturas)

**When to use:**
- Small retail sales
- Customer doesn't want invoice
- No customer account needed

**When NOT to use:**
- Customer requests invoice
- B2B sales
- Credit sales
- Customer wants receipt emailed

### 8.6 Customer Validation Rules

#### RUC Validation
- Must be 11 digits for businesses
- Format checked against SUNAT rules
- Duplicate RUC not allowed

#### DNI Validation
- Must be 8 digits for individuals
- Used for personal invoices

#### Email Validation
- Must be valid email format
- Used for sending digital invoices

---

## 9. Credit Notes and Cancellations

### 9.1 Credit Note Overview

**URL Path:** `/credit-note`

Credit notes (notas de crédito) are fiscal documents issued to:
- Cancel an invoice
- Correct errors in an invoice
- Process returns or refunds
- Adjust amounts

### 9.2 When to Issue a Credit Note

Use credit notes for:
- **Total cancellation**: Customer returns all items, full refund
- **Partial return**: Customer returns some items
- **Price correction**: Invoice had wrong price
- **Error correction**: Wrong customer, product, or amount
- **Discounts after sale**: Applied retroactively

### 9.3 Creating a Credit Note

#### Steps
1. Navigate to **Credit Notes** module (`/credit-note`)
2. Click **"Nuevo"** (New) button
3. Credit note form opens:

**Required Fields:**
| Field | Type | Description |
|-------|------|-------------|
| `movimiento_id` | Reference | Original sale/invoice to cancel |
| `tipo_nota` | Dropdown | Type of note (cancellation, correction, etc.) |
| `motivo` | Text | Reason for credit note |
| `fecha` | Date | Issue date |

4. **Select Original Invoice**:
   - Search for the sale to cancel/adjust
   - System validates invoice is issued

5. **Select Note Type**:
   - **Total cancellation**: Voids entire invoice
   - **Partial**: Only some items or amount
   - **Correction**: Error fix

6. **Enter Reason**:
   - Clear explanation required
   - "Customer return", "Billing error", etc.

7. **Specify Items** (if partial):
   - Select which products to credit
   - Enter quantities to return

8. Click **"Guardar"** (Save)

#### Credit Note Processing
- System validates permissions (may need supervisor)
- Creates credit note record
- Links to original invoice
- Stock returns to warehouse (if applicable)
- Cash register may need adjustment (refund recorded)

### 9.4 Sending Credit Note to SUNAT

After creating credit note:
1. Select the credit note in grid
2. Click **"Enviar a SUNAT"** (Send to SUNAT) button
3. System sends credit note electronically
4. Wait for SUNAT response
5. Status updates to **Accepted** or **Rejected**

**Important:** Credit note must be sent to SUNAT within regulatory timeframe.

### 9.5 Viewing and Printing Credit Notes

- Select credit note in grid
- Click **"Ver"** (View) to open PDF
- Print or email to customer
- Credit note mirrors invoice format but marked as "NOTA DE CRÉDITO"

### 9.6 Business Rules - Credit Notes

- Cannot create credit note for draft sales (must be issued invoice)
- Reason is mandatory
- May require supervisor authorization
- Credit note has its own series and number
- Links to original invoice for audit trail
- Stock is returned based on credit note type
- Refund to customer should be recorded as cash movement (if applicable)

---

## 10. Reports and Consultations

### 10.1 Sales Consultation Module

**URL Path:** `/sale-consult`

The sales consultation module provides various reports to analyze sales performance, payments, and daily activity.

### 10.2 Available Report Types

#### 10.2.1 Detailed Sales Report
Lists individual sales transactions with full details.

**Fields/Columns:**
- Date range
- Sale ID
- Customer
- Products sold
- Quantities
- Amounts
- Payment methods
- Invoice numbers

**Use:** Review specific transactions, find a sale, audit

#### 10.2.2 Daily Payment Method Report
Summarizes sales grouped by payment method for a specific day.

**Information:**
- Date
- Payment method (Cash, Card, etc.)
- Number of transactions
- Total amount per method

**Use:** Cash reconciliation, compare to POS terminal totals

#### 10.2.3 Daily Summary Report
High-level overview of sales for the day.

**Metrics:**
- Total sales amount
- Number of transactions
- Average ticket size
- Sales by category
- Top products

**Use:** Quick daily performance check

### 10.3 Generating a Report

#### Steps
1. Navigate to **Sales Consult** (`/sale-consult`)
2. Select **Report Type** from tabs or dropdown
3. Set **Date Range**:
   - Start date
   - End date
   - Or select predefined: Today, This Week, This Month
4. Apply **Filters** (optional):
   - Customer
   - Payment method
   - Product category
   - Warehouse
5. Click **"Generar"** (Generate) or **"Buscar"** (Search)
6. Report displays in grid or chart
7. **Export** to Excel/PDF if needed (toolbar button)

### 10.4 Cash Register Reports

**URL Path:** `/cash-register-report`

#### Cashbox Report
Overview of cash register activity:
- Shifts opened/closed
- Total sales per register
- Cash movements
- Balances

#### Closure Report
Detailed report for a specific shift closure:
- Opening and closing balances
- Sales breakdown by payment method
- Movements (incomes/expenses)
- Differences and notes

**Access:**
- After closing cash register
- URL: `/cash-register/{id}/closure-report`
- Print for records

### 10.5 Using Reports for Daily Tasks

#### Morning Routine
- Check previous day's closure report
- Verify no pending issues

#### During Shift
- Use detailed sales report to find specific transactions
- Check daily summary for current performance

#### End of Day
- Generate payment method report before closing
- Compare totals to physical cash/card receipts
- Use as guide for closure counting

---

## 11. AI Assistant Usage

### 11.1 AI Assistant Overview

The AI Assistant is an intelligent help tool available throughout the system. It can answer questions, provide guidance, and retrieve information using specialized tools.

**Icon:** 🤖 (Robot icon in header)

### 11.2 Accessing the Assistant

#### Opening the Assistant
1. Click the **robot icon** (🤖) in the top header bar
2. Assistant popover opens (does not block the page)
3. Chat interface appears

#### Closing the Assistant
- Click robot icon again, or
- Click outside the popover, or
- Click close (X) in popover

### 11.3 How to Use the Assistant

#### Asking Questions
1. Type your question in the chat input (in Spanish or English)
2. Press **Enter** or click **Send**
3. Assistant processes your request
4. Response appears in chat

#### Example Questions
- "¿Cuál es el stock del producto X?" (What is the stock of product X?)
- "¿Cuánto vendí hoy?" (How much did I sell today?)
- "¿Está abierta mi caja?" (Is my cash register open?)
- "Busca producto código 12345" (Search product code 12345)
- "Muéstrame las últimas 5 ventas" (Show me the last 5 sales)

### 11.4 Assistant Capabilities (Tools)

The assistant can:
1. **Search products** by code or name
2. **Check stock** levels
3. **Retrieve recent sales**
4. **Summarize sales** for a date range
5. **Check cash register status** (open/closed, balance)
6. **Provide guidance** on how to perform tasks

### 11.5 Assistant Limitations

The assistant **cannot**:
- Perform actions (create sales, open cash register) - it's advisory only
- Access data without permission (respects user role)
- Guess or make up data (if tool fails, it will say so)
- Modify system settings

### 11.6 Tips for Effective Assistant Use

- **Be specific**: "Check stock of product 'Laptop XYZ' in warehouse 1"
- **Use natural language**: Ask as you would ask a colleague
- **Provide context**: "I need to find a sale from yesterday for customer Juan Pérez"
- **Follow up**: If answer is unclear, ask for clarification
- **Language**: Assistant understands Spanish primarily, but can handle English

### 11.7 When to Use the Assistant

- Quick information lookup during a sale
- When you're unsure how to perform a task
- To avoid navigating away from current screen
- For training and learning system features

---

## 12. Daily Workflow Guide

### 12.1 Morning Routine (Start of Shift)

#### Step 1: Login
- Access system with credentials
- Select branch if prompted

#### Step 2: Open Cash Register
- Navigate to Cash Register Dashboard
- Count opening cash
- Click "Abrir Caja"
- Enter opening balance accurately
- Confirm opening

#### Step 3: Verify System Readiness
- Check printer is connected and has paper
- Verify QZ Tray is running (if using thermal printer)
- Test print if necessary
- Ensure internet connection is stable

#### Step 4: Review Pending Items
- Check for any pending sales from previous day
- Review notifications
- Check for low-stock alerts

**Duration:** ~10 minutes

### 12.2 During Shift (Active Sales Period)

#### Processing Sales
1. Greet customer
2. Create new sale (click "Nuevo")
3. Select/create customer
4. Add products to sale
5. Apply discounts if applicable
6. Verify total with customer
7. Select payment method
8. Save sale
9. Generate invoice
10. Print/email receipt
11. Process payment (cash/card)
12. Provide change/receipt
13. Thank customer

**Repeat for each customer**

#### Handling Special Situations
- **Out of stock:** Inform customer, suggest alternatives, or offer to order
- **Price query:** Use product search or ask assistant
- **Discount request:** Apply if within limit, or call supervisor
- **Return/exchange:** Process via credit note (see section 9)
- **Payment issue:** Verify amount, check payment method, call supervisor if needed

#### Recording Cash Movements
- **Bank deposit:** Record as expense movement
- **Petty cash:** Record as expense with description
- **Change brought in:** Record as income movement

### 12.3 Mid-Shift Tasks

#### Periodic Checks
- Review sales total so far (use daily summary)
- Count cash in register periodically (informal check)
- Ensure thermal paper is sufficient
- Address any error messages or system issues

#### Break Procedure
- If taking a break, inform supervisor
- Do **not** close cash register (unless shift ends)
- Lock screen or log out if leaving terminal unattended

### 12.4 End of Shift (Closing Routine)

#### Step 1: Complete All Pending Sales
- Ensure no sales are in draft status (or note them)
- Generate all pending invoices
- Clear any errors

#### Step 2: Count Cash and Receipts
- Separate cash by denomination
- Count total cash in register
- Organize card receipts by transaction
- Prepare bank deposit if applicable

#### Step 3: Generate Pre-Closure Reports
- Daily payment method report
- Daily summary
- Compare totals to physical counts

#### Step 4: Record Final Movements
- If depositing cash to bank, record as expense movement
- Any last-minute expenses or incomes

#### Step 5: Close Cash Register
- Navigate to closure screen
- Enter counted amounts for each payment method
- Note any differences
- Provide explanations for variances
- Confirm closure

#### Step 6: Print Closure Report
- Print closure report for records
- Attach card receipts and deposit slips
- Submit to supervisor or accounting

#### Step 7: Secure Cash and Equipment
- Place cash in safe or prepare bank deposit
- Turn off/log out of terminal
- Secure physical space

**Duration:** ~20-30 minutes

### 12.5 Weekly/Periodic Tasks

- Review weekly sales trends
- Check inventory levels for frequently sold items
- Update customer information if needed
- Attend training on new features

---

## 13. Business Rules and Validations

### 13.1 Cash Register Rules

| Rule | Description | Enforcement |
|------|-------------|-------------|
| One active shift per user | Cashier can only have one open shift at a time | System blocks opening second shift |
| Cash register must be open for sales | Cannot create sales without open shift | Error message on save |
| Opening balance required | Must enter opening amount to open shift | Field validation |
| Counted amounts required at close | All payment methods must be counted | Cannot close until filled |
| Immutable closures | Closed shifts cannot be reopened or edited | System enforced |

### 13.2 Sales Rules

| Rule | Description | Enforcement |
|------|-------------|-------------|
| Customer required | Every sale must have a customer | Form validation |
| Warehouse required | Sale must specify source warehouse | Form validation |
| At least one product | Sale must include items | Cannot save empty sale |
| Stock validation | Quantity cannot exceed available stock | Error on save |
| Price list required | Prices must come from valid price list | Form validation |
| Payment method required | Must select payment type | Form validation |
| Immutable invoices | Issued invoices cannot be edited | Use credit note for changes |

### 13.3 Discount Rules

| Rule | Description | Enforcement |
|------|-------------|-------------|
| Cashier discount limit | Cashiers can apply up to X% discount | System-defined threshold |
| Supervisor authorization | Discounts above limit require supervisor PIN | Popup prompt |
| Discount range | 0-100% only | Field validation |
| Line vs. sale discount | Can apply to line item or entire sale | Different fields |

### 13.4 Inventory Rules

| Rule | Description | Enforcement |
|------|-------------|-------------|
| Stock deduction on sale | Sale deducts from warehouse stock | Automatic |
| Stock return on credit note | Credit note returns stock to warehouse | Automatic |
| No manual stock edits | Cashiers cannot adjust inventory directly | Permission-based |
| Stock type enforcement | System respects stock control type (none, batch, serial) | Validation |

### 13.5 Customer Validation Rules

| Rule | Description | Enforcement |
|------|-------------|-------------|
| RUC format | 11 digits for RUC | Field validation |
| DNI format | 8 digits for DNI | Field validation |
| Email format | Valid email syntax | Field validation |
| Address required for invoice | Invoices need complete address | Form validation |
| Unique RUC | Cannot duplicate RUC in system | Database constraint |

### 13.6 Invoice and Receipt Rules

| Rule | Description | Enforcement |
|------|-------------|-------------|
| Sequential numbering | Voucher numbers must be sequential | System auto-increment |
| SUNAT compliance | Invoices must meet SUNAT format | Template enforcement |
| Electronic signature | Invoices are digitally signed | Automatic |
| No deletion | Issued invoices cannot be deleted | System enforced |
| Credit note for voids | Must use credit note to cancel | Workflow enforced |

### 13.7 Payment Method Rules

| Rule | Description | Enforcement |
|------|-------------|-------------|
| Cash to register | Cash payments increase register balance | Automatic |
| Non-cash tracking | Card/transfer tracked separately | Recorded in closure |
| Mixed payments | System supports split payments | Field available |

---

## 14. Troubleshooting

### 14.1 Common Errors and Solutions

#### Error: "Cash register must be open"
**Cause:** Attempting to create sale without open cash shift  
**Solution:**
1. Navigate to Cash Register Dashboard
2. Open your cash register
3. Return to sales and try again

#### Error: "Insufficient stock"
**Cause:** Quantity requested exceeds available stock  
**Solution:**
1. Check available stock for product
2. Reduce quantity to available amount
3. Or select different warehouse with stock
4. Or inform customer and suggest alternative

#### Error: "Customer is required"
**Cause:** Sale form submitted without customer  
**Solution:**
1. Select customer from dropdown
2. Or create new customer
3. Or use "CLIENTE VARIOS" for generic sale

#### Error: "Unauthorized discount"
**Cause:** Discount exceeds cashier limit  
**Solution:**
1. Request supervisor to authorize
2. Supervisor enters PIN/credentials
3. Or reduce discount to within limit

#### Error: "Cannot close shift - counted amounts missing"
**Cause:** Some payment methods not counted  
**Solution:**
1. Review closure grid
2. Enter counted amount for each payment method
3. Ensure no fields are empty/undefined
4. Try closing again

#### Error: "Invoice already issued"
**Cause:** Attempting to edit or delete issued invoice  
**Solution:**
- Use credit note to cancel/adjust
- Cannot edit issued invoices directly

### 14.2 System Performance Issues

#### Slow Loading
- Check internet connection
- Refresh page (F5)
- Clear browser cache
- Contact IT support if persistent

#### Page Not Responding
- Wait a moment (may be processing)
- Check browser console for errors (F12)
- Try different browser
- Report to supervisor

### 14.3 Printer Issues

#### QZ Tray Not Connected
**Solution:**
1. Verify QZ Tray is running (system tray icon)
2. Restart QZ Tray application
3. Check printer is powered on and connected
4. Refresh browser page
5. Try printing again

#### Thermal Printer Not Printing
**Solution:**
1. Check printer is default printer
2. Verify paper is loaded
3. Check printer status (Windows: Devices and Printers)
4. Test print from Windows to verify hardware
5. Restart printer and QZ Tray

#### Print Formatting Issues
**Solution:**
- Use thermal printer template (80mm)
- Or switch to PDF view and print A4
- Contact administrator to adjust template

### 14.4 Login and Access Issues

#### Cannot Login
**Solution:**
1. Verify username and password (case-sensitive)
2. Check Caps Lock is off
3. Try password reset (if available)
4. Contact administrator

#### "Forbidden" or "Unauthorized" Errors
**Cause:** User role lacks permission for action  
**Solution:**
- Verify you have CASHIER role assigned
- Some actions require SUPERVISOR or ADMIN
- Contact administrator if permissions seem incorrect

### 14.5 Data Not Appearing

#### Sales Not Showing in Grid
**Solution:**
1. Check date filters (may be filtering out your data)
2. Clear filters and search
3. Refresh grid
4. Verify sales were saved successfully

#### Customer Not Found
**Solution:**
1. Try different search term (RUC vs. name)
2. Check spelling
3. Customer may need to be created
4. Ask supervisor if customer should exist

### 14.6 When to Escalate

Contact supervisor or IT support for:
- System crashes or errors persisting after refresh
- Data loss or corruption
- Security concerns
- Hardware failures (printer, terminal)
- Unusual financial discrepancies
- SUNAT communication errors

---

## 15. Keyboard Shortcuts and Tips

### 15.1 General Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl + S` | Save form (in some screens) |
| `Ctrl + P` | Print page |
| `Ctrl + F` | Find in page |
| `Esc` | Close dialog/popup |
| `Tab` | Move to next field |
| `Shift + Tab` | Move to previous field |
| `Enter` | Confirm/submit (in forms) |
| `F5` | Refresh page |

### 15.2 Grid Shortcuts (DevExtreme)

| Shortcut | Action |
|----------|--------|
| `Arrow keys` | Navigate between cells |
| `Enter` | Edit selected row |
| `Delete` | Delete selected row (if allowed) |
| `Ctrl + Click` | Multi-select rows |
| `Shift + Click` | Select range of rows |

### 15.3 Productivity Tips

#### Search Efficiently
- Use wildcard searches: `*laptop*` finds all with "laptop"
- Search by partial code: `ABC*` finds all codes starting ABC

#### Form Navigation
- Use Tab to move through fields quickly
- Set default values for frequently used fields (ask admin)

#### Batch Operations
- Multi-select in grids for batch actions (print multiple invoices)
- Use clone feature for repeat customers

#### Assistant Quick Access
- Keep assistant open in popover for quick questions
- Use assistant while filling forms

#### Date Filters
- Use predefined ranges (Today, This Week) for speed
- Set date formats to match your preference

### 15.4 Best Practices

#### Data Entry
- **Double-check** customer RUC/DNI before saving
- **Verify** product codes and quantities
- **Review** totals before finalizing sale
- **Use** clear, descriptive notes and comments

#### Error Prevention
- **Count** opening and closing cash carefully
- **Reconcile** payment methods before closing
- **Print** closure report for records
- **Document** any irregularities

#### Security
- **Lock** screen when leaving terminal
- **Log out** at end of shift
- **Do not share** credentials
- **Report** suspicious activity

#### Customer Service
- **Greet** customers warmly
- **Process** sales efficiently
- **Explain** charges and receipts
- **Thank** customers for their business

---

## Appendix A: Glossary

| Term | Spanish | Definition |
|------|---------|------------|
| Cash Register | Caja | Physical or virtual register for cash handling |
| Cash Shift | Turno de Caja | Period between opening and closing cash register |
| Cashier | Cajero/Cajera | User role for point-of-sale operations |
| Credit Note | Nota de Crédito | Fiscal document to cancel/adjust invoices |
| Invoice | Factura | Fiscal document for B2B or high-value sales |
| Receipt | Boleta | Fiscal document for retail sales |
| RUC | RUC | Tax ID number (11 digits) |
| DNI | DNI | National ID number (8 digits) |
| SUNAT | SUNAT | Peru's tax authority |
| Warehouse | Almacén | Stock location |
| Price List | Lista de Precios | Pricing tier/catalog |
| IGV | IGV | Value-added tax (VAT) in Peru |
| Voucher | Comprobante | Generic term for fiscal documents |
| Draft | Borrador | Unsaved/unissued sale |
| Issued | Emitido | Finalized and sent to SUNAT |
| Voided | Anulado | Cancelled invoice |

---

## Appendix B: Quick Reference Card

### Opening Cash Register
1. Cash Register Dashboard
2. Click "Abrir Caja"
3. Enter opening balance
4. Confirm

### Creating a Sale
1. Sales → "Nuevo"
2. Select customer
3. Select warehouse
4. Add products
5. Select payment method
6. Save
7. Generate invoice
8. Print

### Closing Cash Register
1. Complete all sales
2. Count cash and receipts
3. Cash Register → "Cerrar"
4. Enter counted amounts
5. Note differences
6. Confirm closure
7. Print report

### Processing a Return
1. Credit Notes → "Nuevo"
2. Select original invoice
3. Select note type
4. Enter reason
5. Save
6. Send to SUNAT

### Using AI Assistant
1. Click 🤖 icon
2. Ask question in chat
3. Review response
4. Close popover

---

## Appendix C: Contact and Support

### Technical Support
- **IT Help Desk:** [contact details]
- **System Administrator:** [contact details]

### Business Support
- **Supervisor:** [contact details]
- **Accounting:** [contact details]

### Emergency Procedures
- **System Down:** [procedure]
- **Cash Discrepancy:** [procedure]
- **Customer Dispute:** [procedure]

---

## Document Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | Dec 2024 | System Team | Initial cashier manual for AI assistant |

---

**End of Cashier User Manual**

This manual is designed as a comprehensive knowledge base for the AI assistant to help cashier users with daily operations. It covers all aspects of cashier functionality, business rules, workflows, and troubleshooting.
