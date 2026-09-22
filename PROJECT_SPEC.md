# Inventory Management System — Project Specification

You are the lead Laravel developer for this project.

Build a production-quality Inventory Management System using:

* Laravel 13
* PHP 8.5+
* MySQL 8+
* Blade
* Livewire
* Tailwind CSS
* Laravel authentication
* Laravel migrations
* Eloquent ORM
* Form Requests
* Policies/Gates where appropriate
* Database transactions
* PHPUnit/Pest tests where appropriate

The system must be designed from the beginning to support a FUTURE POS system.

## PRIMARY OBJECTIVE

Build a complete inventory management system that can later integrate with a Point-of-Sale system without requiring a major database redesign.

The system must support:

1. Product management
2. Categories
3. Brands
4. Units
5. Product variants
6. Suppliers
7. Warehouses
8. Inventory
9. Inventory movements
10. Stock-in
11. Stock-out
12. Stock adjustments
13. Stock transfers
14. Purchasing
15. Purchase orders
16. Receiving
17. Product batches
18. Expiration tracking
19. Low-stock monitoring
20. Inventory reports
21. User management
22. Roles and permissions
23. Activity logs
24. Dashboard

The future POS will add:

* Sales
* Sale items
* Customers
* Cashiers
* Payments
* Receipts
* Returns

Do NOT build the POS yet.

## IMPORTANT ARCHITECTURAL RULE

Inventory quantities must NOT be modified randomly throughout controllers.

Create a centralized inventory service responsible for inventory-changing operations.

Example:

InventoryService

Responsibilities:

* increase stock
* decrease stock
* adjust stock
* transfer stock
* record inventory movement
* validate sufficient stock
* maintain inventory consistency

All inventory-changing operations must use database transactions.

Example conceptual flow:

Purchase receiving
→ InventoryService
→ increase inventory
→ create inventory movement

Future POS sale
→ InventoryService
→ decrease inventory
→ create inventory movement

Stock adjustment
→ InventoryService
→ adjust inventory
→ create inventory movement

## DATABASE DESIGN

Use proper foreign keys, indexes, unique constraints, nullable fields, timestamps, and appropriate data types.

Core tables should include:

users
roles
permissions
categories
brands
units
products
product_variants
warehouses
inventories
inventory_movements
suppliers
purchase_orders
purchase_order_items
product_batches
stock_transfers
stock_transfer_items
activity_logs

Design the schema so future POS tables can connect cleanly.

## PRODUCT DESIGN

Products should support:

* SKU
* barcode
* name
* description
* category
* brand
* unit
* cost price
* selling price
* reorder level
* status

Product variants should be supported for products that need variants.

Each sellable product/variant should have a unique SKU.

Barcode should be indexed and preferably unique when present.

## INVENTORY DESIGN

Inventory must be tracked per warehouse.

Conceptually:

Product/Variant
→ Warehouse
→ Inventory record

Inventory should support:

* quantity
* reserved quantity if needed
* reorder level

Do not store inventory history only as a number.

Use inventory_movements as the audit trail.

## INVENTORY MOVEMENTS

Every stock change must create an inventory movement.

Supported movement types should include:

PURCHASE
SALE
SALE_RETURN
PURCHASE_RETURN
ADJUSTMENT_IN
ADJUSTMENT_OUT
TRANSFER_IN
TRANSFER_OUT
DAMAGE
EXPIRED

The design must support future POS sales.

Each movement should be traceable to its source transaction using fields such as:

* reference_type
* reference_id

## PURCHASING

Purchase workflow:

Draft
→ Ordered
→ Partially Received
→ Received
→ Cancelled

Creating a purchase order must NOT automatically increase inventory.

Inventory increases only when products are actually received.

## STOCK RECEIVING

When receiving products:

1. Validate the purchase order.
2. Validate received quantities.
3. Prevent receiving more than ordered unless explicitly supported.
4. Update inventory through InventoryService.
5. Create inventory movements.
6. Update purchase receiving status.
7. Use a database transaction.

## STOCK ADJUSTMENTS

Never silently change stock quantities.

An adjustment must record:

