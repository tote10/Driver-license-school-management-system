# Database Design Specification: Driver License School Management System

This document provides a comprehensive technical reference for the 16-table relational database architecture developed for the Driver License School Management System. It details the purpose of each table, the relationships between entities, and the business logic enforced at the database level.

---

### 1. Core Identity and Authentication Layer

### Table: branches

This table stores the physical locations of the school branches. It is used to categorize users and students by their location for reporting and localized data management. Primary fields include the branch name, physical location, and contact information.

### Table: users

The central identity store for the entire system. It handles authentication credentials and role-based access control. The 'role' field determines whether a user is an Administrator, Student, Instructor, or Supervisor. The 'status' field tracks whether an account is pending, active, or rejected.

### Table: students

An extension of the 'users' table specifically for learners. It stores sensitive demographic information such as National ID numbers, date of birth, and physical addresses. It also tracks the student's license category and their current registration status within the workflow.

### Table: instructors

An extension of the 'users' table for teaching staff. It records professional data including instructor license numbers, years of experience, and areas of specialization. The instructor license number is nullable to allow for instructors who are currently in their own licensing phase.

---

## 2. Curriculum and Enrollment Layer

### Table: training_programs

Defines the courses offered by the school (e.g., Automatic vs. Manual Driving). This table includes the course name, detailed syllabus content in a course_structure field, and the total fee amount required for the program.

### Table: enrollments

The join table that links a student to a training program. It records the date of enrollment, the manager who approved the enrollment, and the current progress of the student within that program (e.g., currently in theory phase).

### Table: instructor_assignments

Records the formal assignment of a specific instructor to a specific student. This relationship is managed by Supervisors to ensure accountability and resource allocation.

---

## 3. Training and Evaluation Layer

### Table: training_schedules

Records all scheduled lesson appointments, both for theory classes and practical driving sessions. It includes the date, time, location, and lesson type.

### Table: training_records

The evaluation store for the training phase. It records attendance and performance scores for every scheduled lesson. This table includes a review mechanism where a Supervisor can provide notes and validate the instructor's assessment of a student. It also includes a flag for when an instructor recommends a student for their final exam.

---

## 4. Assessment and Certification Layer

### Table: exam_records

Stores the results of formal theory and practical examinations. The table uses a self-referencing prerequisite field to ensure that practical exams can only be validated if the corresponding theory exam has been passed.

### Table: certificates

The final output of the system. Once a student passes all requirements, a record is created here with a unique certificate number and a reference to the national license database. It also stores a URL to the generated digital certificate.

---

## 5. System Support and Financial Layer

### Table: payments

Maintains a detailed ledger of all financial transactions. Credits to this table are linked to students and their specific enrollments to track full or partial fee payments.

### Table: documents

A centralized file manager that tracks paths to physical files uploaded to the server, such as ID scans, medical forms, and profile photos. Each document is linked to an entity type and entity ID for easy retrieval.

### Table: notifications

The internal system for managing communication with users. It tracks whether an alert has been read and handles various notification types like exam reminders or approval alerts.

### Table: audit_logs

A dedicated security table that records every major change within the system. It tracks the user who performed an action, the type of action (Create, Update, Delete), and the specific entity that was modified.

### Table: complaints

Handles student feedback and issue resolution. It tracks the reporter, the nature of the complaint, and the supervisor responsible for reviewing and resolving the issue.

---

## 6. Technical standards and Indexes

To ensure sub-2-second performance across all reports, every foreign key column is indexed using the standard idx\_ prefix. The database enforces referential integrity through foreign key constraints, ensuring that related records (such as payments or grades) are protected from accidental deletion if a top-level record is modified.
