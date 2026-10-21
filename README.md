# HR Management & Recruitment System

An enterprise-grade HR Management and AI-driven Recruitment Platform built with Laravel 12.

## 📚 Documentation
- **[Database Documentation & Schema Architecture](DATABASE.md)**: Full breakdown of all 55 database tables, ER diagram, audit trail standards (`UID`, `LUID`, `timestamps`, `softDeletes`), account lifecycle states, and Eloquent relationships.

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
   php artisan migrate
   ```

4. **Start the development server:**
   ```bash
   php artisan serve
   ```
