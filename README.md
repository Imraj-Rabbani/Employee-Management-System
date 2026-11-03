# HRM & Payroll

Laravel rebuild of the erp2all HRM, Payroll and Workflow modules.
Specs live one level up: `../PRD.md`, `../Architecture.md`, `../Tasks.md`, `../schema.mmd`.

## Stack

PHP 8.4 · Laravel 12 · Livewire 4 + Flux + Volt · Tailwind 4 · MySQL 8 · Pest 4

## Local setup (MAMP)

```bash
# use MAMP's PHP 8.4 (Homebrew's default php is 8.2)
alias php=/Applications/MAMP/bin/php/php8.4.15/bin/php

composer install
cp .env.example .env && php artisan key:generate
# create databases ems_clients (app) and hrm_testing (tests) in MySQL
php artisan migrate
npm install && npm run build

composer run dev      # serve + queue + vite
php vendor/bin/pest   # tests
```
