# BJS Order Management and Worker Synchronization System

## Overview
This is a Laravel-based web application that serves as a central management system for handling social media engagement orders (primarily Instagram) and worker account management. The system integrates with multiple third-party services, specifically BJS (BelanjaSOSMed) for order management and IndoFoll for worker account provisioning. The unique aspect is that worker applications directly interact with Redis and the database, rather than consuming REST APIs, enabling high-performance and low-latency operations.

## Core Components

### 1. Order Management System
- Handles different types of social media engagement orders (likes, follows)
- Integrates with BJS API for order synchronization
- Uses Redis for real-time order status tracking
- Orders are stored in both PostgreSQL and Redis:
  * PostgreSQL: For persistence and historical data
  * Redis: For real-time processing status and worker coordination
- Supports various order statuses: pending, inprogress, processing, completed, partial, canceled
- Implements priority-based order processing
- Features order refill capability for incomplete orders
- Tracks various metrics including success rate, processing time, and completion status

### 2. Worker Management
- Manages social media worker accounts used for fulfilling orders
- Syncs with IndoFoll to obtain new worker accounts
- Workers directly read/write to Redis and PostgreSQL:
  * Redis: For real-time status updates, locks, and coordination
  * PostgreSQL: For worker account data and long-term storage
- Tracks worker status, health, and capabilities
- Monitors worker metrics (followers, following, media count)
- Implements worker verification and status management
- Features automated worker status updates
- Manages worker locks to prevent concurrent operations

### 3. Data Architecture
- Hybrid storage approach:
  * PostgreSQL: Primary data store for orders, workers, and devices
  * Redis: Real-time coordination and caching layer
- Direct database access by workers for efficiency
- Redis key patterns for different data types:
  * `order:{id}:*` for order processing states
  * `worker:{id}:*` for worker states and locks
  * `system:*` for global configuration
- No REST API layer between workers and data stores
- Optimized for high-throughput and low-latency operations

### 4. Caching and State Management
- Redis serves as both cache and state coordination system
- Key Redis functions:
  * Order processing state management
  * Worker locks and coordination
  * System configuration
  * Cookie persistence for API authentication
  * Real-time metrics
- Direct Redis access by workers enables:
  * Atomic operations for coordination
  * Real-time status updates
  * Efficient worker-to-worker communication
  * Low-latency state changes

### 5. Worker Application Integration
- Workers directly connect to:
  * Redis for real-time coordination
  * PostgreSQL for data persistence
- No API middleware layer, reducing latency
- Uses Redis for distributed locking
- Direct database access for efficient bulk operations
- Real-time state synchronization via Redis
- Optimized for high-volume processing

### Technical Stack
- Laravel 9.x Framework (Web Dashboard)
- PostgreSQL Database (Persistent Storage)
- Redis (Real-time Coordination)
- Docker Containerization
- Queue Workers for Background Processing
- Argon Dashboard UI

### Key Features
- Direct data store access by workers
- Real-time order tracking
- Automated worker management
- Distributed system architecture
- High availability design
- Scalable worker processing
- Comprehensive monitoring
- Fault-tolerant operation
- Priority-based processing
- Detailed analytics and reporting

The system's architecture is optimized for high-performance social media engagement operations, with workers directly accessing data stores instead of using an API layer. This design choice enables extremely low-latency operations and high throughput, while the web dashboard provides comprehensive monitoring and management capabilities. The hybrid use of PostgreSQL and Redis creates a robust and scalable system capable of handling large volumes of concurrent operations.
