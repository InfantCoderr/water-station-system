# ISRAPHIL Water Station System Documentation

## 1. System Overview

The ISRAPHIL Water Station System is a role-based web application for managing water gallon delivery operations. It supports three main users: administrator, staff, and customer. The system is designed to help a local water station handle customer registration, order placement, delivery assignment, inventory monitoring, and loyalty tracking in one workspace.

At its current stage, the system already functions as a working MVP. The major operational flows are present and connected to the database, especially for order processing, delivery management, and stock control.

## 2. Purpose of the System

The main purpose of the system is to digitize the day-to-day workflow of a water station by replacing manual tracking with an organized online platform.

The system aims to:

- manage customer accounts and delivery details
- accept customer water orders online
- assign delivery staff to active orders
- track order and delivery progress
- monitor available inventory and low stock
- reward repeat customers through a loyalty program

## 3. Target Users

### 3.1 Administrator

The administrator is the main operations manager of the system.

Responsibilities:

- monitor overall system activity
- manage customer and staff accounts
- manage inventory records
- review and update order statuses
- assign or reassign staff for deliveries

What the administrator can do:

- log in to the admin dashboard
- view total orders, pending orders, active deliveries, customers, and staff
- open the orders page and update order status
- manually assign staff to an order
- add new inventory items
- edit stock quantity, unit price, and reorder level
- hide, restore, discontinue, or delete inventory items
- create new staff accounts
- edit staff details and activate or deactivate staff
- search, edit, activate, or deactivate customer accounts
- view customer loyalty information and order counts

### 3.2 Staff

The staff user represents the delivery personnel.

Responsibilities:

- check assigned deliveries
- view customer contact and address details
- update delivery outcomes

What the staff can do:

- log in to the staff dashboard
- see assigned, in-transit, delivered, and failed deliveries
- filter deliveries by status
- view delivery date, customer name, phone number, address, item, quantity, and notes
- mark an order as delivered
- mark a delivery as failed and provide a reason

When a staff member marks a delivery as failed, the order returns to pending status so it can be reassigned.

### 3.3 Customer

The customer is the end user who places water orders.

Responsibilities:

- maintain account information
- place orders for available water containers
- track current and previous orders

What the customer can do:

- register a new customer account
- log in to the customer dashboard
- reset password using username and registered email
- place an order for an available inventory item
- choose quantity, delivery date, address, contact number, and notes
- cancel eligible orders
- view recent orders and complete order history
- view assigned staff when available
- update profile information
- change password
- track loyalty progress and available free gallons

## 4. Main System Modules

### 4.1 Authentication Module

This module handles:

- login for admin, staff, and customer
- session-based access control
- customer registration
- password reset

The system redirects users to the correct dashboard based on role after successful login.

### 4.2 Admin Dashboard Module

This module provides a quick operations overview, including:

- total orders
- pending orders
- orders out for delivery
- total customers
- total staff
- inventory summary

This page acts as the admin control center.

### 4.3 Orders Management Module

This module allows the administrator to:

- review all customer orders
- filter orders by status
- update order status
- assign staff to an order

Supported order statuses in the system:

- `pending`
- `confirmed`
- `processing`
- `out_for_delivery`
- `delivered`
- `cancelled`
- `returned` exists in the database structure, but the active UI mainly uses the statuses above except returned as a delivery-related end state

### 4.4 Delivery Management Module

This module is mainly used by staff and partially by admin through order assignment.

Supported delivery statuses:

- `assigned`
- `picked_up`
- `in_transit`
- `delivered`
- `failed`
- `returned`

Current active UI usage mainly focuses on:

- assigned
- in transit
- delivered
- failed

### 4.5 Inventory Module

This module allows the administrator to:

- add inventory items
- edit stock quantity
- edit price
- set reorder levels
- hide or restore items
- delete items with no order history
- mark items with order history as discontinued

Inventory status values:

- `available`
- `out_of_stock`
- `discontinued`

The system automatically updates stock availability after ordering and cancellation.

### 4.6 Customer Management Module

This module allows the administrator to:

- search customer accounts
- view customer details
- edit customer full name, phone number, and address
- activate or deactivate customer accounts
- review loyalty and order data

### 4.7 Staff Management Module

This module allows the administrator to:

