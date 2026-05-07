## Driver License School Management System

This project is a comprehensive management system for driving schools, designed to streamline operations from student registration to license issuance.

## Features

- **Student Management**: Track student information, enrollment status, and progress.
- **Instructor Management**: Manage instructor details and assignments.
- **Course Management**: Create and manage different driving courses.
- **Scheduling**: Schedule theory and practical driving tests.
- **Billing & Payments**: Handle fee calculations and payment tracking.
- **Reporting**: Generate reports on student performance and school operations.

## Getting Started

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache or Nginx)

### Installation

1. Clone the repository:
   ```bash
   git clone <repository-url>
   cd driving-school-license
   ```

2. Create a database and import the SQL file:
   ```sql
   CREATE DATABASE driving_school_license_db;
   USE driving_school_license_db;
   SOURCE path/to/your/database.sql;
   ```

3. Configure the database connection:
   Edit `config/config.php` with your database credentials:
   ```php
   $host = "localhost";
   $user = "root";
   $pass = "";
   $dbname = "driving_school_license_db";
   ```

4. Run the application:
   Place the project files in your web server's document root (e.g., `htdocs` for XAMPP) and access it through your browser.

## Usage

- **Admin Dashboard**: Access `admin/` directory for administrative functions.
- **User Interface**: Access `public/` directory for student and public access.

## Technologies Used

- **Frontend**: HTML, CSS, JavaScript
- **Backend**: PHP
- **Database**: MySQL

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
