<?php

function manager_table_has_column(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?
         LIMIT 1"
    );
    $stmt->execute([$table, $column]);
    $cache[$key] = (bool)$stmt->fetchColumn();
    return $cache[$key];
}

function manager_student_program_complete(PDO $pdo, int $student_id, int $program_id): bool {
    $stmtTheory = $pdo->prepare(
        "SELECT COUNT(*)
         FROM exam_records
         WHERE student_user_id = ?
           AND program_id = ?
           AND exam_type = 'theory'
           AND passed = 1
           AND status = 'completed'"
    );
    $stmtTheory->execute([$student_id, $program_id]);
    if ((int)$stmtTheory->fetchColumn() === 0) {
        return false;
    }

    $stmtPractical = $pdo->prepare(
        "SELECT COUNT(*)
         FROM exam_records
         WHERE student_user_id = ?
           AND program_id = ?
           AND exam_type = 'practical'
           AND passed = 1
           AND status = 'completed'"
    );
    $stmtPractical->execute([$student_id, $program_id]);

    return (int)$stmtPractical->fetchColumn() > 0;
}

function manager_student_all_enrolled_programs_complete(PDO $pdo, int $student_id, int $branch_id): bool {
    $stmtPrograms = $pdo->prepare(
        "SELECT e.program_id
         FROM enrollments e
         JOIN training_programs tp ON tp.program_id = e.program_id
         WHERE e.student_user_id = ?
           AND e.approval_status = 'approved'
                     AND e.current_progress_status <> 'failed'
           AND tp.branch_id = ?"
    );
    $stmtPrograms->execute([$student_id, $branch_id]);
    $program_ids = $stmtPrograms->fetchAll(PDO::FETCH_COLUMN);

    if (count($program_ids) === 0) {
        return false;
    }

    foreach ($program_ids as $program_id) {
        if (!manager_student_program_complete($pdo, (int)$student_id, (int)$program_id)) {
            return false;
        }
    }

    return true;
}

function manager_student_has_graduation_certificate(PDO $pdo, int $student_id): bool {
    $stmt = $pdo->prepare(
        "SELECT certificate_id
         FROM certificates
         WHERE student_user_id = ?
         LIMIT 1"
    );
    $stmt->execute([$student_id]);
    return (bool)$stmt->fetchColumn();
}

function manager_student_graduation_ready(PDO $pdo, int $student_id, int $branch_id): bool {
    if (manager_student_has_graduation_certificate($pdo, $student_id)) {
        return false;
    }

    return manager_student_all_enrolled_programs_complete($pdo, $student_id, $branch_id);
}

function manager_latest_graduation_enrollment_id(PDO $pdo, int $student_id, int $branch_id): ?int {
    $stmt = $pdo->prepare(
        "SELECT e.enrollment_id
         FROM enrollments e
         JOIN training_programs tp ON tp.program_id = e.program_id
         WHERE e.student_user_id = ?
           AND e.approval_status = 'approved'
           AND tp.branch_id = ?
         ORDER BY e.approved_date DESC, e.enrollment_id DESC
         LIMIT 1"
    );
    $stmt->execute([$student_id, $branch_id]);
    $enrollment_id = $stmt->fetchColumn();

    return $enrollment_id ? (int)$enrollment_id : null;
}

function manager_student_exam_ready(PDO $pdo, int $student_id, int $program_id): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM training_records
         WHERE student_user_id = ?
           AND program_id = ?
           AND instructor_recommendation_for_exam = 1
           AND reviewed_by IS NOT NULL
           AND review_notes LIKE 'Approved by supervisor%'"
    );
    $stmt->execute([$student_id, $program_id]);
    return (int)$stmt->fetchColumn() > 0;
}
