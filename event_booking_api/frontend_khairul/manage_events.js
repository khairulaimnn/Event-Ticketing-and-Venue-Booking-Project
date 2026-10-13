const organiserId = Number(
  document.querySelector('meta[name="organiser-id"]').content
);

const csrfToken = document.querySelector(
  'meta[name="csrf-token"]'
).content;

const form = document.getElementById("eventForm");
const fields = document.getElementById("eventFields");
const rows = document.getElementById("eventRows");
const message = document.getElementById("eventMessage");
const listMessage = document.getElementById("listMessage");
const refreshButton = document.getElementById("refreshEvents");
const cancelButton = document.getElementById("cancelEdit");

const inputs = {
  event_name: document.getElementById("eventName"),
  description: document.getElementById("description"),
  venue_id: document.getElementById("venueId"),
  event_date: document.getElementById("eventDate"),
  event_time: document.getElementById("eventTime"),
  ticket_price: document.getElementById("ticketPrice"),
  total_tickets: document.getElementById("totalTickets"),
  status: document.getElementById("eventStatus")
};

let editingEvent = null;
let venues = [];
let busy = false;
let loading = false;
let venuesReady = false;

async function apiRequest(endpoint, method = "GET", payload = null) {
  const headers = { Accept: "application/json" };

  const options = {
    method,
    headers,
    credentials: "same-origin",
    signal: AbortSignal.timeout(15000)
  };

  if (method !== "GET") {
    headers["X-CSRF-Token"] = csrfToken;
  }

  if (payload !== null) {
    headers["Content-Type"] = "application/json";
    options.body = JSON.stringify(payload);
  }

  const response = await fetch(`../api/${endpoint}`, options);

  let result;

  try {
    result = await response.json();
  } catch {
    throw new Error("The API did not return valid JSON.");
  }

  if (!response.ok || result.success !== true) {
    throw new Error(result.message || `Request failed (${response.status}).`);
  }

  return result;
}

function errorText(error) {
  return error.name === "TimeoutError"
    ? "Request timed out. Refresh records to check the result."
    : error.message;
}

function showMessage(text, error = false) {
  message.textContent = text;
  message.className = error ? "error" : "success";
}

function updateControls() {
  fields.disabled = busy || loading || !venuesReady;
  refreshButton.disabled = busy || loading;

  rows.querySelectorAll("button").forEach(button => {
    button.disabled = busy || loading;
  });
}

function resetForm() {
  editingEvent = null;
  form.reset();
  document.getElementById("formTitle").textContent = "Create event";
  document.getElementById("saveEvent").textContent = "Create event";
  cancelButton.hidden = true;
}

function editEvent(event) {
  if (busy || loading) return;

  editingEvent = event;

  Object.entries(inputs).forEach(([key, input]) => {
    input.value = event[key];
  });

  document.getElementById("formTitle").textContent = "Edit event";
  document.getElementById("saveEvent").textContent = "Save changes";
  cancelButton.hidden = false;
  message.textContent = "";

  inputs.event_name.focus();
  form.scrollIntoView({ behavior: "smooth", block: "center" });
}

