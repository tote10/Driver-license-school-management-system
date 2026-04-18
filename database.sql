DROP DATABASE IF EXISTS driver_license_school_db;
CREATE DATABASE driver_license_school_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE driver_license_school_db;

CREATE TABLE branches (
    branch_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    address TEXT,
    contact_phone VARCHAR(20),
    contact_email VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    gender VARCHAR(10),
    role VARCHAR(30) NOT NULL,
    branch_id INT,
    status VARCHAR(30) DEFAULT 'pending',
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL,
    INDEX idx_user_branch (branch_id),
    INDEX idx_user_status (status)
);

CREATE TABLE students (
    user_id INT PRIMARY KEY,
    national_id VARCHAR(100) UNIQUE NOT NULL,
    date_of_birth DATE,
    address TEXT,
    license_category VARCHAR(50),
    registration_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    registration_status VARCHAR(30) DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_student_reg_status (registration_status)
);

CREATE TABLE instructors (
    user_id INT PRIMARY KEY,
    instructor_license_number VARCHAR(100) UNIQUE DEFAULT NULL,
    years_experience INT,
    specialization VARCHAR(255),
    qualifications TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE training_programs (
    program_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    license_category VARCHAR(50) NOT NULL,
    description TEXT,
    course_structure LONGTEXT,
    theory_duration_hours INT,
    practical_duration_hours INT,
    fee_amount DECIMAL(10, 2) NOT NULL,
    branch_id INT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_prog_branch (branch_id),
    INDEX idx_prog_license (license_category)
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
    FOREIGN KEY (student_user_id) REFERENCES students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES training_programs(program_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_enr_student (student_user_id),
    INDEX idx_enr_prog (program_id),
    INDEX idx_enr_status (approval_status)
);

CREATE TABLE instructor_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT,
    instructor_user_id INT,
    assigned_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT,
    status VARCHAR(30) DEFAULT 'active',
    FOREIGN KEY (student_user_id) REFERENCES students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_user_id) REFERENCES instructors(user_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_asgn_student (student_user_id),
    INDEX idx_asgn_instructor (instructor_user_id)
);

CREATE TABLE training_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT,
    instructor_user_id INT,
    lesson_type VARCHAR(20),
    scheduled_datetime DATETIME,
    duration_minutes INT DEFAULT 60,
    location VARCHAR(255),
    branch_id INT,
    status VARCHAR(30) DEFAULT 'scheduled',
    FOREIGN KEY (student_user_id) REFERENCES students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_user_id) REFERENCES instructors(user_id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL,
    INDEX idx_sch_student (student_user_id),
    INDEX idx_sch_date (scheduled_datetime)
);

CREATE TABLE training_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT,
    student_user_id INT,
    instructor_user_id INT,
    lesson_type VARCHAR(20),
    performance_score DECIMAL(5,2),
    feedback TEXT,
    attendance_status VARCHAR(20) DEFAULT 'present',
    instructor_recommendation_for_exam TINYINT(1) DEFAULT 0,
    recorded_by INT,
    reviewed_by INT,
    review_notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES training_schedules(schedule_id) ON DELETE SET NULL,
    FOREIGN KEY (student_user_id) REFERENCES students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_rec_student (student_user_id),
    INDEX idx_rec_review (reviewed_by)
);

CREATE TABLE documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    entity_id INT,
    entity_type VARCHAR(30),
    document_type VARCHAR(50),
    file_url VARCHAR(255),
    uploaded_by INT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_doc_entity (entity_id, entity_type)
);

CREATE TABLE exam_records (
    exam_id INT AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT,
    exam_type VARCHAR(20),
    prerequisite_exam_id INT,
    scheduled_date DATETIME,
    taken_date DATETIME,
    score INT,
    passed TINYINT(1) DEFAULT 0,
    comments TEXT,
    status VARCHAR(30) DEFAULT 'scheduled',
    approved_by INT,
    validated_date DATETIME,
    FOREIGN KEY (student_user_id) REFERENCES students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (prerequisite_exam_id) REFERENCES exam_records(exam_id) ON DELETE SET NULL,
    INDEX idx_ex_student (student_user_id),
    INDEX idx_ex_status (status)
);

CREATE TABLE certificates (
    certificate_id INT AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT,
    certificate_number VARCHAR(100) UNIQUE,
    issue_date DATE,
    approved_by INT,
    national_license_reference VARCHAR(100),
    certificate_url VARCHAR(255),
    graduation_status VARCHAR(30) DEFAULT 'issued',
    FOREIGN KEY (student_user_id) REFERENCES students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_cert_student (student_user_id)
);

CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_user_id INT,
    title VARCHAR(255),
    message TEXT,
    notification_type VARCHAR(50),
    is_read TINYINT(1) DEFAULT 0,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_notif_user (recipient_user_id),
    INDEX idx_notif_read (is_read)
);

CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action_type VARCHAR(50),
    entity_type VARCHAR(50),
    entity_id INT,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_date (created_at)
);

CREATE TABLE complaints (
    complaint_id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_user_id INT,
    subject_type VARCHAR(50),
    description TEXT,
    status VARCHAR(30) DEFAULT 'open',
    reviewed_by INT,
    resolution TEXT,
    resolution_date DATETIME,
    reported_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_comp_reporter (reporter_user_id),
    INDEX idx_comp_status (status)
);

CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT,
    enrollment_id INT,
    amount DECIMAL(10, 2),
    payment_type VARCHAR(50),
    status VARCHAR(30) DEFAULT 'paid',
    transaction_id VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_user_id) REFERENCES students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(enrollment_id) ON DELETE SET NULL,
    INDEX idx_pay_student (student_user_id),
    INDEX idx_pay_enrollment (enrollment_id)
);
