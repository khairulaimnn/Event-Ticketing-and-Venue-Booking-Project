const organiserId = document.querySelector(
  'meta[name="organiser-id"]'
).content;

const message = document.getElementById("organiserMessage");
const rows = document.getElementById("myEventRows");
const refreshButton = document.getElementById("refreshOrganiser");

const money = new Intl.NumberFormat("en-MY", {
  style: "currency",
  currency: "MYR"
});

const countIds = [
  "myEventsCount",
  "upcomingCount",
  "totalTicketsCount",
  "availableTicketsCount"
];

function renderEvents(events) {
  rows.replaceChildren();

  if (events.length === 0) {
    const row = document.createElement("tr");
    const cell = document.createElement("td");

    cell.colSpan = 7;
    cell.textContent = "You have no events yet.";

    row.appendChild(cell);
    rows.appendChild(row);
    return;
  }

  events.forEach(event => {
    const row = document.createElement("tr");

    const values = [
      event.event_name,
      event.event_date,
      event.event_time,
      event.venue_name ?? "Not specified",
      money.format(Number(event.ticket_price)),
      `${event.available_tickets} / ${event.total_tickets}`,
      event.status
    ];

    values.forEach(value => {
      const cell = document.createElement("td");
      cell.textContent = value;
      row.appendChild(cell);
    });

    rows.appendChild(row);
  });
}

async function loadMyEvents() {
  refreshButton.disabled = true;
  message.className = "";
  message.textContent = "Loading your events...";
  rows.replaceChildren();

  countIds.forEach(id => {
    document.getElementById(id).textContent = "—";
  });

  try {
    const response = await fetch(
      `../api/events.php?organiser_id=${encodeURIComponent(organiserId)}`,
      {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
        signal: AbortSignal.timeout(15000)
      }
    );

    let result;

    try {
      result = await response.json();
    } catch {
      throw new Error("The Events API did not return valid JSON.");
    }

    if (!response.ok || result.success !== true) {
      throw new Error(result.message || "Unable to load your events.");
    }

    if (!Array.isArray(result.data)) {
      throw new Error("Unexpected events response format.");
    }

    const events = result.data;

    // Check that the API applied the requested organiser filter.
    if (events.some(event =>
      Number(event.organiser_id) !== Number(organiserId)
    )) {
      throw new Error("The API returned events for a different organiser.");
    }

    document.getElementById("myEventsCount")
      .textContent = events.length;

    document.getElementById("upcomingCount")
      .textContent = events.filter(
        event => event.status === "upcoming"
      ).length;

    document.getElementById("totalTicketsCount")
      .textContent = events.reduce(
        (total, event) => total + Number(event.total_tickets),
        0
      );

    document.getElementById("availableTicketsCount")
      .textContent = events.reduce(
        (total, event) => total + Number(event.available_tickets),
        0
      );

    renderEvents(events);
    message.textContent = "Your events loaded successfully.";
  } catch (error) {
    message.className = "error";
    message.textContent =
      error.name === "TimeoutError"
        ? "The request timed out. Refresh to try again."
        : error.message;
  } finally {
    refreshButton.disabled = false;
  }
}

refreshButton.addEventListener("click", loadMyEvents);

loadMyEvents();