Notifications Design

Purpose
- Provide in-app and email notifications for schedule changes, exam scheduling/results, approvals/rejections, certificate issuance, and important admin alerts.

DB Schema (suggested)
- `notifications` table
  - `id` INT PRIMARY KEY AUTO_INCREMENT
  - `user_id` INT NULL -- recipient (nullable for broadcast)
  - `type` VARCHAR(50) -- e.g., schedule, exam_result, approval, certificate
  - `title` VARCHAR(255)
  - `message` TEXT
  - `meta` JSON NULL -- extra data (schedule_id, exam_id, program_id)
  - `is_read` TINYINT(1) DEFAULT 0
  - `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
  - `delivered_at` DATETIME NULL

Events to trigger notifications
- Enrollment approved by manager
- Instructor assigned to a student
- Schedule created/updated/cancelled
- Exam scheduled/cancelled
- Exam result recorded (theory/practical)
- Supervisor approval/rejection
- Certificate issued

Delivery methods
- In-app (stored in `notifications` table, fetched by frontend)
- Email (optional SMTP integration)
- Future: SMS or push notifications

API / PHP hooks
- `send_notification($pdo, $user_id, $type, $title, $message, $meta = null)`
- `fetch_user_notifications($pdo, $user_id, $limit = 50)`
- `mark_notification_read($pdo, $notification_id, $user_id)`

UI
- Topbar bell icon showing unread count
- Notifications dropdown with recent items
- Notifications page: paginated list with filters (unread, type)
- Notification detail modal with action links (view schedule, view exam)

Integration points (where to call)
- After approval endpoints: `manager/enrollments.php`, `supervisor/reviews.php`
- After schedule changes: `manager/schedules.php`, `instructor/records.php`
- After exam result saved: `manager/exams.php`
- After certificate issued: `manager/certificates.php`

Migration (example SQL)

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `meta` JSON NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `delivered_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

Notes
- Keep notifications lightweight. Use `meta` JSON to store references so UI can deep-link.
- For broadcasts, set `user_id` NULL and provide a `target` field in future (e.g., role, branch).
- Ensure indexes on `user_id` and `is_read` for performance.
