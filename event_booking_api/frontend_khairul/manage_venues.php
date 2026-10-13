<?php
session_name("event_booking_session");
session_start();

header("Cache-Control: no-store");

if (!isset($_SESSION["user"])) {
    header("Location: login.html");
    exit;
}

if ($_SESSION["user"]["role"] !== "admin") {
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
    name="csrf-token"
    content="<?= htmlspecialchars(
        $_SESSION["csrf_token"],
        ENT_QUOTES,
        "UTF-8"
    ) ?>"
  >

  <title>Manage Venues | EventSpace</title>
  <link rel="stylesheet" href="style.css">
  <script src="manage_venues.js" defer></script>
</head>
<body>
  <header class="topbar">
    <a class="brand" href="admin.php">EventSpace<span>.</span></a>
    <a href="admin.php">Back to dashboard</a>
  </header>

  <main>
    <section class="hero dashboard-hero">
      <p class="eyebrow">ADMINISTRATOR WORKSPACE</p>
      <h1>Manage venues.</h1>
      <p>Keep venue locations and capacities up to date.</p>
    </section>

    <section class="dashboard-panel management-panel">
      <h2 id="formTitle">Create venue</h2>

      <form id="venueForm" class="management-form">
        <fieldset id="formFields">
          <label for="venueName">Venue name</label>
          <input id="venueName" type="text" maxlength="150" required>

          <label for="venueLocation">Location</label>
          <input id="venueLocation" type="text" maxlength="255" required>

          <label for="venueCapacity">Capacity</label>
          <input
            id="venueCapacity"
            type="number"
            min="1"
            max="2147483647"
            step="1"
            required
          >

          <div class="form-actions">
            <button id="saveVenue" type="submit">Create venue</button>
            <button id="cancelEdit" type="button" hidden>Cancel edit</button>
          </div>
        </fieldset>
      </form>

      <p id="venueMessage" role="status" aria-live="polite"></p>
    </section>

    <section class="dashboard-panel management-panel">
      <div class="section-heading">
        <h2>Available venues</h2>
        <button id="refreshVenues" type="button">Refresh venues</button>
      </div>

      <p id="listMessage" role="status" aria-live="polite"></p>

      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th scope="col">ID</th>
              <th scope="col">Venue</th>
              <th scope="col">Location</th>
              <th scope="col">Capacity</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody id="venueRows"></tbody>
        </table>
      </div>
    </section>
  </main>

  <footer>EventSpace · Frontend developed by Khairul</footer>
</body>
</html>