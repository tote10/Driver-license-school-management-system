# Database Architecture & SRS Alignment
## Frontend Developer Documentation

### 1. Introduction
The database is architected to fully support the **Software Requirements Specification (SRS)** while maintaining a separation of concerns between core identity and role-specific data. This flexible structure ensures that the Frontend UI can dynamically adapt its interface based on the user's role (Student, Instructor, Supervisor, or Manager) while sharing a unified authentication layer.

> [!NOTE]
> Instead of fragmented tables, we utilize a **Core + Extension** pattern to keep the system clean, secure, and highly maintainable.

---

### 2. Core User Management
#### `users` Table (The Identity Hub)
*Reference: SRS Section 4 (Role-Based Access Control)*

Every entity that interacts with the UI exists here. This table is the "Single Source of Truth" for authentication and authorization.

| Column | UI Purpose | SRS Mapping |
| :--- | :--- | :--- |
| `username/email` | Login Credentials | Auth Flow |
| `role` | Determines UI Layout / Permissions | RBAC 4.0 |
| `status` | Approval Workflow (Pending/Approved) | Student Registration 4.1 |
| `branch_id` | Multi-branch UI filtering | Scalability 5.0 |

**Design Rationale:** By centralizing login data, we avoid data redundancy and ensure that security patches or password resets apply globally across all roles.

---

### 3. Role-Specific Data (Extension Tables)
To avoid "fat" tables with empty columns, we use specialized extensions linked via `user_id`.

#### `students` Table
*Reference: SRS 4.1 (Student Profile)*
- Handles: National ID, License Category, and Enrollment Status.
- **UI Context:** Used for the "My Profile" and "Registration Approval" views.

#### `instructors` Table
*Reference: SRS 4.2 (Staff Management)*
- Handles: License Number, Years of Experience, and Specialization.
- **UI Context:** Populates the "Instructor Bio" and "Assignment" cards.

---

### 4. Training & Enrollment Logic
#### `training_programs` Table
*Reference: SRS 4.4 (Program Management)*
- Defines the packages available in the UI (Theory vs. Practical hours, Fees).

#### `enrollments` & `assignments`
- **Enrollments:** Connects a Student to a Program (SRS 4.1).
- **Assignments:** Links an Instructor to a Student for oversight (SRS 4.3).

#### `training_records` Table
*Reference: SRS 4.2 / 4.3 (Progress Tracking)*
- This is the engine behind the **Dashboard Progress Bars**. Every session log here instantly updates the student's progress percentage in the UI.

---

### 5. Examination & Certification Strategy
#### `exam_records` Table
*Reference: SRS Section 3 (Workflow Enforcement)*
- **Logic:** The UI uses the `prerequisite_exam_id` to "Lock" the Practical Exam button until the Theory Exam result is marked as 'Pass'.

#### `certificates` Table
*Reference: SRS 4.4 (Graduation)*
- Final stage of the learner journey. Triggers the "Download Certificate" button in the Student Dashboard.

---

### 6. Supporting Service Tables
- **`documents`**: Backend for the "Upload ID" file interface.
- **`notifications`**: Powers the real-time alerts in the Topbar.
- **`audit_logs`**: Populates the "Recent Activity" feed for Managers.
- **`complaints`**: Feeds the "Quality Alerts" in the Supervisor's view.

---

### 7. Design Philosophy Recap
The database is the "skeleton" that allows the Frontend UI to be:
1. **Adaptive:** Role-based logic is baked into the `users.role` column.
2. **Secure:** Status-based views (Pending/Approved) are enforced at the query level.
3. **Data-Driven:** All counters and charts on the dashboards are direct reflections of `training_records` and `exam_records`.

---
*Last Updated: 2026-04-14 | Prepared for Project Presentation*