* product
* warehouse
* previous quantity
* new quantity
* difference
* reason
* user
* timestamp

The adjustment must create an inventory movement.

## STOCK TRANSFERS

Support:

Warehouse A
→ Warehouse B

A transfer should create:

TRANSFER_OUT from source warehouse

and

TRANSFER_IN to destination warehouse.

Use a database transaction.

## PRODUCT BATCHES

Support optional batch tracking:

* batch number
* product/variant
* warehouse
* quantity
* unit cost
* expiration date

The system should be capable of implementing FEFO later.

## LOW STOCK

Products should be considered low-stock when:

current stock <= reorder level

Provide a dashboard widget and report for low-stock products.

## REPORTS

Prepare reports for:

* Current inventory
* Inventory valuation
* Stock movements
* Low stock
* Expiring products
* Purchases
* Stock adjustments
* Stock transfers

## SECURITY

Use:

* authentication
* authorization
* policies/gates
* validation
* CSRF protection
* mass-assignment protection
* database transactions

Users should only perform actions permitted by their role.

## LIVEWIRE

Use Livewire where interactive behavior provides value, such as:

* product search
* inventory search
* filters
* pagination
* purchase item selection
* receiving
* stock adjustment forms
* dashboard widgets

Do not use Livewire unnecessarily for simple static pages.

Use normal Blade where appropriate.

## UI

Create a clean professional admin dashboard.

Use:

* responsive sidebar
* top navigation
* cards
* tables
* forms
* modal dialogs where useful
* confirmation dialogs
* validation messages
* success/error notifications
* pagination
* search
* filtering

Keep the UI consistent across all modules.

## CODE QUALITY

Follow Laravel conventions.

Use:

* RESTful controllers where appropriate
* Eloquent relationships
* Form Requests
* Policies
* Services for complex business logic
* database transactions
* enums where appropriate
* reusable Blade/Livewire components

Avoid:

* duplicated business logic
* massive controllers
* raw SQL unless genuinely necessary
* modifying unrelated files
* unnecessary dependencies

## IMPORTANT DEVELOPMENT RULE

DO NOT build the entire application at once.

Build one module at a time.

For every module:

1. Explain the architecture briefly.
2. Identify required database changes.
3. Create migrations.
4. Create/update models.
5. Create relationships.
6. Create Form Requests.
7. Create services when required.
8. Create controllers/Livewire components.
9. Create Blade views.
10. Create routes.
11. Add authorization.
12. Add validation.
13. Add tests where appropriate.
14. Run migrations/tests.
15. Fix errors.
16. Summarize what was created.

Before modifying an existing file, inspect the current implementation.

Do not overwrite working functionality unnecessarily.

Never assume a table, column, route, model, component, or package exists.

Inspect the project first.

## FUTURE POS COMPATIBILITY

The inventory system must eventually support this flow:

POS
→ create sale
→ create sale items
→ InventoryService
→ decrease stock
→ create SALE inventory movement
→ complete transaction

Therefore, do not design inventory around purchases only.

The inventory system must be independent enough that future POS, online orders, returns, or other stock-consuming modules can use the same InventoryService.

## DEVELOPMENT ORDER

Build modules in this order:

1. Project foundation
2. Authentication and authorization
3. Categories
4. Brands
5. Units
6. Products
7. Product variants
8. Warehouses
9. Inventory
10. Inventory movements
11. Suppliers
12. Purchasing
13. Receiving
14. Stock adjustments
15. Stock transfers
16. Product batches and expiration
17. Low-stock system
18. Dashboard
19. Reports
20. Activity logs
21. Testing and hardening

After all inventory modules are complete, stop.

Do NOT build the POS unless explicitly instructed.

## CURRENT TASK

Before writing code:

1. Inspect the current Laravel project.
2. Determine what has already been installed.
3. Inspect composer.json and package.json.
4. Inspect existing routes.
5. Inspect existing migrations.
6. Inspect existing models.
7. Inspect existing Blade/Livewire files.
8. Determine the current authentication setup.
9. Identify anything that conflicts with this specification.

Then provide a short implementation plan.

Do not start building every module immediately.

Wait for the specific module instruction.
