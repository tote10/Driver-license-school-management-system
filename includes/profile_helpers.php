<?php

if (!function_exists('ds_display_name')) {
    function ds_display_name(?string $fallback = null): string {
        $name = trim((string)($_SESSION['full_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        return trim((string)$fallback) !== '' ? trim((string)$fallback) : 'User';
    }
}

if (!function_exists('ds_display_initials')) {
    function ds_display_initials(?string $name = null, ?string $fallback = null): string {
        $source = trim((string)($name ?? $_SESSION['full_name'] ?? ''));
        if ($source === '') {
            $source = trim((string)$fallback);
        }
        if ($source === '') {
            $source = 'U';
        }
        $parts = preg_split('/\s+/', $source) ?: [];
        $initials = strtoupper(substr($parts[0] ?? 'U', 0, 1));
        if (count($parts) > 1) {
            $initials .= strtoupper(substr((string)end($parts), 0, 1));
        }
        return $initials;
    }
}

if (!function_exists('ds_profile_photo_url')) {
    function ds_profile_photo_url(PDO $pdo, int $user_id): string {
        if ($user_id <= 0) {
            return '';
        }
        $stmt = $pdo->prepare("SELECT file_url FROM documents WHERE entity_id = ? AND entity_type = 'user' AND document_type = 'profile_photo' ORDER BY uploaded_at DESC LIMIT 1");
        $stmt->execute([$user_id]);
        return (string)($stmt->fetchColumn() ?: '');
    }
}
