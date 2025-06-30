# Branch-Specific Stock Management Implementation Tasks

## Overview
This document outlines the tasks required to implement branch-specific stock management in the system. Currently, the system tracks product stock at a global level, but we need to extend it to track stock by branch/warehouse.

## Database Changes

1. Create a new `product_stock` entity/table with the following fields:
   - `id` (Primary Key)
   - `product_id` (Foreign Key to Product)
   - `warehouse_id` (Foreign Key to Warehouse)
   - `stock` (Current stock quantity)
   - `min_stock` (Minimum stock threshold for alerts)
   - `created_at` (Timestamp)
   - `updated_at` (Timestamp)

2. Add unique constraint on `product_id` and `warehouse_id` combination to prevent duplicates

## Entity Changes

1. Create a new `ProductStock` entity class with:
   - Properties for all fields in the database table
   - Getters and setters for all properties
   - Relationships to Product and Warehouse entities
   - Appropriate annotations for ORM mapping

2. Modify the `Product` entity:
   - Add a one-to-many relationship to ProductStock
   - Add methods to get stock by warehouse
   - Keep the existing `stock` field for backward compatibility (global stock)

3. Modify the `Warehouse` entity:
   - Add a one-to-many relationship to ProductStock
   - Add methods to get all products in the warehouse

## Repository Changes

1. Create a `ProductStockRepository` with methods for:
   - Finding stock by product and warehouse
   - Finding all stock for a product across warehouses
   - Finding all stock for a warehouse
   - Finding products below minimum stock threshold by warehouse

2. Update `ProductRepository` to include methods that work with the new stock structure

## Service Layer Changes

1. Create a `ProductStockService` with methods for:
   - Getting stock for a product in a specific warehouse
   - Updating stock for a product in a warehouse
   - Transferring stock between warehouses
   - Calculating global stock from warehouse-specific stocks

2. Modify the stock calculation logic in `UpdateStockCommand`:
   - Update to calculate stock per warehouse
   - Maintain backward compatibility by updating the global stock field

3. Update `SaleProcessor` to:
   - Reduce stock from the specific warehouse where the sale occurs
   - Handle cases where stock is insufficient in the selected warehouse

## API Changes

1. Extend the `ProductStockController` to:
   - Support getting stock for all warehouses
   - Support updating stock for specific warehouses

2. Create API endpoints for:
   - Getting all products in a warehouse
   - Getting stock levels across all warehouses
   - Transferring stock between warehouses

## Frontend Changes

1. Update product management screens to:
   - Display stock by warehouse
   - Allow setting minimum stock levels by warehouse

2. Update sales screens to:
   - Show available stock in the selected warehouse
   - Prevent sales if stock is insufficient in the selected warehouse

3. Create new screens for:
   - Stock transfer between warehouses
   - Stock level reporting by warehouse

## Migration Strategy

1. Create a data migration script to:
   - Initialize warehouse-specific stock based on current global stock
   - Distribute existing stock evenly or based on business rules

2. Implement a transition period where both systems work in parallel

## Testing Requirements

1. Unit tests for:
   - ProductStock entity
   - ProductStockRepository
   - ProductStockService

2. Integration tests for:
   - Stock updates during sales
   - Stock transfers between warehouses
   - API endpoints

3. End-to-end tests for critical workflows:
   - Complete sales process with stock reduction
   - Stock transfer process
   - Reporting accuracy

## Documentation

1. Update API documentation to include new endpoints
2. Create user documentation for new stock management features
3. Document the migration process for system administrators
