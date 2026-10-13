"use strict";

const customerId = Number(
    document.querySelector('meta[name="customer-id"]').content
);
const csrfToken = document.querySelector(
    'meta[name="csrf-token"]'
).content;

const form = document.getElementById("bookingForm");
const fields = document.getElementById("bookingFields");
const eventSelect = document.getElementById("bookingEvent");
const quantityInput = document.getElementById("bookingQuantity");
const formTitle = document.getElementById("formTitle");
const saveButton = document.getElementById("saveBooking");
const cancelEditButton = document.getElementById("cancelBookingEdit");
const refreshButton = document.getElementById("refreshBookings");
const message = document.getElementById("bookingMessage");
const listMessage = document.getElementById("bookingListMessage");
const rows = document.getElementById("bookingRows");
const preview = document.getElementById("pricePreview");

let events = [];
let editing = null;
let busy = false;
let loading = false;
let ready = false;

const money = value => `RM ${Number(value).toFixed(2)}`;

function showMessage(text, error = false) {
    message.textContent = text;
    message.className = error ? "error" : "success";
}

function updateControls() {
    fields.disabled = busy || loading || !ready;
    eventSelect.disabled = Boolean(editing);
    refreshButton.disabled = busy || loading;

    rows.querySelectorAll("button").forEach(button => {
        button.disabled = busy || loading;
    });
}

async function request(path, method = "GET", body) {
    const headers = { Accept: "application/json" };

    if (method !== "GET") {
        headers["X-CSRF-Token"] = csrfToken;
    }

    if (body !== undefined) {
        headers["Content-Type"] = "application/json";
    }

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 15000);

    try {
        const response = await fetch(`../api/${path}`, {
            method,
            credentials: "same-origin",
            headers,
            body: body === undefined ? undefined : JSON.stringify(body),
            signal: controller.signal
        });

        let result;

        try {
            result = await response.json();
        } catch {
            throw new Error("The server returned an invalid response.");
        }

        if (!response.ok || result.success !== true) {
            throw new Error(result.message || "The request failed.");
        }

        return result.data;
    } catch (error) {
        if (error.name === "AbortError") {
            throw new Error("The request timed out. Please try again.");
        }

        throw error;
    } finally {
        clearTimeout(timer);
    }
}

function updatePreview() {
    const event = events.find(
        item => Number(item.event_id) === Number(eventSelect.value)
    );
    const quantity = Number(quantityInput.value);

    if (!event) {
        preview.textContent = "Select an event to see the price.";
        return;
    }

    preview.textContent =
        `${money(event.ticket_price)} per ticket · ` +
        `${event.available_tickets} available · ` +
        `Estimated total: ${money(
            Number.isInteger(quantity) && quantity > 0
                ? Number(event.ticket_price) * quantity
                : 0
        )}`;
}

function fillEvents() {
    const selectedId = editing
        ? String(editing.event_id)
        : eventSelect.value;

    eventSelect.replaceChildren(new Option("Select an event", ""));

    events.forEach(event => {
        const isCurrent = editing &&
            Number(event.event_id) === Number(editing.event_id);

        if (
            isCurrent ||
            (event.status === "upcoming" &&
                Number(event.available_tickets) > 0)
        ) {
            eventSelect.add(new Option(
                `${event.event_name} — ${money(event.ticket_price)}`,
                String(event.event_id)
            ));
        }
    });

    eventSelect.value = selectedId;
    updatePreview();
}

function resetForm() {
    editing = null;
    form.reset();
    formTitle.textContent = "Book tickets";
    saveButton.textContent = "Book tickets";
    cancelEditButton.hidden = true;
    fillEvents();
    updateControls();
}

function startEdit(booking) {
    editing = booking;
    formTitle.textContent = `Edit booking #${booking.booking_id}`;
    saveButton.textContent = "Save quantity";
    cancelEditButton.hidden = false;
    quantityInput.value = booking.quantity;

    fillEvents();
    updateControls();
    quantityInput.focus();
}

function cell(row, value) {
    const td = document.createElement("td");
    td.textContent = String(value);
    row.appendChild(td);
    return td;
}

