# ISRAPHIL Water Station System - Overall System Document

## 1. System Overview

The ISRAPHIL Water Station System is a role-based web application for managing water station operations. It helps the business handle customer orders, delivery preparation, delivery assignment, inventory monitoring, staff management, customer records, exceptions, and simple reports.

The system has three main user roles:

- Admin
- Staff or rider
- Customer

The admin side focuses on operations control. The customer side focuses on placing and tracking orders. The staff side focuses on assigned deliveries and delivery status updates.

## 2. Purpose of the System

The purpose of the system is to make daily water delivery operations easier to monitor and manage.

The system helps the business:

- record customer orders
- track order status
- prepare delivery batches
- assign riders or staff
- monitor delivery progress
- handle cancelled, failed, and returned orders
- monitor inventory stock levels
- manage staff and customer accounts
- provide simple operational reports

## 3. User Roles

### 3.1 Admin

The admin is responsible for managing the whole operation.

Main responsibilities:

- view dashboard statistics
- manage orders
- prepare and monitor deliveries
- handle exceptions
- manage inventory
- manage staff accounts
- manage customer records
- view reports

Admin pages:

- Dashboard
- Orders
- Deliveries
- Exceptions
- Inventory
- Staff
- Customers
- Reports

### 3.2 Staff or Rider

The staff user is responsible for delivery work.

Main responsibilities:

- view assigned deliveries
- check customer delivery details
- update delivery progress
- mark deliveries as delivered or failed

### 3.3 Customer

The customer uses the system to place and track orders.

Main responsibilities:

- register an account
- log in
- place water orders
- view current and previous orders
- update profile details
- cancel allowed orders
- track loyalty progress

## 4. Admin Module Structure

### 4.1 Dashboard

The dashboard is the admin overview page. It should answer: what needs attention right now?

Important dashboard information:

- pending orders
- ready-to-batch orders
- active deliveries
- exceptions
- low stock items
- today's orders
- delivered orders today
- cancelled orders today
- recent orders
- recent delivery activity
- simple charts for order status and today's delivery outcomes

### 4.2 Orders

The Orders page is used to manage the order flow.

Main functions:

- view all orders
- filter orders by status
- search orders by customer
- view order details
- confirm orders
- cancel orders
- track payment, order, and delivery status

### 4.3 Deliveries

The Deliveries section handles delivery preparation and assignment.

Main functions:

- view scheduled deliveries
- generate draft batches
- add orders to a batch
- remove orders from a batch
- confirm batches
- cancel batches
- assign riders or staff
- track active and completed delivery batches

### 4.4 Exceptions

The Exceptions page is used for non-standard order and delivery results.

Exception types:

- cancelled orders
- failed deliveries
- returned orders

This page helps the admin review issues that need follow-up or explanation.

### 4.5 Inventory

The Inventory page manages water products and stock.

Main functions:

- add items
- edit stock
- edit price
- set reorder level
- check low stock
- hide or restore items
- discontinue items
- delete allowed items

### 4.6 Staff

The Staff page manages internal users and riders.

Main functions:

- create staff accounts
- edit staff details
- reset staff password
- activate or deactivate staff
- review assigned delivery workload

### 4.7 Customers

The Customers page supports customer account management.

Main functions:

- view customer records
- search customers
- edit customer details
- activate or deactivate accounts
- view order count and loyalty progress

### 4.8 Reports

The Reports page provides simple operational summaries.

Main report areas:

- daily order summary
- delivery completion summary
- cancelled, failed, and returned counts
- low stock summary
- recent order activity

## 5. Main System Workflows

### 5.1 Customer Ordering Workflow

1. Customer logs in.
2. Customer selects an inventory item.
3. Customer enters quantity, address, contact number, delivery date, and notes.
4. System checks stock availability.
5. System creates the order.
6. System reserves stock.
7. Order waits for admin processing or delivery assignment.

### 5.2 Admin Order Processing Workflow

1. Admin opens the Orders page.
2. Admin reviews pending orders.
3. Admin confirms or cancels orders.
4. Confirmed orders become ready for delivery preparation.
5. Orders can be included in delivery batches.

### 5.3 Delivery Batch Workflow

1. Admin opens the Deliveries section.
2. Admin generates or creates a draft batch.
3. Admin adds orders to the batch.
4. Admin confirms the batch.
5. Admin assigns a rider or staff member.
6. Staff handles the assigned delivery work.

### 5.4 Staff Delivery Workflow

1. Staff logs in.
2. Staff views assigned deliveries.
3. Staff checks customer address and order details.
4. Staff updates delivery status.
5. If delivered, the order is completed.
6. If failed or returned, the case becomes an exception for admin review.

### 5.5 Exception Workflow

1. An order is cancelled, failed, or returned.
2. The system records the status.
3. Admin reviews the case on the Exceptions page.
4. Admin decides the next action, such as follow-up, reassignment, or record closure.

## 6. Important Data Used by the System

Main database entities:

- `users`
- `orders`
- `order_items`
- `inventory`
- `deliveries`
- `delivery_batches`
- `delivery_batch_items`
- `loyalty`
- `free_gallon_redemptions`
- `activity_logs`

Important order statuses:

- `pending`
- `confirmed`
- `processing`
- `out_for_delivery`
- `delivered`
- `cancelled`
- `returned`

Important delivery statuses:

- `assigned`
- `in_transit`
- `delivered`
- `failed`
- `returned`

Important inventory statuses:

- `available`
- `out_of_stock`
- `discontinued`

## 7. Dashboard Data Meaning

Dashboard statistics should be clear about their timeframe.

Examples:

- Pending Orders means orders still waiting for action.
- Active Deliveries means deliveries currently assigned or in transit.
- Delivered Today means deliveries completed today.
- Today Delivery Outcomes means delivered, failed, and returned deliveries for the current day.
- Exceptions means cancelled, failed, and returned records that need attention.
- Low Stock means inventory items at or below reorder level.

## 8. Current System Strengths

The system already supports the most important operational needs:

- role-based access
- customer ordering
- admin order management
- delivery batching
- staff delivery dashboard
- inventory monitoring
- exception tracking
- customer and staff management
- simple reporting

## 9. Current Limitations

The system is functional, but some areas can still be improved:

- payment handling is still simple
- proof of delivery upload is not fully used
- notifications are not yet implemented
- reporting can be expanded later
- delivery exception resolution can become more detailed
- reusable admin layout files can reduce repeated sidebar code

## 10. Summary

The ISRAPHIL Water Station System is an operations-focused system for managing water delivery orders from customer request to delivery completion. Its main value is that it connects ordering, inventory, delivery assignment, exception tracking, and reporting into one admin-controlled workflow.

The admin dashboard and main operation pages should stay clear, direct, and useful because the admin needs to make fast decisions during daily business operations.
