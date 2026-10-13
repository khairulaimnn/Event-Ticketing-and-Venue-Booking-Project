const API_BASE = "../api/";

const eventList = document.getElementById("eventList");
const message = document.getElementById("message");
const refreshBtn = document.getElementById("refreshBtn");
const searchInput = document.getElementById("searchInput");

const money = new Intl.NumberFormat("en-MY", {
  style: "currency",
  currency: "MYR"
});

let events = [];

function addText(parent, tag, value, className = "") {
  const element = document.createElement(tag);
  element.textContent = value;
  element.className = className;
  parent.appendChild(element);
  return element;
}

function renderEvents() {
  const query = searchInput.value.trim().toLowerCase();

  const filtered = events.filter(event =>
    `${event.event_name} ${event.venue_name ?? ""}`
      .toLowerCase()
      .includes(query)
  );

  eventList.replaceChildren();
  message.className = "";
  message.textContent = filtered.length
    ? `${filtered.length} event(s) found.`
    : "No events found.";

  filtered.forEach(event => {
    const card = document.createElement("article");
    card.className = "event-card";

    addText(card, "span", event.status, "badge");
    addText(card, "h3", event.event_name);
    addText(card, "p", event.description || "No description provided.");
    addText(card, "p", `Date: ${event.event_date}`);
    addText(card, "p", `Time: ${event.event_time}`);
    addText(card, "p", `Venue: ${event.venue_name ?? "Not specified"}`);
    addText(card, "p", `Available tickets: ${event.available_tickets}`);
    addText(card, "strong", money.format(Number(event.ticket_price)), "price");

    eventList.appendChild(card);
  });
}

async function loadEvents() {
  refreshBtn.disabled = true;
  searchInput.disabled = true;
  eventList.setAttribute("aria-busy", "true");
  eventList.replaceChildren();
  message.className = "";
  message.textContent = "Loading events...";

  try {
    const response = await fetch(`${API_BASE}events.php`, {
      headers: { Accept: "application/json" },
      signal: AbortSignal.timeout(15000)
    });

    let result;

    try {
      result = await response.json();
    } catch {
      throw new Error(
        "The API did not return valid JSON. Check the backend connection."
      );
    }

    if (!response.ok || result.success !== true) {
      throw new Error(result.message || `Request failed (${response.status}).`);
    }

    if (!Array.isArray(result.data)) {
      throw new Error("The API returned an unexpected events format.");
    }

    events = result.data;
    renderEvents();
  } catch (error) {
    events = [];
    message.className = "error";
    message.textContent =
      error.name === "TimeoutError"
        ? "The request timed out. Click Refresh events to try again."
        : `Unable to load events: ${error.message}`;
  } finally {
    refreshBtn.disabled = false;
    searchInput.disabled = false;
    eventList.setAttribute("aria-busy", "false");
  }
}

refreshBtn.addEventListener("click", loadEvents);
searchInput.addEventListener("input", renderEvents);

loadEvents();