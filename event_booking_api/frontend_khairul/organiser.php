<?php
session_name("event_booking_session");
session_start();

header("Cache-Control: no-store");

if (!isset($_SESSION["user"])) {
    header("Location: login.html");
    exit;
}

if ($_SESSION["user"]["role"] !== "organiser") {
    http_response_code(403);
    exit("Access denied. An Organiser account is required.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <meta
    name="organiser-id"
    content="<?= (int) $_SESSION["user"]["user_id"] ?>"
  >

  <title>Organiser | EventSpace</title>
  <link rel="stylesheet" href="style.css">
  <script src="organiser.js" defer></script>
  <script src="organiser_bookings.js" defer></script>
</head>
<body>
  <header class="topbar">
    <a class="brand" href="organiser.php">EventSpace<span>.</span></a>
    <a href="index.html">Browse events</a>
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
    <section class="hero dashboard-hero">
      <p class="eyebrow">ORGANISER WORKSPACE</p>
      <h1>Your events, organised.</h1>

      <p>
        Welcome,
        <?= htmlspecialchars(
            $_SESSION["user"]["name"],
            ENT_QUOTES,
            "UTF-8"
        ) ?>.
        View your event schedules and ticket availability.
      </p>
    </section>

    <p id="organiserMessage" role="status" aria-live="polite"></p>

    <section class="stats-grid" aria-label="Your event totals">
      <article class="stat-card">
        <p>My events</p>
        <strong id="myEventsCount">—</strong>
      </article>

      <article class="stat-card">
        <p>Upcoming events</p>
        <strong id="upcomingCount">—</strong>
      </article>

      <article class="stat-card">
        <p>Total ticket allocation</p>
        <strong id="totalTicketsCount">—</strong>
      </article>

      <article class="stat-card">
        <p>Available tickets</p>
        <strong id="availableTicketsCount">—</strong>
      </article>
    </section>

    <section class="dashboard-panel">
      <div class="section-heading">
        <div>
        <h2>My events</h2>

        <div class="event-toolbar">
          <a class="button" href="manage_events.php">Manage events</a>
          <button id="refreshOrganiser" type="button">Refresh events</button>
        </div>
        </div>

      <div class="table-scroll">
        <table>
          <caption>Events assigned to your organiser account</caption>
          <thead>
            <tr>
              <th scope="col">Event</th>
              <th scope="col">Date</th>
              <th scope="col">Time</th>
              <th scope="col">Venue</th>
              <th scope="col">Price</th>
              <th scope="col">Available / Total</th>
              <th scope="col">Status</th>
            </tr>
          </thead>
          <tbody id="myEventRows"></tbody>
        </table>
      </div>
    </section>
    <section class="dashboard-panel">
    <h2>Bookings for my events</h2>

    <div class="event-toolbar">
        <label for="organiserBookingStatus">Booking status</label>

        <select id="organiserBookingStatus">
            <option value="">All statuses</option>
            <option value="confirmed">Confirmed</option>
            <option value="pending">Pending</option>
            <option value="cancelled">Cancelled</option>
        </select>

        <button id="refreshOrganiserBookings" type="button">
            Refresh bookings
        </button>
    </div>

    <p id="organiserBookingMessage" role="status" aria-live="polite"></p>

    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Booking ID</th>
                    <th>Event</th>
                    <th>Customer</th>
                    <th>Tickets</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="organiserBookingRows"></tbody>
        </table>
    </div>
</section>
  </main>

  <footer>EventSpace · Frontend developed by Khairul</footer>
</body>
</html>