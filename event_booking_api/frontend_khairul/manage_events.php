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
    exit("Access denied.");
}

if (empty($_SESSION["csrf_token"])) {
    header("Location: login.html");
    exit;
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

  <meta
    name="csrf-token"
    content="<?= htmlspecialchars(
        $_SESSION["csrf_token"],
        ENT_QUOTES,
        "UTF-8"
    ) ?>"
  >

  <title>Manage Events | EventSpace</title>
  <link rel="stylesheet" href="style.css">
  <script src="manage_events.js" defer></script>
</head>
<body>
  <header class="topbar">
    <a class="brand" href="organiser.php">EventSpace<span>.</span></a>
    <a href="organiser.php">Back to dashboard</a>
  </header>

  <main>
    <section class="hero dashboard-hero">
      <p class="eyebrow">ORGANISER WORKSPACE</p>
      <h1>Manage your events.</h1>
      <p>Plan schedules, select venues and manage ticket allocations.</p>
    </section>

    <section class="dashboard-panel management-panel">
      <h2 id="formTitle">Create event</h2>

      <form id="eventForm" class="management-form">
        <fieldset id="eventFields" disabled>
          <label for="eventName">Event name</label>
          <input id="eventName" maxlength="150" required>

          <label for="description">Description</label>
          <textarea id="description" rows="4" required></textarea>

          <label for="venueId">Venue</label>
          <select id="venueId" required></select>

          <label for="eventDate">Event date</label>
          <input id="eventDate" type="date" required>

          <label for="eventTime">Event time</label>
          <input id="eventTime" type="time" step="1" required>

          <label for="ticketPrice">Ticket price (RM)</label>
          <input
            id="ticketPrice"
            type="number"
            min="0"
            max="99999999.99"
            step="0.01"
            required
          >

          <label for="totalTickets">Total tickets</label>
          <input id="totalTickets" type="number" min="1" step="1" required>

          <label for="eventStatus">Status</label>
          <select id="eventStatus">
            <option value="upcoming">Upcoming</option>
            <option value="ongoing">Ongoing</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>

          <p>
            New events start with all tickets available. During editing,
            availability adjusts with the total allocation.
          </p>

          <div class="form-actions">
            <button id="saveEvent" type="submit">Create event</button>
            <button id="cancelEdit" type="button" hidden>Cancel edit</button>
          </div>
        </fieldset>
      </form>

      <p id="eventMessage" role="status" aria-live="polite"></p>
    </section>

    <section class="dashboard-panel management-panel">
      <div class="section-heading">
        <h2>My events</h2>
        <button id="refreshEvents" type="button">Refresh records</button>
      </div>

      <p id="listMessage" role="status" aria-live="polite"></p>

      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th scope="col">Event</th>
              <th scope="col">Date</th>
              <th scope="col">Venue</th>
              <th scope="col">Available / Total</th>
              <th scope="col">Status</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody id="eventRows"></tbody>
        </table>
      </div>
    </section>
  </main>

  <footer>EventSpace · Frontend developed by Khairul</footer>
</body>
</html>