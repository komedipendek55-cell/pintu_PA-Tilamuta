<?php
function audit_log(string $action, string $table, ?int $recordId = null, $before = null, $after = null): void {
    try {
        $stmt = Database::pdo()->prepare(
            "INSERT INTO audit_logs (user_id, action, table_name, record_id, before_json, after_json, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            current_user()['id'] ?? null,
            $action,
            $table,
            $recordId,
            $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('[PINTU] audit: ' . $e->getMessage());
    }
}