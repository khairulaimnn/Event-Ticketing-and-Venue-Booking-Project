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

  <meta
    name="current-user-id"
    content="<?= (int) $_SESSION["user"]["user_id"] ?>"
  >

  <title>Manage Users | EventSpace</title>
  <link rel="stylesheet" href="style.css">
  <script src="manage_users.js" defer></script>
</head>
<body>
  <header class="topbar">
    <a class="brand" href="admin.php">EventSpace<span>.</span></a>
    <a href="admin.php">Back to dashboard</a>
  </header>

  <main>
    <section class="hero dashboard-hero">
      <p class="eyebrow">ADMINISTRATOR WORKSPACE</p>
      <h1>Manage users.</h1>
      <p>Create and update accounts for the event system.</p>
    </section>

    <section class="dashboard-panel management-panel">
      <h2 id="formTitle">Create user</h2>

      <form id="userForm" class="management-form">
        <fieldset id="formFields">
          <label for="userName">Full name</label>
          <input id="userName" type="text" maxlength="100" required>

          <label for="userEmail">Email address</label>
          <input id="userEmail" type="email" maxlength="150" required>

          <label for="userRole">Role</label>
          <select id="userRole" required>
            <option value="customer">Customer</option>
            <option value="organiser">Organiser</option>
            <option value="admin">Administrator</option>
          </select>

          <label for="userPassword">Password</label>
          <input
            id="userPassword"
            type="password"
            minlength="6"
            maxlength="72"
            autocomplete="new-password"
            required
          >
          <p id="passwordHint">
            Use at least 6 characters.
          </p>

          <div class="form-actions">
            <button id="saveUser" type="submit">Create user</button>
            <button id="cancelEdit" type="button" hidden>Cancel edit</button>
          </div>
        </fieldset>
      </form>

      <p id="userMessage" role="status" aria-live="polite"></p>
    </section>

    <section class="dashboard-panel management-panel">
      <div class="section-heading">
        <h2>Registered users</h2>
        <button id="refreshUsers" type="button">Refresh users</button>
      </div>

      <p id="listMessage" role="status" aria-live="polite"></p>

      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th scope="col">ID</th>
              <th scope="col">Name</th>
              <th scope="col">Email</th>
              <th scope="col">Role</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody id="userRows"></tbody>
        </table>
      </div>
    </section>
  </main>

  <footer>EventSpace · Frontend developed by Khairul</footer>
</body>
</html>