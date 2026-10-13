const adminMessage = document.getElementById("adminMessage");
const refreshAdmin = document.getElementById("refreshAdmin");
const usersTable = document.getElementById("usersTable");

async function getRecords(resource) {
  const response = await fetch(`../api/${resource}.php`, {
    credentials: "same-origin",
    headers: { Accept: "application/json" },
    signal: AbortSignal.timeout(15000)
  });

  let result;

  try {
    result = await response.json();
  } catch {
    throw new Error(`${resource}: the API did not return valid JSON.`);
  }

  if (!response.ok || result.success !== true) {
    throw new Error(
      `${resource}: ${result.message || "Unable to retrieve records."}`
    );
  }

  if (!Array.isArray(result.data)) {
    throw new Error(`${resource}: unexpected response format.`);
  }

  return result.data;
}

function displayUsers(users) {
  usersTable.replaceChildren();

  if (users.length === 0) {
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.colSpan = 4;
    cell.textContent = "No users found.";
    row.appendChild(cell);
    usersTable.appendChild(row);
    return;
  }

  users.forEach(user => {
    const row = document.createElement("tr");

    [
      user.user_id,
      user.name,
      user.email,
      user.role
    ].forEach(value => {
      const cell = document.createElement("td");
      cell.textContent = value;
      row.appendChild(cell);
    });

    usersTable.appendChild(row);
  });
}

async function loadDashboard() {
  refreshAdmin.disabled = true;
  adminMessage.className = "";
  adminMessage.textContent = "Loading dashboard...";
  usersTable.replaceChildren();

  const resources = ["users", "events", "venues", "bookings"];

  resources.forEach(resource => {
    document.getElementById(`${resource}Count`).textContent = "—";
  });

  try {
    const results = await Promise.all(
      resources.map(resource => getRecords(resource))
    );

    results.forEach((records, index) => {
      const resource = resources[index];
      document.getElementById(`${resource}Count`)
        .textContent = records.length;
    });

    displayUsers(results[0]);
    adminMessage.textContent = "Dashboard updated successfully.";
  } catch (error) {
    adminMessage.className = "error";
    adminMessage.textContent =
      error.name === "TimeoutError"
        ? "The request timed out. Refresh to try again."
        : error.message;
  } finally {
    refreshAdmin.disabled = false;
  }
}

refreshAdmin.addEventListener("click", loadDashboard);

loadDashboard();