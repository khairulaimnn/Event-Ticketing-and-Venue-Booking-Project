<?php
session_name("event_booking_session");
session_start();
header("Cache-Control: no-store");

if (empty($_SESSION["user"])) {
    header("Location: login.html");
    exit;
}

if ($_SESSION["user"]["role"] !== "customer") {
    http_response_code(403);
    exit("Access denied. Customer account required.");
}

if (empty($_SESSION["csrf_token"])) {
    header("Location: login.html");
    exit;
}

function esc($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$user = $_SESSION["user"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="customer-id" content="<?= esc($user["user_id"]) ?>">
    <meta name="csrf-token" content="<?= esc($_SESSION["csrf_token"]) ?>">
    <title>Customer Dashboard | EventSpace</title>
    <link rel="stylesheet" href="style.css">
    <script src="customer.js" defer></script>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="index.html">EventSpace.</a>
        <a class="button" href="index.html">Browse events</a>
    <form action="logout.php" method="POST" class="logout-form">
    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(
            (string)($_SESSION["csrf_token"] ?? ""),
            ENT_QUOTES,
            "UTF-8"
        ) ?>"
    >
    <button type="submit">Logout</button>
</form>
    </header>

    <main>
        <section class="dashboard-hero">
            <p>Customer portal</p>
            <h1>Welcome, <?= esc($user["name"]) ?></h1>
            <p>Book your next experience and manage your tickets.</p>
        </section>

        <section class="dashboard-panel">
            <h2 id="formTitle">Book tickets</h2>

            <form id="bookingForm" class="management-form">
                <fieldset id="bookingFields" disabled>
                    <label for="bookingEvent">Event</label>
                    <select id="bookingEvent" required>
                        <option value="">Loading events...</option>
                    </select>

                    <label for="bookingQuantity">Number of tickets</label>
                    <input
                        id="bookingQuantity"
                        type="number"
                        min="1"
                        max="2147483647"
                        step="1"
                        value="1"
                        required
                    >

                    <p id="pricePreview">Select an event to see the price.</p>

                    <div class="form-actions">
                        <button id="saveBooking" type="submit">
                            Book tickets
                        </button>
                        <button id="cancelBookingEdit" type="button" hidden>
                            Cancel editing
                        </button>
                    </div>
                </fieldset>
            </form>

            <p id="bookingMessage" role="status" aria-live="polite"></p>
        </section>

        <section class="dashboard-panel">
            <div class="form-actions">
                <h2>My bookings</h2>
                <button id="refreshBookings" type="button">Refresh</button>
            </div>

            <p id="bookingListMessage" role="status" aria-live="polite"></p>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Tickets</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="bookingRows"></tbody>
                </table>
            </div>
        </section>
        <section id="bookingQrPanel" class="dashboard-panel" hidden>
    <h2>Booking QR reference</h2>
    <p id="bookingQrReference"></p>
    <img
        id="bookingQrImage"
        width="250"
        height="250"
        alt="QR code containing your booking reference"
    >
    <p>Keep this reference with your booking details.</p>
</section>
    </main>
</body>
</html>