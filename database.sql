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