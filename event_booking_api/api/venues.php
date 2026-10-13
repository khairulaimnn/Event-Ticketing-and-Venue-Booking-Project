<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . "/../config/database.php";

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

// Viewing venues remains public.
// Changing venues requires an Administrator account.
if (in_array($method, ["POST", "PUT", "DELETE"], true)) {
    requireRoles($db, ["admin"]);
}

// ========================================
// GET - Retrieve venues
// ========================================
if ($method === "GET") {

    // GET single venue
    if (isset($_GET["id"])) {

        $id = filter_var($_GET["id"], FILTER_VALIDATE_INT);

        if (!$id || $id <= 0) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Invalid venue ID"
            ]);
            exit;
        }

        $stmt = $db->prepare("
            SELECT venue_id, venue_name, location, capacity, created_at
            FROM venues
            WHERE venue_id = ?
        ");

        $stmt->execute([$id]);
        $venue = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$venue) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Venue not found"
            ]);
            exit;
        }

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "data" => $venue
        ]);
        exit;
    }

    // GET all venues
    $stmt = $db->prepare("
        SELECT venue_id, venue_name, location, capacity, created_at
        FROM venues
        ORDER BY venue_id ASC
    ");

    $stmt->execute();
    $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "count" => count($venues),
        "data" => $venues
    ]);
    exit;
}


// ========================================
// POST - Create new venue
// ========================================
if ($method === "POST") {

    $data = json_decode(file_get_contents("php://input"), true);

    if (
        !$data ||
        empty($data["venue_name"]) ||
        empty($data["location"]) ||
        !isset($data["capacity"])
    ) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Venue name, location and capacity are required"
        ]);
        exit;
    }

    $venue_name = trim($data["venue_name"]);
    $location = trim($data["location"]);
    $capacity = filter_var($data["capacity"], FILTER_VALIDATE_INT);

    if ($capacity === false || $capacity <= 0) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid capacity"
        ]);
        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO venues (venue_name, location, capacity)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $venue_name,
        $location,
        $capacity
    ]);

    $venue_id = $db->lastInsertId();

    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Venue created successfully",
        "venue_id" => $venue_id
    ]);
    exit;
}


// ========================================
// PUT - Update venue
// ========================================
if ($method === "PUT") {

    if (!isset($_GET["id"])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Venue ID is required"
        ]);
        exit;
    }

    $id = filter_var($_GET["id"], FILTER_VALIDATE_INT);

    if (!$id || $id <= 0) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid venue ID"
        ]);
        exit;
    }

    // Check venue exists
    $check = $db->prepare("
        SELECT venue_id
        FROM venues
        WHERE venue_id = ?
    ");

    $check->execute([$id]);

    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Venue not found"
        ]);
        exit;
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (
        !$data ||
        empty($data["venue_name"]) ||
        empty($data["location"]) ||
        !isset($data["capacity"])
    ) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Venue name, location and capacity are required"
        ]);
        exit;
    }

    $venue_name = trim($data["venue_name"]);
    $location = trim($data["location"]);
    $capacity = filter_var($data["capacity"], FILTER_VALIDATE_INT);

    if ($capacity === false || $capacity <= 0) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid capacity"
        ]);
        exit;
    }

    $stmt = $db->prepare("
        UPDATE venues
        SET venue_name = ?,
            location = ?,
            capacity = ?
        WHERE venue_id = ?
    ");

    $stmt->execute([
        $venue_name,
        $location,
        $capacity,
        $id
    ]);

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Venue updated successfully"
    ]);
    exit;
}


// ========================================
// DELETE - Delete venue
// ========================================
if ($method === "DELETE") {

    if (!isset($_GET["id"])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Venue ID is required"
        ]);
        exit;
    }

    $id = filter_var($_GET["id"], FILTER_VALIDATE_INT);

    if (!$id || $id <= 0) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid venue ID"
        ]);
        exit;
    }

    // Check venue exists
    $check = $db->prepare("
        SELECT venue_id
        FROM venues
        WHERE venue_id = ?
    ");

    $check->execute([$id]);

    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Venue not found"
        ]);
        exit;
    }

    try {

        $stmt = $db->prepare("
            DELETE FROM venues
            WHERE venue_id = ?
        ");

        $stmt->execute([$id]);

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Venue deleted successfully"
        ]);

    } catch (PDOException $e) {

        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Venue cannot be deleted because it is being used by an event"
        ]);
    }

    exit;
}


// ========================================
// METHOD NOT ALLOWED
// ========================================
http_response_code(405);

echo json_encode([
    "success" => false,
    "message" => "Method not allowed"
]);