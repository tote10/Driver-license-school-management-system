-- Write your SQL code here!
DROP DATABASE IF EXISTS driver_license_school_db;

CREATE DATABASE driver_license_school_db DEFAULT 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE driver_license_school_db;
CREATE TABLE branches(
    branch_id INT AUTO_INCREMENT PRIMARY KEY,
    name varchar(255) not null,
    address text,
    contact_phone varchar(20),
    contact_email varchar(255),
    created_at datetime default CURRENT_TIMESTAMP,
    updated_at datetime default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP
);
create table users(
    user_id int AUTO_INCREMENT PRIMARY KEY,
    username varchar (100) UNIQUE NOT NULL,
    email varchar(255) UNIQUE NOT NULL,
    password_hash VARCHAR (255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role VARCHAR(30) NOT NULL,
    branch_id INT,
    status VARCHAR(30) DEFAULT 'pending',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    last_login datetime,
    updated_at datetime default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
    FOREIGN KEY(branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL,
    INDEX idx_branch_id(branch_id)
);
CREATE TABLE students (
    user_id INT PRIMARY KEY,
    national_id VARCHAR(100) UNIQUE NOT NULL,
    date_of_birth DATE,
    address TEXT,
    license_category VARCHAR(50),
    registration_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    registration_status VARCHAR(30) DEFAULT 'pending', 
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
CREATE TABLE instructors (
    user_id INT PRIMARY KEY,
    instructor_license_number VARCHAR(100) UNIQUE,
    years_experience INT,
    specialization VARCHAR(255),
    qualifications TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
CREATE TABLE training_programs (
    program_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    theory_duration_hours INT,
    practical_duration_hours INT,
    fee_amount DECIMAL(10, 2) NOT NULL,
    branch_id INT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_tp_branch_id (branch_id),
    INDEX idx_tp_created_by (created_by)
);

CREATE TABLE enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT,
    program_id INT,
    enrollment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    approval_status VARCHAR(30) DEFAULT 'pending',
    approved_by INT,
    approved_date DATETIME,
    current_progress_status VARCHAR(30) DEFAULT 'enrolled',
    last_progress_update DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_user_id) REFERENCES students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES training_programs(program_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_enr_student_id (student_user_id),
    INDEX idx_enr_program_id (program_id),
    INDEX idx_enr_approved_by (approved_by)
);

CREATE TABLE instructor_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT,
    instructor_user_id INT,
    assigned_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT,
    status VARCHAR(30) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_user_id) REFERENCES students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_user_id) REFERENCES instructors(user_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_asgn_student_id (student_user_id),
    INDEX idx_asgn_instructor_id (instructor_user_id),
    INDEX idx_asgn_assigned_by (assigned_by)
);