function renderBookings(bookings) {
    rows.replaceChildren();

    if (bookings.length === 0) {
        const row = document.createElement("tr");
        cell(row, "You have no bookings yet.").colSpan = 7;
        rows.appendChild(row);
        return;
    }

    bookings.forEach(booking => {
        const row = document.createElement("tr");

        cell(row, booking.booking_id);
        cell(row, booking.event_name);
        cell(row, `${booking.event_date} ${booking.event_time}`);
        cell(row, booking.quantity);
        cell(row, money(booking.total_amount));
        cell(row, booking.booking_status);

        const actions = cell(row, "");
        actions.className = "row-actions";

        if (booking.booking_status !== "cancelled") {
            const editButton = document.createElement("button");
            editButton.type = "button";
            editButton.textContent = "Edit quantity";
            editButton.addEventListener("click", () => startEdit(booking));

            const cancelButton = document.createElement("button");
            cancelButton.type = "button";
            cancelButton.textContent = "Cancel booking";
            cancelButton.className = "danger-button";
            cancelButton.addEventListener("click", () => cancelBooking(booking));

            actions.append(editButton, cancelButton);
            if (booking.booking_status === "confirmed") {
                const qrButton = document.createElement("button");
                qrButton.type = "button";
                qrButton.textContent = "View QR";

                qrButton.addEventListener("click", () => showBookingQr(booking));

                actions.appendChild(qrButton);
            }
        } else {
            actions.textContent = "Cancelled";
        }

        rows.appendChild(row);
    });
}

async function loadData() {
    document.getElementById("bookingQrPanel").hidden = true;
    loading = true;
    listMessage.textContent = "Loading events and bookings...";
    updateControls();

    try {
        const [eventData, bookingData] = await Promise.all([
            request("events.php"),
            request(`bookings.php?customer_id=${customerId}`)
        ]);

        if (!Array.isArray(eventData) || !Array.isArray(bookingData)) {
            throw new Error("Unexpected data received from the server.");
        }

        if (bookingData.some(
            booking => Number(booking.customer_id) !== customerId
        )) {
            throw new Error("The server returned bookings for another account.");
        }

        events = eventData;
        ready = true;
        fillEvents();
        renderBookings(bookingData);
        listMessage.textContent = `${bookingData.length} booking(s) found.`;
    } catch (error) {
        ready = false;
        rows.replaceChildren();
        listMessage.textContent = error.message;
    } finally {
        loading = false;
        updateControls();
    }
}

form.addEventListener("submit", async event => {
    event.preventDefault();
    if (busy || loading) return;

    const eventId = Number(eventSelect.value);
    const quantity = Number(quantityInput.value);

    if (
        !Number.isInteger(eventId) || eventId < 1 ||
        !Number.isInteger(quantity) || quantity < 1 ||
        quantity > 2147483647
    ) {
        showMessage("Select an event and enter a valid ticket quantity.", true);
        return;
    }

    busy = true;
    updateControls();
    showMessage("Saving booking...");

    try {
        if (editing) {
            await request(
                `bookings.php?id=${editing.booking_id}`,
                "PUT",
                {
                    quantity,
                    booking_status: editing.booking_status
                }
            );
            showMessage("Booking quantity updated successfully.");
        } else {
            await request("bookings.php", "POST", {
                customer_id: customerId,
                event_id: eventId,
                quantity
            });
            showMessage("Tickets booked successfully!");
        }

        resetForm();
        await loadData();
    } catch (error) {
        showMessage(error.message, true);
    } finally {
        busy = false;
        updateControls();
    }
});

async function cancelBooking(booking) {
    if (busy || loading) return;

    if (!confirm(`Cancel booking #${booking.booking_id}?`)) return;

    busy = true;
    updateControls();
    showMessage("Cancelling booking...");

    try {
        await request(
            `bookings.php?id=${booking.booking_id}`,
            "PUT",
            {
                quantity: Number(booking.quantity),
                booking_status: "cancelled"
            }
        );

        resetForm();
        showMessage("Booking cancelled successfully.");
        await loadData();
    } catch (error) {
        showMessage(error.message, true);
    } finally {
        busy = false;
        updateControls();
    }
}

eventSelect.addEventListener("change", updatePreview);
quantityInput.addEventListener("input", updatePreview);
cancelEditButton.addEventListener("click", resetForm);
refreshButton.addEventListener("click", loadData);

loadData();
async function showBookingQr(booking) {
    if (busy || loading) return;

    const panel = document.getElementById("bookingQrPanel");
    const image = document.getElementById("bookingQrImage");
    const reference = document.getElementById("bookingQrReference");

    busy = true;
    panel.hidden = true;
    image.removeAttribute("src");
    updateControls();
    showMessage("Generating booking QR...");

    try {
        const result = await request(
            `booking_qr.php?id=${booking.booking_id}`
        );

        if (
            !result ||
            typeof result.image !== "string" ||
            !result.image.startsWith("data:image/png;base64,")
        ) {
            throw new Error("Invalid QR image received.");
        }

        image.src = result.image;
        reference.textContent = result.reference;
        panel.hidden = false;
        showMessage("Booking QR generated successfully.");
        panel.scrollIntoView({ behavior: "smooth", block: "center" });
    } catch (error) {
        showMessage(error.message, true);
    } finally {
        busy = false;
        updateControls();
    }
}