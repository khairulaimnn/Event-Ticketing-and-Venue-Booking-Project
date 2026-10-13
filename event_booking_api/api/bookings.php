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

if (!$db) {
    response(500, false, "Database connection failed");
}

$method = $_SERVER["REQUEST_METHOD"];
require_once __DIR__ . "/auth.php";

$allowedRoles = $method === "GET"
    ? ["admin", "organiser", "customer"]
    : ["admin", "customer"];

$actor = requireRoles($db, $allowedRoles);

// Customers can only list their own bookings.
if ($method === "GET" && $actor["role"] === "customer") {
    $_GET["customer_id"] = (int)$actor["user_id"];
}

// Check ownership when a particular booking is requested.
if (
    in_array($method, ["GET", "PUT", "DELETE"], true)
    && isset($_GET["id"])
) {
    $requestedId = validId($_GET["id"]);

    if (!$requestedId) {
        response(400, false, "Invalid booking ID");
    }

    $check = $db->prepare("
        SELECT b.customer_id, b.booking_status, e.organiser_id
        FROM bookings b
        INNER JOIN events e ON e.event_id = b.event_id
        WHERE b.booking_id = ?
    ");
    $check->execute([$requestedId]);
    $ownedBooking = $check->fetch(PDO::FETCH_ASSOC);

    if (!$ownedBooking) {
        response(404, false, "Booking not found");
    }

    if (
        $actor["role"] === "customer"
        && (int)$ownedBooking["customer_id"] !== (int)$actor["user_id"]
    ) {
        response(403, false, "You can only access your own bookings");
    }

    if (
        $actor["role"] === "organiser"
        && (int)$ownedBooking["organiser_id"] !== (int)$actor["user_id"]
    ) {
        response(403, false, "You can only view bookings for your events");
    }

    // Customers cancel bookings using PUT, preserving booking history.
    if ($actor["role"] === "customer" && $method === "DELETE") {
        response(403, false, "Cancel your booking instead of deleting it");
    }

    if ($actor["role"] === "customer" && $method === "PUT") {
        $body = getJsonBody();

        if ($ownedBooking["booking_status"] === "cancelled") {
            response(409, false, "A cancelled booking cannot be changed");
        }

        $requestedStatus = $body["booking_status"] ?? "confirmed";

        if (
            !is_string($requestedStatus)
            || !in_array(
                $requestedStatus,
                [$ownedBooking["booking_status"], "cancelled"],
                true
            )
        ) {
            response(403, false, "You cannot change this booking status");
        }
    }
}

// Customers cannot create bookings under another customer's account.
if ($method === "POST" && $actor["role"] === "customer") {
    $body = getJsonBody();

    if (
        validId($body["customer_id"] ?? null)
        !== (int)$actor["user_id"]
    ) {
        response(403, false, "You can only book using your own account");
    }
}


// =====================================================
// HELPER FUNCTIONS
// =====================================================

function response($code, $success, $message = null, $data = null)
{
    http_response_code($code);

    $result = [
        "success" => $success
    ];

    if ($message !== null) {
        $result["message"] = $message;
    }

    if ($data !== null) {
        $result["data"] = $data;
    }

    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}


function getJsonBody()
{
    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($data)) {
        response(400, false, "Invalid JSON body");
    }

    return $data;
}


function validId($value)
{
    $id = filter_var(
        $value,
        FILTER_VALIDATE_INT
    );

    return ($id !== false && $id > 0)
        ? $id
        : false;
}


// =====================================================
// GET BOOKINGS
// =====================================================

