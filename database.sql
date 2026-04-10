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
    FOREIGN KEY(branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL
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
    instructor_license_number VARCHAR(100) UNIQUE NOT NULL,
    years_experience INT,
    specialization VARCHAR(255),
    qualifications TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
CREATE TABLE training_programs(
    program_id int AUTO_INCREMENT PRIMARY KEY,
    name varchar(255) not null,
    description text,
    theory_duration_hours INT,
    practical_duration_hours INT,
    created_by INT,
    fee_amount decimal(10,2) not null,
    branch_id INT,
    created_at datetime default CURRENT_TIMESTAMP,
    updated_at datetime default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
    FOREIGN KEY(branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL,
    FOREIGN KEY(created_by) REFERENCES users(user_id) ON DELETE SET NULL

);
CREATE TABLE enrollments(
    enrollment_id int AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT,
    program_id INT,
    enrollment_date datetime default CURRENT_TIMESTAMP,
    approval_status VARCHAR(30) DEFAULT 'pending',
    approved_by INT,
    approval_date datetime,
    current_progress_status VARCHAR(30) DEFAULT 'enrolled',
    last_progress_update datetime,
    created_at datetime default CURRENT_TIMESTAMP,
    updated_at datetime default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
    FOREIGN KEY(student_user_id) REFERENCES students(user_id) ON DELETE CASCADE,
    FOREIGN KEY(program_id) REFERENCES training_programs(program_id) ON DELETE CASCADE.
    FOREIGN KEY(approved_by) REFERENCES users(user_id) ON DELETE SET NULL
);
CREATE TABLE instructor_assignments(
    assignment_id int AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT,
    instructor_user_id INT,
    assigned_date datetime default CURRENT_TIMESTAMP,
    assigned_by INT,
    status VARCHAR(30) DEFAULT 'active',
    created_at datetime default CURRENT_TIMESTAMP,
    updated_at datetime default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
    FOREIGN KEY(student_user_id) REFERENCES students(user_id) ON DELETE CASCADE,
    FOREIGN KEY(instructor_user_id) REFERENCES instructors(user_id) ON DELETE CASCADE,
    FOREIGN KEY(assigned_by) REFERENCES users(user_id) ON DELETE SET NULL
);