# Inventory Management System

A web-based **Inventory Management System** built with **Laravel 13, Livewire, MySQL, and Tailwind CSS**. The system is designed to manage products, inventory, warehouses, suppliers, purchasing, stock movements, transfers, adjustments, batches, and reports from a centralized interface.

The project is being developed as a portfolio project and is designed to serve as the inventory foundation for a future **Point of Sale (POS) System**.

## Features

### Dashboard

* Inventory overview and key metrics
* Stock monitoring
* Inventory summaries
* Quick access to major modules

### Product Management

* Product management
* Product variants
* Categories
* Brands
* Units
* Product status management

### Inventory Management

* Inventory dashboard
* Stock overview
* Stock movement history
* Stock adjustments
* Stock transfers between warehouses
* Reserved stock handling
* Inventory quantity validation
* Prevention of negative inventory

### Batches & Expiration

* Product batch tracking
* Batch numbers
* Expiration dates
* Batch quantity tracking
* Batch-aware inventory movements

### Warehouse Management

* Multiple warehouse support
* Warehouse status management
* Warehouse-specific inventory
* Stock transfers between warehouses

### Purchasing

* Supplier management
* Purchase orders
* Purchase order items
* Partial receiving
* Purchase receipts
* Inventory updates after receiving
* Duplicate receipt protection

### Reports

* Inventory reports
* Stock movement reports
* Purchasing reports
* Warehouse-related reporting

### Activity Logs

* User activity tracking
* Inventory operation auditing
* Important system changes recorded for traceability

### Security & Access Control

* Authentication
* Role and permission management
* Laravel policies and gates
* Login rate limiting
* Input validation
* Transaction-safe inventory operations

## Tech Stack

| Technology   | Purpose                   |
| ------------ | ------------------------- |
| Laravel 13   | Backend framework         |
| PHP 8.3+     | Server-side language      |
| Livewire     | Dynamic user interface    |
| Blade        | Server-rendered templates |
| Tailwind CSS | UI styling                |
| MySQL        | Relational database       |
| Vite         | Frontend asset bundling   |
| Pest         | Automated testing         |
| Laravel Pint | PHP code formatting       |

## System Architecture

The application separates business logic from controllers using service classes.

```text
Request
   │
   ▼
Controller / Livewire Component
   │
   ▼
Authorization & Validation
   │
   ▼
Service Layer
   │
   ▼
Database Transaction
   │
   ▼
Eloquent Models
   │
   ▼
MySQL Database
```

Core business logic is handled by services including inventory, purchasing, receiving, stock adjustment, stock transfer, batch allocation, reporting, and activity logging.

## Inventory Flow

```text
Products
   │
   ▼
Stock Items
   │
   ▼
Inventory
   │
   ├──────── Purchasing
   │
   ├──────── Stock Adjustments
   │
   ├──────── Stock Transfers
   │
   └──────── Future POS
   │
   ▼
Inventory Movements
```

Inventory movements provide a historical record of stock changes including purchases, adjustments, transfers, and future sales transactions.

## Installation

### Requirements

Make sure the following are installed:

* PHP 8.3+
* Composer
* MySQL
* Node.js
* NPM
* Git

### 1. Clone the repository

```bash
git clone https://github.com/EJ-code764/inventory-management-system.git
```

Enter the project directory:

```bash
cd inventory-management-system
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install frontend dependencies

```bash
npm install
```

### 4. Create the environment file

Copy the example environment configuration:

**Windows**

```bash
copy .env.example .env
```

**Linux/macOS**

```bash
cp .env.example .env
```

### 5. Generate the application key

```bash
php artisan key:generate
```

### 6. Configure the database

Create a MySQL database and update `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventory
DB_USERNAME=root
DB_PASSWORD=
```

Adjust the username and password according to your MySQL configuration.

### 7. Run database migrations

```bash
php artisan migrate
```

If the project contains development seeders:

```bash
php artisan db:seed
```

### 8. Build frontend assets

For development:

```bash
npm run dev
```

For a production build:

```bash
npm run build
```

### 9. Start the application

```bash
php artisan serve
```

Open:

```text
http://127.0.0.1:8000
```

## Running Tests

Run the automated test suite with:

```bash
php artisan test
```

The project includes tests covering important inventory and business operations such as:

* Core inventory operations
* Inventory auditing
* Inventory services
* Authentication
* Classifications
* Product batches
* Purchasing
* Stock adjustments
* Stock transfers
* Reports
* Activity logging

## Code Formatting

Laravel Pint is used for PHP code formatting.

Check or format the project with:

```bash
./vendor/bin/pint
```

On Windows:

```bash
vendor\bin\pint
```

## Project Structure

```text
app/
├── Http/
│   ├── Controllers/
│   └── Requests/
├── Livewire/
├── Models/
├── Policies/
├── Services/
└── ...

database/
├── factories/
├── migrations/
└── seeders/

resources/
├── css/
├── js/
└── views/

routes/
└── web.php

tests/
├── Feature/
└── Unit/
```

## Screenshots

Screenshots of the application will be added as development continues.

Recommended screenshots:

* Dashboard
* Products
* Inventory Dashboard
* Stock Overview
* Stock Movements
* Purchase Orders
* Stock Transfers
* Batches & Expiration
* Reports

## Roadmap

Future development may include:

* Point of Sale (POS) module
* Sales transactions
* Sales returns
* Barcode scanning
* Receipt generation
* Customer management
* Payment methods
* Sales reports and analytics
* Low-stock notifications
* Enhanced dashboard analytics
* Deployment and online demo

## POS Integration

The Inventory Management System is designed to become the inventory foundation of a future POS system.

The POS module will use the existing inventory infrastructure rather than maintaining a separate stock database.

```text
Purchase
    │
    ▼
Inventory
    │
    ├──── Stock Transfer
    ├──── Stock Adjustment
    │
    ▼
POS Sale
    │
    ▼
Inventory Movement
```

This allows purchasing, warehouse operations, and future sales transactions to share a consistent inventory source of truth.

## Security

Sensitive environment configuration is stored in `.env` and should never be committed to Git.

Before deploying to production, make sure production configuration uses appropriate settings such as:

```env
APP_ENV=production
APP_DEBUG=false
```

Production credentials, database passwords, application keys, and other secrets must never be stored directly in the repository.

## Status

**Under Active Development**

The inventory management functionality is currently being developed and refined. POS functionality is planned as a future extension.

## Author

**Elly Jay V. Jangco**

GitHub: `EJ-code764`

## License

This project is currently intended for educational, portfolio, and development purposes.
