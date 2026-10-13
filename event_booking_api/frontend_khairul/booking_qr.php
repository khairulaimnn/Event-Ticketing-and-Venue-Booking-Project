<?php
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/auth.php";

function qrResponse($code, $message, $data = null) {
    http_response_code($code);

    $result = [
        "success" => $code === 200,
        "message" => $message
    ];

    if ($data !== null) {
        $result["data"] = $data;
    }

    echo json_encode($result);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");
    qrResponse(405, "Method not allowed");
}

try {
    $database = new Database();

    ob_start();
    try {
        $db = $database->getConnection();
    } finally {
        ob_end_clean();
    }

    if (!$db instanceof PDO) {
        qrResponse(500, "Database connection failed");
    }

    $actor = requireRoles($db, ["customer"]);

    $id = filter_var(
        $_GET["id"] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$id || $id < 1) {
        qrResponse(400, "Invalid booking ID");
    }

    $stmt = $db->prepare("
        SELECT booking_id, customer_id, booking_status
        FROM bookings
        WHERE booking_id = ?
    ");
    $stmt->execute([$id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        qrResponse(404, "Booking not found");
    }

    if ((int)$booking["customer_id"] !== (int)$actor["user_id"]) {
        qrResponse(403, "You can only view your own booking QR");
    }

    if ($booking["booking_status"] !== "confirmed") {
        qrResponse(409, "QR is available only for confirmed bookings");
    }

    session_write_close();

    if (!function_exists("curl_init")) {
        qrResponse(500, "PHP cURL must be enabled to generate QR codes");
    }

    // Send only a reference, without personal details or login tokens.
    $reference = "EventSpace booking #" . $id;

    $url = "https://quickchart.io/qr?" . http_build_query([
        "text" => $reference,
        "size" => 250,
        "format" => "png"
    ], "", "&", PHP_QUERY_RFC3986);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ["Accept: image/png"]
    ]);

    $png = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (
        $status !== 200 ||
        !is_string($png) ||
        substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n"
    ) {
        error_log("QR service failed: " . $curlError);
        qrResponse(502, "QR service unavailable. Please try again.");
    }

    qrResponse(200, "Booking QR generated", [
        "booking_id" => $id,
        "reference" => $reference,
        "image" => "data:image/png;base64," . base64_encode($png)
    ]);

} catch (Throwable $e) {
    error_log($e->getMessage());
    qrResponse(500, "Unable to generate booking QR");
}