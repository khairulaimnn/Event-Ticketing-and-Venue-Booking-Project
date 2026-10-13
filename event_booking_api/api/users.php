<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . "/../config/database.php";

// Handle CORS preflight request
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER["REQUEST_METHOD"];
require_once __DIR__ . "/auth.php";

if (!$db instanceof PDO) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database unavailable."
    ]);

    exit;
}

requireRoles($db, ["admin"]);

// =====================================================
// GET - Retrieve users
// =====================================================
if ($method === "GET") {

    // GET single user
    if (isset($_GET["id"])) {

        $id = filter_var($_GET["id"], FILTER_VALIDATE_INT);

        if ($id === false || $id <= 0) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Invalid user ID"
            ]);
            exit;
        }

        $stmt = $db->prepare("
            SELECT user_id, name, email, role, created_at
            FROM users
            WHERE user_id = ?
        ");

        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "User not found"
            ]);
            exit;
        }

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "data" => $user
        ]);
        exit;
    }

    // GET all users
    $stmt = $db->query("
        SELECT user_id, name, email, role, created_at
        FROM users
        ORDER BY user_id ASC
    ");

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "count" => count($users),
        "data" => $users
    ]);
    exit;
}


// =====================================================
// POST - Create new user
// =====================================================
if ($method === "POST") {

    $data = json_decode(file_get_contents("php://input"), true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON body"
        ]);
        exit;
    }

    if (
        !isset($data["name"], $data["email"], $data["password"], $data["role"]) ||
        trim((string)$data["name"]) === "" ||
        trim((string)$data["email"]) === "" ||
        trim((string)$data["password"]) === "" ||
        trim((string)$data["role"]) === ""
    ) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Name, email, password and role are required"
        ]);
        exit;
    }

    $name = trim($data["name"]);
    $email = strtolower(trim($data["email"]));
    $password = (string)$data["password"];
    $role = strtolower(trim($data["role"]));

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid email format"
        ]);
        exit;
    }

    // Validate password length
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Password must be at least 6 characters"
        ]);
        exit;
    }

    // Must match ENUM in database
    $allowedRoles = ["admin", "organiser", "customer"];

    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid role. Allowed roles: admin, organiser, customer"
        ]);
        exit;
    }

    // Check duplicate email
    $check = $db->prepare("
        SELECT user_id
        FROM users
        WHERE email = ?
    ");

    $check->execute([$email]);

    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Email already exists"
        ]);
        exit;
    }

    // Securely hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {

        $stmt = $db->prepare("
            INSERT INTO users (name, email, password, role)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $name,
            $email,
            $hashedPassword,
            $role
        ]);

        http_response_code(201);

        echo json_encode([
            "success" => true,
            "message" => "User created successfully",
            "user_id" => (int)$db->lastInsertId()
        ]);

    } catch (PDOException $e) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "Failed to create user"
        ]);
    }

    exit;
}


// =====================================================
// PUT - Update existing user
// =====================================================
if ($method === "PUT") {

    if (!isset($_GET["id"])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "User ID is required"
        ]);
        exit;
    }

    $id = filter_var($_GET["id"], FILTER_VALIDATE_INT);

    if ($id === false || $id <= 0) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid user ID"
        ]);
        exit;
    }

    // Check user exists
    $check = $db->prepare("
        SELECT user_id
        FROM users
        WHERE user_id = ?
    ");

    $check->execute([$id]);

    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "User not found"
        ]);
        exit;
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON body"
        ]);
        exit;
    }

    if (
        !isset($data["name"], $data["email"], $data["role"]) ||
        trim((string)$data["name"]) === "" ||
        trim((string)$data["email"]) === "" ||
        trim((string)$data["role"]) === ""
    ) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Name, email and role are required"
        ]);
        exit;
    }

    $name = trim($data["name"]);
    $email = strtolower(trim($data["email"]));
    $role = strtolower(trim($data["role"]));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid email format"
        ]);
        exit;
    }

    $allowedRoles = ["admin", "organiser", "customer"];

    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid role. Allowed roles: admin, organiser, customer"
        ]);
        exit;
    }

    // Check duplicate email except current user
    $duplicate = $db->prepare("
        SELECT user_id
        FROM users
        WHERE email = ?
        AND user_id != ?
    ");

    $duplicate->execute([$email, $id]);

    if ($duplicate->fetch()) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Email already exists"
        ]);
        exit;
    }

    /*
     * If password is provided, update and hash it.
     * If password is not provided, keep existing password.
     */
    if (
        isset($data["password"]) &&
        trim((string)$data["password"]) !== ""
    ) {

        $password = (string)$data["password"];

        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Password must be at least 6 characters"
            ]);
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("
            UPDATE users
            SET name = ?, email = ?, password = ?, role = ?
            WHERE user_id = ?
        ");

        $stmt->execute([
            $name,
            $email,
            $hashedPassword,
            $role,
            $id
        ]);

    } else {

        $stmt = $db->prepare("
            UPDATE users
            SET name = ?, email = ?, role = ?
            WHERE user_id = ?
        ");

        $stmt->execute([
            $name,
            $email,
            $role,
            $id
        ]);
    }

    http_response_code(200);

    echo json_encode([
        "success" => true,
        "message" => "User updated successfully",
        "user_id" => (int)$id
    ]);

    exit;
}


// =====================================================
// DELETE - Delete user
// =====================================================
if ($method === "DELETE") {

    if (!isset($_GET["id"])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "User ID is required"
        ]);
        exit;
    }

    $id = filter_var($_GET["id"], FILTER_VALIDATE_INT);

    if ($id === false || $id <= 0) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid user ID"
        ]);
        exit;
    }

    // Check user exists
    $check = $db->prepare("
        SELECT user_id
        FROM users
        WHERE user_id = ?
    ");

    $check->execute([$id]);

    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "User not found"
        ]);
        exit;
    }

    try {

        $stmt = $db->prepare("
            DELETE FROM users
            WHERE user_id = ?
        ");

        $stmt->execute([$id]);

        http_response_code(200);

        echo json_encode([
            "success" => true,
            "message" => "User deleted successfully",
            "user_id" => (int)$id
        ]);

    } catch (PDOException $e) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "User cannot be deleted because related records exist"
        ]);
    }

    exit;
}


// =====================================================
// Unsupported HTTP method
// =====================================================
http_response_code(405);

echo json_encode([
    "success" => false,
    "message" => "Method not allowed"
]);