function renderEvents(events) {
  rows.replaceChildren();

  if (!events.length) {
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.colSpan = 6;
    cell.textContent = "No events found.";
    row.appendChild(cell);
    rows.appendChild(row);
    return;
  }

  events.forEach(event => {
    const row = document.createElement("tr");

    [
      event.event_name,
      event.event_date,
      event.venue_name ?? "Not specified",
      `${event.available_tickets} / ${event.total_tickets}`,
      event.status
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
    editButton.addEventListener("click", () => editEvent(event));

    const deleteButton = document.createElement("button");
    deleteButton.type = "button";
    deleteButton.textContent = "Delete";
    deleteButton.className = "danger-button";
    deleteButton.addEventListener("click", () => deleteEvent(event));

    actions.append(editButton, deleteButton);
    row.appendChild(actions);
    rows.appendChild(row);
  });
}

async function loadRecords() {
  loading = true;
  venuesReady = false;
  updateControls();
  rows.replaceChildren();
  listMessage.className = "";
  listMessage.textContent = "Loading events and venues...";

  try {
    const [eventResult, venueResult] = await Promise.all([
      apiRequest(`events.php?organiser_id=${organiserId}`),
      apiRequest("venues.php")
    ]);

    if (
      !Array.isArray(eventResult.data)
      || !Array.isArray(venueResult.data)
    ) {
      throw new Error("Unexpected API response format.");
    }

    if (eventResult.data.some(event =>
      Number(event.organiser_id) !== organiserId
    )) {
      throw new Error("The API returned another organiser's events.");
    }

    venues = venueResult.data;

    const previousVenue = inputs.venue_id.value;
    inputs.venue_id.replaceChildren();

    const placeholder = new Option("Select a venue", "");
    inputs.venue_id.add(placeholder);

    venues.forEach(venue => {
      inputs.venue_id.add(new Option(
        `${venue.venue_name} — capacity ${venue.capacity}`,
        venue.venue_id
      ));
    });

    if (venues.some(venue =>
      String(venue.venue_id) === previousVenue
    )) {
      inputs.venue_id.value = previousVenue;
    }

    venuesReady = venues.length > 0;
    renderEvents(eventResult.data);

    listMessage.textContent = venuesReady
      ? `${eventResult.data.length} event(s) found.`
      : "Ask the Administrator to create a venue before adding events.";
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

  if (busy || loading || !form.reportValidity()) return;

  const payload = { organiser_id: organiserId };

  Object.entries(inputs).forEach(([key, input]) => {
    payload[key] = input.value.trim();
  });

  payload.venue_id = Number(payload.venue_id);
  payload.ticket_price = Number(payload.ticket_price);
  payload.total_tickets = Number(payload.total_tickets);

  const selectedVenue = venues.find(
    venue => Number(venue.venue_id) === payload.venue_id
  );

  if (!payload.event_name || !payload.description) {
    showMessage("Enter an event name and description.", true);
    return;
  }

  if (
    !selectedVenue
    || !Number.isInteger(payload.total_tickets)
    || payload.total_tickets < 1
    || payload.total_tickets > Number(selectedVenue.capacity)
  ) {
    showMessage("Ticket allocation must fit within the venue capacity.", true);
    return;
  }

  if (!Number.isFinite(payload.ticket_price) || payload.ticket_price < 0) {
    showMessage("Enter a valid ticket price.", true);
    return;
  }

  if (payload.event_time.length === 5) {
    payload.event_time += ":00";
  }

  busy = true;
  updateControls();
  message.className = "";
  message.textContent = "Saving event...";

  try {
    if (editingEvent !== null) {
      // Read fresh availability before adjusting the allocation.
      const latest = await apiRequest(
        `events.php?id=${encodeURIComponent(editingEvent.event_id)}`
      );

      const current = latest.data;
      const allocated = Number(current.total_tickets)
        - Number(current.available_tickets);

      if (
        !Number.isInteger(allocated)
        || allocated < 0
        || payload.total_tickets < allocated
      ) {
        throw new Error(
          "Ticket data needs checking, or the total is below allocated tickets."
        );
      }

      payload.available_tickets = payload.total_tickets - allocated;
    }

    const endpoint = editingEvent === null
      ? "events.php"
      : `events.php?id=${encodeURIComponent(editingEvent.event_id)}`;

    const result = await apiRequest(
      endpoint,
      editingEvent === null ? "POST" : "PUT",
      payload
    );

    resetForm();
    showMessage(result.message || "Event saved successfully.");
    await loadRecords();
  } catch (error) {
    showMessage(errorText(error), true);
  } finally {
    busy = false;
    updateControls();
  }
});

async function deleteEvent(event) {
  if (busy || loading) return;

  if (!window.confirm(`Delete ${event.event_name}?`)) return;

  busy = true;
  updateControls();
  message.className = "";
  message.textContent = "Deleting event...";

  try {
    const result = await apiRequest(
      `events.php?id=${encodeURIComponent(event.event_id)}`,
      "DELETE"
    );

    if (
      editingEvent !== null
      && Number(editingEvent.event_id) === Number(event.event_id)
    ) {
      resetForm();
    }

    showMessage(result.message || "Event deleted successfully.");
    await loadRecords();
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

refreshButton.addEventListener("click", loadRecords);

loadRecords();