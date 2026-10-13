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
    exit("Access denied. This dashboard requires an Administrator account.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administrator | EventSpace</title>
  <link rel="stylesheet" href="style.css">
  <script src="admin.js" defer></script>
</head>
<body>
  <header class="topbar">
    <a class="brand" href="index.html">EventSpace<span>.</span></a>
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
      <p class="eyebrow">ADMINISTRATOR WORKSPACE</p>
      <h1>System overview.</h1>
      <p>
        Welcome,
        <?= htmlspecialchars(
            $_SESSION["user"]["name"],
            ENT_QUOTES,
            "UTF-8"
        ) ?>.
        View your system records below.
      </p>
    </section>

    <p id="adminMessage" role="status" aria-live="polite"></p>

    <section class="stats-grid" aria-label="System totals">
      <article class="stat-card">
        <p>Users</p>
        <strong id="usersCount">—</strong>
      </article>

      <article class="stat-card">
        <p>Events</p>
        <strong id="eventsCount">—</strong>
      </article>

      <article class="stat-card">
        <p>Venues</p>
        <strong id="venuesCount">—</strong>
        <div>
            <a href="manage_venues.php">Manage venues</a>
        </div>
      </article>

      <article class="stat-card">
        <p>Bookings</p>
        <strong id="bookingsCount">—</strong>
      </article>
    </section>

    <section class="dashboard-panel">
      <div class="section-heading">
        <div>
            <h2>System users</h2>
            <a class="button" href="manage_users.php">Manage users</a>
        </div>
        <button id="refreshAdmin" type="button">Refresh dashboard</button>
      </div>

      <div class="table-scroll">
        <table>
          <caption>Users registered in the shared database</caption>
          <thead>
            <tr>
              <th scope="col">ID</th>
              <th scope="col">Name</th>
              <th scope="col">Email</th>
              <th scope="col">Role</th>
            </tr>
          </thead>
          <tbody id="usersTable"></tbody>
        </table>
      </div>
    </section>
  </main>

  <footer>EventSpace · Frontend developed by Khairul</footer>
</body>
</html>