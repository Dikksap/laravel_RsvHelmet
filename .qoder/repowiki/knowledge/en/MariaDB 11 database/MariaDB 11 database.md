---
kind: external_dependency
name: MariaDB 11 database
slug: mariadb
category: external_dependency
category_hints:
    - client_constraint
scope:
    - '**'
---

### MariaDB
- Role: Primary relational store for all application data (items, production batches, stock opnames, item requests, etc.).
- Dev environment: containerized MariaDB 11 on host port 3308, internal name `db`, database `rsv_helmet`, user `laravel` (password from `.env`). Production uses a separate isolated network with persistent volume `rsv_helmet_db_data`.
- Connection is configured through Laravel's standard MySQL connection (`DB_CONNECTION=mysql`) pointing at the container hostname `db` on port 3306.