if ($method === "GET") {

    try {

        // =================================================
        // GET SINGLE BOOKING
        // Example:
        // bookings.php?id=1
        // =================================================

        if (isset($_GET["id"])) {

            $id = validId($_GET["id"]);

            if (!$id) {
                response(
                    400,
                    false,
                    "Invalid booking ID"
                );
            }

            $stmt = $db->prepare("
                SELECT
                    b.booking_id,
                    b.customer_id,
                    u.name AS customer_name,
                    u.email AS customer_email,
                    b.event_id,
                    e.event_name,
                    e.event_date,
                    e.event_time,
                    e.ticket_price,
                    b.quantity,
                    b.total_amount,
                    b.booking_status,
                    b.booking_date
                FROM bookings b

                INNER JOIN users u
                    ON b.customer_id = u.user_id

                INNER JOIN events e
                    ON b.event_id = e.event_id

                WHERE b.booking_id = ?
            ");

            $stmt->execute([$id]);

            $booking = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$booking) {
                response(
                    404,
                    false,
                    "Booking not found"
                );
            }

            response(
                200,
                true,
                null,
                $booking
            );
        }


        // =================================================
        // FILTER BOOKINGS
        //
        // Examples:
        // ?customer_id=4
        // ?event_id=1
        // ?status=confirmed
        // =================================================

        $conditions = [];
        $params = [];
        // Organisers can only list bookings for events they own.
        if ($actor["role"] === "organiser") {
            $conditions[] = "e.organiser_id = ?";
            $params[] = (int)$actor["user_id"];
        }


        // CUSTOMER FILTER

        if (isset($_GET["customer_id"])) {

            $customerId = validId(
                $_GET["customer_id"]
            );

            if (!$customerId) {
                response(
                    400,
                    false,
                    "Invalid customer ID"
                );
            }

            // Check customer exists

            $check = $db->prepare("
                SELECT user_id
                FROM users
                WHERE user_id = ?
                AND role = 'customer'
            ");

            $check->execute([
                $customerId
            ]);

            if (!$check->fetch()) {
                response(
                    404,
                    false,
                    "Customer not found"
                );
            }

            $conditions[] =
                "b.customer_id = ?";

            $params[] =
                $customerId;
        }


        // EVENT FILTER

        if (isset($_GET["event_id"])) {

            $eventId = validId(
                $_GET["event_id"]
            );

            if (!$eventId) {
                response(
                    400,
                    false,
                    "Invalid event ID"
                );
            }

            // Check event exists

            $check = $db->prepare("
                SELECT event_id
                FROM events
                WHERE event_id = ?
            ");

            $check->execute([
                $eventId
            ]);

            if (!$check->fetch()) {
                response(
                    404,
                    false,
                    "Event not found"
                );
            }

            $conditions[] =
                "b.event_id = ?";

            $params[] =
                $eventId;
        }


        // STATUS FILTER

        if (isset($_GET["status"])) {

            $status = strtolower(
                trim($_GET["status"])
            );

            $allowedStatuses = [
                "pending",
                "confirmed",
                "cancelled"
            ];

            if (!in_array(
                $status,
                $allowedStatuses,
                true
            )) {

                response(
                    400,
                    false,
                    "Invalid booking status"
                );
            }

            $conditions[] =
                "b.booking_status = ?";

            $params[] =
                $status;
        }


        // =================================================
        // BUILD QUERY
        // =================================================

        $sql = "
            SELECT
                b.booking_id,
                b.customer_id,
                u.name AS customer_name,
                u.email AS customer_email,
                b.event_id,
                e.event_name,
                e.event_date,
                e.event_time,
                e.ticket_price,
                b.quantity,
                b.total_amount,
                b.booking_status,
                b.booking_date
            FROM bookings b

            INNER JOIN users u
                ON b.customer_id = u.user_id

            INNER JOIN events e
                ON b.event_id = e.event_id
        ";


        if (!empty($conditions)) {

            $sql .= "
                WHERE "
                . implode(
                    " AND ",
                    $conditions
                );
        }


        $sql .= "
            ORDER BY b.booking_id ASC
        ";


        $stmt = $db->prepare($sql);

        $stmt->execute($params);

        $bookings =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        http_response_code(200);

        echo json_encode([
            "success" => true,
            "count" => count($bookings),
            "data" => $bookings
        ], JSON_PRETTY_PRINT);

        exit;


    } catch (PDOException $e) {

        response(
            500,
            false,
            "Failed to retrieve bookings"
        );
    }
}


// =====================================================
// POST - CREATE BOOKING
// =====================================================

if ($method === "POST") {

    $data = getJsonBody();

    if (
        !isset($data["customer_id"]) ||
        !isset($data["event_id"]) ||
        !isset($data["quantity"])
    ) {

        response(
            400,
            false,
            "customer_id, event_id and quantity are required"
        );
    }


    $customerId =
        validId($data["customer_id"]);

    $eventId =
        validId($data["event_id"]);

    $quantity =
        validId($data["quantity"]);


    if (
        !$customerId ||
        !$eventId ||
        !$quantity
    ) {

        response(
            400,
            false,
            "Invalid customer_id, event_id or quantity"
        );
    }


    try {

        $db->beginTransaction();


        // =================================================
        // CHECK CUSTOMER
        // =================================================

        $stmt = $db->prepare("
            SELECT user_id
            FROM users
            WHERE user_id = ?
            AND role = 'customer'
        ");

        $stmt->execute([
            $customerId
        ]);

        if (!$stmt->fetch()) {

            $db->rollBack();

            response(
                404,
                false,
                "Customer not found"
            );
        }


        // =================================================
        // GET EVENT
        // =================================================

        $stmt = $db->prepare("
            SELECT
                event_id,
                event_name,
                ticket_price,
                available_tickets,
                status
            FROM events
            WHERE event_id = ?
            FOR UPDATE
        ");

        $stmt->execute([
            $eventId
        ]);

        $event =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$event) {

            $db->rollBack();

            response(
                404,
                false,
                "Event not found"
            );
        }


        // =================================================
        // EVENT MUST BE UPCOMING
        // =================================================

        if (
            $event["status"] !==
            "upcoming"
        ) {

            $db->rollBack();

            response(
                400,
                false,
                "This event is not available for booking"
            );
        }


        // =================================================
        // CHECK AVAILABLE TICKETS
        // =================================================

        if (
            $quantity >
            (int)$event["available_tickets"]
        ) {

            $db->rollBack();

            response(
                400,
                false,
                "Not enough tickets available"
            );
        }


        // =================================================
        // CALCULATE TOTAL
        // =================================================

        $totalAmount =
            (float)$event["ticket_price"]
            * $quantity;


        // =================================================
        // CREATE BOOKING
        // =================================================

        $stmt = $db->prepare("
            INSERT INTO bookings
            (
                customer_id,
                event_id,
                quantity,
                total_amount,
                booking_status
            )
            VALUES (?, ?, ?, ?, 'confirmed')
        ");


        $stmt->execute([
            $customerId,
            $eventId,
            $quantity,
            $totalAmount
        ]);


        $bookingId =
            $db->lastInsertId();


        // =================================================
        // REDUCE AVAILABLE TICKETS
        // =================================================

        $stmt = $db->prepare("
            UPDATE events
            SET available_tickets =
                available_tickets - ?
            WHERE event_id = ?
        ");


        $stmt->execute([
            $quantity,
            $eventId
        ]);


        $db->commit();


        response(
            201,
            true,
            "Booking created successfully",
            [
                "booking_id" =>
                    (int)$bookingId,

                "customer_id" =>
                    $customerId,

                "event_id" =>
                    $eventId,

                "event_name" =>
                    $event["event_name"],

                "quantity" =>
                    $quantity,

                "ticket_price" =>
                    number_format(
                        $event["ticket_price"],
                        2,
                        ".",
                        ""
                    ),

                "total_amount" =>
                    number_format(
                        $totalAmount,
                        2,
                        ".",
                        ""
                    ),

                "booking_status" =>
                    "confirmed"
            ]
        );


    } catch (PDOException $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        response(
            500,
            false,
            "Failed to create booking"
        );
    }
}


// =====================================================
// PUT - UPDATE BOOKING
// =====================================================

if ($method === "PUT") {

    if (!isset($_GET["id"])) {

        response(
            400,
            false,
            "Booking ID is required"
        );
    }


    $bookingId =
        validId($_GET["id"]);


    if (!$bookingId) {

        response(
            400,
            false,
            "Invalid booking ID"
        );
    }


    $data = getJsonBody();


    if (!isset($data["quantity"])) {

        response(
            400,
            false,
            "quantity is required"
        );
    }


    $newQuantity =
        validId($data["quantity"]);


    if (!$newQuantity) {

        response(
            400,
            false,
            "Quantity must be greater than 0"
        );
    }


    $newStatus =
        strtolower(
            trim(
                $data["booking_status"]
                ?? "confirmed"
            )
        );


    $allowedStatuses = [
        "pending",
        "confirmed",
        "cancelled"
    ];


    if (!in_array(
        $newStatus,
        $allowedStatuses,
        true
    )) {

        response(
            400,
            false,
            "Invalid booking status"
        );
    }


    try {

        $db->beginTransaction();


        // =================================================
        // GET CURRENT BOOKING
        // =================================================

        $stmt = $db->prepare("
            SELECT
                booking_id,
                event_id,
                quantity,
                booking_status
            FROM bookings
            WHERE booking_id = ?
            FOR UPDATE
        ");


        $stmt->execute([
            $bookingId
        ]);


        $booking =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$booking) {

            $db->rollBack();

            response(
                404,
                false,
                "Booking not found"
            );
        }


        // =================================================
        // GET EVENT
        // =================================================

        $stmt = $db->prepare("
            SELECT
                event_id,
                event_name,
                ticket_price,
                available_tickets
            FROM events
            WHERE event_id = ?
            FOR UPDATE
        ");


        $stmt->execute([
            $booking["event_id"]
        ]);


        $event =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$event) {

            $db->rollBack();

            response(
                404,
                false,
                "Event not found"
            );
        }


        $oldQuantity =
            (int)$booking["quantity"];

        $oldStatus =
            $booking["booking_status"];

        $available =
            (int)$event[
                "available_tickets"
            ];


        // =================================================
        // TICKET MANAGEMENT
        // =================================================

        $ticketChange = 0;


        // ACTIVE -> CANCELLED

        if (
            $oldStatus !== "cancelled" &&
            $newStatus === "cancelled"
        ) {

            $ticketChange =
                $oldQuantity;
        }


        // CANCELLED -> ACTIVE

        elseif (
            $oldStatus === "cancelled" &&
            $newStatus !== "cancelled"
        ) {

            if (
                $newQuantity >
                $available
            ) {

                $db->rollBack();

                response(
                    400,
                    false,
                    "Not enough tickets available"
                );
            }

            $ticketChange =
                -$newQuantity;
        }


        // ACTIVE -> ACTIVE

        elseif (
            $oldStatus !== "cancelled" &&
            $newStatus !== "cancelled"
        ) {

            $difference =
                $newQuantity -
                $oldQuantity;


            if (
                $difference > 0 &&
                $difference > $available
            ) {

                $db->rollBack();

                response(
                    400,
                    false,
                    "Not enough tickets available"
                );
            }


            $ticketChange =
                -$difference;
        }


        // CANCELLED -> CANCELLED
        // No ticket changes


        // =================================================
        // CALCULATE NEW TOTAL
        // =================================================

        $totalAmount =
            (float)$event[
                "ticket_price"
            ]
            * $newQuantity;


        // =================================================
        // UPDATE BOOKING
        // =================================================

        $stmt = $db->prepare("
            UPDATE bookings
            SET
                quantity = ?,
                total_amount = ?,
                booking_status = ?
            WHERE booking_id = ?
        ");


        $stmt->execute([
            $newQuantity,
            $totalAmount,
            $newStatus,
            $bookingId
        ]);


        // =================================================
        // UPDATE AVAILABLE TICKETS
        // =================================================

        if ($ticketChange != 0) {

            $stmt = $db->prepare("
                UPDATE events
                SET available_tickets =
                    available_tickets + ?
                WHERE event_id = ?
            ");


            $stmt->execute([
                $ticketChange,
                $booking["event_id"]
            ]);
        }


        $db->commit();


        response(
            200,
            true,
            "Booking updated successfully",
            [
                "booking_id" =>
                    $bookingId,

                "event_id" =>
                    (int)$booking[
                        "event_id"
                    ],

                "event_name" =>
                    $event[
                        "event_name"
                    ],

                "old_quantity" =>
                    $oldQuantity,

                "new_quantity" =>
                    $newQuantity,

                "booking_status" =>
                    $newStatus,

                "total_amount" =>
                    number_format(
                        $totalAmount,
                        2,
                        ".",
                        ""
                    )
            ]
        );


    } catch (PDOException $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        response(
            500,
            false,
            "Failed to update booking"
        );
    }
}


// =====================================================
// DELETE BOOKING
// =====================================================

if ($method === "DELETE") {

    if (!isset($_GET["id"])) {

        response(
            400,
            false,
            "Booking ID is required"
        );
    }


    $bookingId =
        validId($_GET["id"]);


    if (!$bookingId) {

        response(
            400,
            false,
            "Invalid booking ID"
        );
    }


    try {

        $db->beginTransaction();


        // =================================================
        // GET BOOKING
        // =================================================

        $stmt = $db->prepare("
            SELECT
                booking_id,
                event_id,
                quantity,
                booking_status
            FROM bookings
            WHERE booking_id = ?
            FOR UPDATE
        ");


        $stmt->execute([
            $bookingId
        ]);


        $booking =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$booking) {

            $db->rollBack();

            response(
                404,
                false,
                "Booking not found"
            );
        }


        // =================================================
        // DELETE BOOKING
        // =================================================

        $stmt = $db->prepare("
            DELETE FROM bookings
            WHERE booking_id = ?
        ");


        $stmt->execute([
            $bookingId
        ]);


        // =================================================
        // RESTORE TICKETS
        // Only restore if booking was active
        // =================================================

        if (
            $booking[
                "booking_status"
            ] !== "cancelled"
        ) {

            $stmt = $db->prepare("
                UPDATE events
                SET available_tickets =
                    available_tickets + ?
                WHERE event_id = ?
            ");


            $stmt->execute([
                $booking["quantity"],
                $booking["event_id"]
            ]);
        }


        $db->commit();


        response(
            200,
            true,
            "Booking deleted successfully",
            [
                "booking_id" =>
                    $bookingId,

                "event_id" =>
                    (int)$booking[
                        "event_id"
                    ]
            ]
        );


    } catch (PDOException $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        response(
            500,
            false,
            "Failed to delete booking"
        );
    }
}


// =====================================================
// METHOD NOT ALLOWED
// =====================================================

response(
    405,
    false,
    "Method not allowed"
);