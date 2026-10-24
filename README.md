# HR Management & Recruitment System

Laravel 12 API for the basic recruitment flow: authentication, candidate profiles, jobs, applications, interviews, exams, and offers.

## Requirements

- PHP 8.2+
- Composer
- MySQL 8+ or MariaDB (XAMPP is enough on Windows)
- Node.js/npm only if frontend assets are needed

## 📚 Project Documentation
- **[Frontend Integration & Workflow Guide](docs/FRONTEND_INTEGRATION_AND_WORKFLOW_GUIDE.md)**: API contracts, authentication, response envelopes, and end-to-end flow.
- **[Full Architecture & API Audit](PROJECT_FULL_ARCHITECTURE_AND_API_AUDIT.md)**: Current routes, database design, and implementation status.

## 🚀 Quick Setup

1. **Clone the repository & install dependencies:**
   ```bash
   git clone -b feature/database-migrations https://github.com/itstechn-source/hr_managment_system.git
   cd hr_managment_system
   composer install
   npm install
   ```

2. **Configure environment:**
   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

3. **Run database migrations:**
   ```bash
   php artisan migrate --seed
   ```

4. **Run automated test suite:**
   ```bash
   php artisan test
   ```

5. **Start the development server:**
   ```bash
   php artisan serve --host=127.0.0.1 --port=8000
   ```

Create a MySQL database named `recruitment_platform` before running migrations and confirm the `DB_*` values in `.env`.

Demo accounts after seeding:

- Admin: `admin@example.com` / `ChangeMe123!`
- Company: `company@example.com` / `Password123!`
- Candidate: `candidate@example.com` / `Password123!`
- Interviewer: `interviewer@example.com` / `Password123!`

Open the API at `http://127.0.0.1:8000/api` and the documentation at `http://127.0.0.1:8000/docs/api`.
