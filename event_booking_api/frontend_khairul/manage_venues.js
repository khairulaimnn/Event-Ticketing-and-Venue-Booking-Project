const form = document.getElementById("venueForm");
const fields = document.getElementById("formFields");
const nameInput = document.getElementById("venueName");
const locationInput = document.getElementById("venueLocation");
const capacityInput = document.getElementById("venueCapacity");
const message = document.getElementById("venueMessage");
const listMessage = document.getElementById("listMessage");
const rows = document.getElementById("venueRows");
const refreshButton = document.getElementById("refreshVenues");
const cancelButton = document.getElementById("cancelEdit");

const csrfToken = document.querySelector(
  'meta[name="csrf-token"]'
).content;

let editingId = null;
let busy = false;
let loading = false;

async function apiRequest(method = "GET", id = null, payload = null) {
  const url = "../api/venues.php" +
    (id === null ? "" : `?id=${encodeURIComponent(id)}`);

  const headers = { Accept: "application/json" };

  if (method !== "GET") {
    headers["X-CSRF-Token"] = csrfToken;
  }

  const options = {
    method,
    headers,
    credentials: "same-origin",
    signal: AbortSignal.timeout(15000)
  };

  if (payload !== null) {
    headers["Content-Type"] = "application/json";
    options.body = JSON.stringify(payload);
  }

  const response = await fetch(url, options);

  let result;

  try {
    result = await response.json();
  } catch {
    throw new Error("The API did not return valid JSON.");
  }

  if (!response.ok || result.success !== true) {
    throw new Error(
      result.message || `Request failed (${response.status}).`
    );
  }

  return result;
}

function errorText(error) {
  return error.name === "TimeoutError"
    ? "Request timed out. Refresh the venue list to check the result."
    : error.message;
}

function showMessage(text, error = false) {
  message.textContent = text;
  message.className = error ? "error" : "success";
}

function updateControls() {
  fields.disabled = busy;
  refreshButton.disabled = busy || loading;

  rows.querySelectorAll("button").forEach(button => {
    button.disabled = busy || loading;
  });
}

function resetForm() {
  editingId = null;
  form.reset();
  document.getElementById("formTitle").textContent = "Create venue";
  document.getElementById("saveVenue").textContent = "Create venue";
  cancelButton.hidden = true;
}

function editVenue(venue) {
  if (busy || loading) return;

  editingId = venue.venue_id;
  nameInput.value = venue.venue_name;
  locationInput.value = venue.location;
  capacityInput.value = venue.capacity;

  document.getElementById("formTitle").textContent = "Edit venue";
  document.getElementById("saveVenue").textContent = "Save changes";
  cancelButton.hidden = false;
  message.textContent = "";

  nameInput.focus();
  form.scrollIntoView({ behavior: "smooth", block: "center" });
}

function renderVenues(venues) {
  rows.replaceChildren();

  if (venues.length === 0) {
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.colSpan = 5;
    cell.textContent = "No venues found.";
    row.appendChild(cell);
    rows.appendChild(row);
    return;
  }

  venues.forEach(venue => {
    const row = document.createElement("tr");

    [
      venue.venue_id,
      venue.venue_name,
      venue.location,
      venue.capacity
    ].forEach(value => {
      const cell = document.createElement("td");
      cell.textContent = value;
      row.appendChild(cell);
    });

    const actions = document.createElement("td");
    actions.className = "row-actions";

    const editButton = document.createElement("button");
    editButton.type = "button";
    editButton.textContent = "Edit";
    editButton.addEventListener("click", () => editVenue(venue));

    const deleteButton = document.createElement("button");
    deleteButton.type = "button";
    deleteButton.textContent = "Delete";
    deleteButton.className = "danger-button";
    deleteButton.addEventListener("click", () => deleteVenue(venue));

    actions.append(editButton, deleteButton);
    row.appendChild(actions);
    rows.appendChild(row);
  });
}

async function loadVenues() {
  loading = true;
  updateControls();
  listMessage.className = "";
  listMessage.textContent = "Loading venues...";
  rows.replaceChildren();

  try {
    const result = await apiRequest();

    if (!Array.isArray(result.data)) {
      throw new Error("Unexpected venues response format.");
    }

    renderVenues(result.data);
    listMessage.textContent = `${result.data.length} venue(s) found.`;
  } catch (error) {
    listMessage.className = "error";
    listMessage.textContent = errorText(error);
  } finally {
    loading = false;
    updateControls();
  }
}

form.addEventListener("submit", async event => {
  event.preventDefault();

  if (busy || !form.reportValidity()) return;

  const capacity = Number(capacityInput.value);

  if (
    !nameInput.value.trim()
    || !locationInput.value.trim()
    || !Number.isInteger(capacity)
    || capacity < 1
  ) {
    showMessage("Enter a venue name, location and positive whole capacity.", true);
    return;
  }

  const payload = {
    venue_name: nameInput.value.trim(),
    location: locationInput.value.trim(),
    capacity
  };

  busy = true;
  updateControls();
  message.className = "";
  message.textContent = "Saving venue...";

  try {
    const result = await apiRequest(
      editingId === null ? "POST" : "PUT",
      editingId,
      payload
    );

    resetForm();
    showMessage(result.message || "Venue saved successfully.");
    await loadVenues();
  } catch (error) {
    showMessage(errorText(error), true);
  } finally {
    busy = false;
    updateControls();
  }
});

async function deleteVenue(venue) {
  if (busy || loading) return;

  if (!window.confirm(`Delete ${venue.venue_name}?`)) return;

  busy = true;
  updateControls();
  message.className = "";
  message.textContent = "Deleting venue...";

  try {
    const result = await apiRequest("DELETE", venue.venue_id);

    if (Number(editingId) === Number(venue.venue_id)) {
      resetForm();
    }

    showMessage(result.message || "Venue deleted successfully.");
    await loadVenues();
  } catch (error) {
    showMessage(errorText(error), true);
  } finally {
    busy = false;
    updateControls();
  }
}

cancelButton.addEventListener("click", () => {
  resetForm();
  message.textContent = "";
});

refreshButton.addEventListener("click", loadVenues);

loadVenues();