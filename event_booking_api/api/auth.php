<?php
declare(strict_types=1);

function authError(int $status, string $message): void
{
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code($status);

    echo json_encode([
        "success" => false,
        "message" => $message
    ]);

    exit;
}

function requireRoles(PDO $db, array $allowedRoles): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set("session.use_strict_mode", "1");
        session_name("event_booking_session");
        session_start();
    }

    header("Cache-Control: no-store");

    if (!isset($_SESSION["user"]["user_id"])) {
        authError(401, "Please log in.");
    }

    // Read the current role from the database.
    $stmt = $db->prepare(
        "SELECT user_id, name, email, role
         FROM users
         WHERE user_id = ?"
    );

    $stmt->execute([
        $_SESSION["user"]["user_id"]
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        unset($_SESSION["user"]);
        authError(401, "Your account is no longer available.");
    }

    if (!in_array($user["role"], $allowedRoles, true)) {
        authError(403, "You do not have permission for this action.");
    }

    $_SESSION["user"] = [
        "user_id" => (int) $user["user_id"],
        "name" => $user["name"],
        "email" => $user["email"],
        "role" => $user["role"]
    ];

    // Protect requests that change records.
    $method = $_SERVER["REQUEST_METHOD"];

    if (in_array($method, ["POST", "PUT", "PATCH", "DELETE"], true)) {
        $providedToken = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
        $sessionToken = $_SESSION["csrf_token"] ?? "";

        if (
            !is_string($providedToken)
            || !is_string($sessionToken)
            || $sessionToken === ""
            || !hash_equals($sessionToken, $providedToken)
        ) {
            authError(403, "Invalid security token. Please log in again.");
        }
    }

    return $_SESSION["user"];
}