# HR Management & Recruitment System

An enterprise-grade HR Management and AI-driven Recruitment Platform built with Laravel 12.

## 📚 Project Documentation
- **[Database Documentation & Schema Architecture](DATABASE.md)**: Full breakdown of all 55 database tables, Mermaid ER diagram, audit trail standards (`UID`, `LUID`, `timestamps`, `softDeletes`), account lifecycle states (`account_statuses`), and Eloquent relationships.
- **[Backend Development & Testing Guide](BACKEND_GUIDE.md)**: Step-by-step roadmap mapping the 6 recruitment workflows, Artisan CLI generator commands, Phase 1 AI-mocking strategy, folder structures, and automated PHPUnit / Feature test suites.

## 🚀 Quick Setup

1. **Clone the repository & install dependencies:**
   ```bash
   git clone https://github.com/itstechn-source/hr_managment_system.git
   cd hr_managment_system
   composer install
   npm install
   ```

2. **Configure environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Run database migrations:**
   ```bash
   php artisan migrate:fresh
   ```

4. **Run automated test suite:**
   ```bash
   php artisan test
   ```

5. **Start the development server:**
   ```bash
   php artisan serve
   ```
