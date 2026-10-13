<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

ini_set("session.use_strict_mode", "1");

session_name("event_booking_session");

session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/event_booking_api/",
    "secure" => !empty($_SERVER["HTTPS"])
        && $_SERVER["HTTPS"] !== "off",
    "httponly" => true,
    "samesite" => "Strict"
]);

session_start();

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"];

// Check the current login session.
if ($method === "GET") {
    if (!isset($_SESSION["user"])) {
        respond(401, [
            "success" => false,
            "message" => "Please log in."
        ]);
    }

    respond(200, [
        "success" => true,
        "data" => $_SESSION["user"]
    ]);
}

// Login requires a POST request.
if ($method !== "POST") {
    header("Allow: GET, POST");

    respond(405, [
        "success" => false,
        "message" => "Method not allowed."
    ]);
}

// Accept JSON only.
$contentType = strtolower(
    explode(";", $_SERVER["CONTENT_TYPE"] ?? "")[0]
);

if (trim($contentType) !== "application/json") {
    respond(415, [
        "success" => false,
        "message" => "Use Content-Type: application/json."
    ]);
}

// Reject browser requests from another origin.
if (isset($_SERVER["HTTP_ORIGIN"])) {
    $scheme = !empty($_SERVER["HTTPS"])
        && $_SERVER["HTTPS"] !== "off"
        ? "https"
        : "http";

    $expectedOrigin = $scheme . "://" . $_SERVER["HTTP_HOST"];

    if ($_SERVER["HTTP_ORIGIN"] !== $expectedOrigin) {
        respond(403, [
            "success" => false,
            "message" => "Request origin not allowed."
        ]);
    }
}

$data = json_decode(file_get_contents("php://input"), true);

if (
    !is_array($data)
    || !isset($data["email"], $data["password"])
    || !is_string($data["email"])
    || !is_string($data["password"])
) {
    respond(400, [
        "success" => false,
        "message" => "Email and password are required."
    ]);
}

$email = strtolower(trim($data["email"]));
$password = $data["password"];

if (
    !filter_var($email, FILTER_VALIDATE_EMAIL)
    || $password === ""
    || strlen($email) > 150
    || strlen($password) > 1024
) {
    respond(400, [
        "success" => false,
        "message" => "Enter a valid email and password."
    ]);
}

require_once __DIR__ . "/../config/database.php";

try {
    // Prevent connection messages from breaking the JSON response.
    ob_start();

    try {
        $database = new Database();
        $db = $database->getConnection();
    } finally {
        ob_end_clean();
    }

    if (!$db) {
        throw new RuntimeException("Database unavailable.");
    }

    $stmt = $db->prepare(
        "SELECT user_id, name, email, password, role
         FROM users
         WHERE email = ?
         LIMIT 1"
    );

    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$user
        || !password_verify($password, $user["password"])
        || !in_array(
            $user["role"],
            ["admin", "organiser", "customer"],
            true
        )
    ) {
        respond(401, [
            "success" => false,
            "message" => "Invalid email or password."
        ]);
    }

    session_regenerate_id(true);

    $_SESSION["user"] = [
        "user_id" => (int) $user["user_id"],
        "name" => $user["name"],
        "email" => $user["email"],
        "role" => $user["role"]
    ];

    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

    respond(200, [
        "success" => true,
        "message" => "Login successful.",
        "data" => $_SESSION["user"]
    ]);
} catch (Throwable $error) {
    error_log("Login API: " . $error->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to log in. Please try again."
    ]);
}