- create staff accounts
- update staff account details
- reset staff password during editing
- activate or deactivate staff accounts

### 4.8 Loyalty Module

The loyalty system rewards repeat customers.

Current loyalty rules:

- each delivered order increases total orders
- each delivered order also increases consecutive orders
- every 5 consecutive delivered orders earns 1 free gallon
- cancelling after confirmation or during delivery resets the consecutive order streak
- cancelling while still pending does not apply a loyalty penalty

## 5. Core Workflows

### 5.1 Customer Registration Workflow

1. Customer opens the registration page.
2. Customer fills in username, password, full name, email, phone number, and address.
3. Customer confirms the delivery location is within the service area.
4. System creates a new customer account.
5. System creates the customer loyalty record.

### 5.2 Customer Ordering Workflow

1. Customer logs in.
2. Customer selects an available water container from inventory.
3. Customer enters quantity, delivery address, date, contact number, and notes.
4. System validates the input.
5. System reserves stock immediately.
6. System creates the order and order item record.
7. System attempts automatic staff assignment based on available workload.
8. If staff is available, the order becomes confirmed.
9. If no staff is available, the order remains pending for admin assignment.

### 5.3 Admin Order Processing Workflow

1. Admin reviews the orders page.
2. Admin filters orders if needed.
3. Admin changes order status or assigns a staff member.
4. The system updates both order and delivery records according to the business rules.

### 5.4 Staff Delivery Workflow

1. Staff logs in to the delivery dashboard.
2. Staff views assigned deliveries.
3. Staff checks customer details and delivery notes.
4. Staff marks the delivery as delivered or failed.
5. If delivered, the order is completed and loyalty is updated.
6. If failed, the order is returned to pending and can be reassigned.

### 5.5 Cancellation Workflow

1. Customer cancels an order from the dashboard if the order is still cancellable.
2. The system changes the order to cancelled.
3. Reserved stock is returned to inventory.
4. If the order was already confirmed, processing, or out for delivery, the loyalty streak is reset.

## 6. Business Rules Implemented

The following logic is already implemented in the current system:

- only active users can log in
- role-based access is enforced
- stock is reserved as soon as an order is placed
- discontinued items cannot be ordered
- out-of-stock situations are prevented during order placement
- delivery staff must be active before assignment
- automatic assignment chooses the least busy active staff member
- an order cannot be moved freely once it reaches a final state like delivered or cancelled
- cancelling an order returns reserved stock
- delivered orders increase loyalty progress
- every 5 consecutive delivered orders creates a free gallon reward record

## 7. Main Database Entities

The core database tables used by the system are:

- `users` for admin, staff, and customers
- `orders` for main order records
- `order_items` for ordered items and quantity
- `inventory` for water products and stock
- `deliveries` for delivery assignments and delivery progress
- `loyalty` for customer reward progress
- `free_gallon_redemptions` for earned free gallon records
- `activity_logs` for order status change logging

## 8. Current Stage of the System

The system is best described as a working MVP or capstone-ready prototype with real operational features.

What is already working well:

- multi-role login and access control
- customer self-registration
- customer ordering flow
- staff delivery dashboard
- admin order, inventory, staff, and customer management
- automatic stock deduction and stock return
- automatic and manual staff assignment
- loyalty tracking

## 9. Current Limitations

Although the system is functional, some features appear incomplete or only partially implemented.

Current limitations include:

- online payment exists in the database structure but is not fully implemented in the user interface
- proof of delivery exists in the database but has no active upload feature in the current pages
- free gallon rewards are tracked, but there is no full reward redemption workflow in the visible UI
- reporting and analytics pages are not yet implemented
- notifications through SMS, email, or in-app alerts are not implemented
- the customer order flow currently behaves like a simple single-item order per transaction
- there is no dedicated public landing page or full customer storefront experience beyond login and ordering

## 10. Conclusion

The ISRAPHIL Water Station System already supports the main operational processes of a small water delivery business. At this stage, it is suitable for demonstrating a complete role-based information system with real workflows for ordering, dispatching, inventory control, account management, and customer loyalty.

Its strongest features are the separation of user roles, the connected order-to-delivery workflow, and the automatic handling of stock and loyalty rules. The next improvements should focus on payment handling, reporting, notification features, and polishing the reward redemption process.
