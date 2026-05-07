Audit Logging Design

Purpose
- Record critical actions for compliance, debugging, and traceability (user actions: create/update/delete, approvals, grade changes, certificate issuance).

DB Schema (suggested)
- `audit_logs` table
  - `id` INT PRIMARY KEY AUTO_INCREMENT
  - `user_id` INT NULL -- actor
  - `action` VARCHAR(100) -- e.g., create_enrollment, approve_exam
  - `entity` VARCHAR(100) -- table or domain entity e.g., enrollment, exam
  - `entity_id` INT NULL
  - `details` JSON NULL -- previous/after snapshots or notes
  - `ip_address` VARCHAR(45) NULL
  - `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP

When to log (examples)
- Manager approves/rejects enrollment
- Supervisor approves readiness
- Instructor records session/grades
- Exam results recorded/modified
- Certificate issued
- Login attempts (optional)

PHP Helper
- `log_audit($pdo, $user_id, $action, $entity, $entity_id = null, $details = null)`
  - Stores a JSON-encodable `details` object for later inspection.

Access / UI
- Manager-only Audit Viewer with filters by user, action, entity, date range
- Export to CSV for compliance reporting

Migration (example SQL)

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity` VARCHAR(100) NOT NULL,
  `entity_id` INT NULL,
  `details` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

Notes
- Keep `details` JSON small; store full snapshots in archival storage if needed.
- Ensure write performance; use asynchronous logging or batching for high traffic endpoints.
