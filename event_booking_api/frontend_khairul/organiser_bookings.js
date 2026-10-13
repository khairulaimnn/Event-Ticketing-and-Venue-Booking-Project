"use strict";

(() => {
    const statusSelect = document.getElementById("organiserBookingStatus");
    const refreshButton = document.getElementById("refreshOrganiserBookings");
    const message = document.getElementById("organiserBookingMessage");
    const rows = document.getElementById("organiserBookingRows");

    let loading = false;

    function addCell(row, value) {
        const cell = document.createElement("td");
        cell.textContent = String(value);
        row.appendChild(cell);
        return cell;
    }

    async function loadBookings() {
        if (loading) return;

        loading = true;
        statusSelect.disabled = true;
        refreshButton.disabled = true;
        rows.replaceChildren();
        message.className = "";
        message.textContent = "Loading bookings...";

        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 15000);

        try {
            const params = new URLSearchParams();

            if (statusSelect.value) {
                params.set("status", statusSelect.value);
            }

            const query = params.toString();
            const url = "../api/bookings.php" + (query ? `?${query}` : "");

            const response = await fetch(url, {
                credentials: "same-origin",
                headers: { Accept: "application/json" },
                signal: controller.signal
            });

            let result;

            try {
                result = await response.json();
            } catch {
                throw new Error("The server returned an invalid response.");
            }

            if (!response.ok || result.success !== true) {
                throw new Error(result.message || "Unable to load bookings.");
            }

            if (!Array.isArray(result.data)) {
                throw new Error("Unexpected booking data received.");
            }

            if (result.data.length === 0) {
                const row = document.createElement("tr");
                addCell(row, "No bookings match this filter.").colSpan = 6;
                rows.appendChild(row);
            } else {
                result.data.forEach(booking => {
                    const row = document.createElement("tr");

                    addCell(row, booking.booking_id);
                    addCell(row, booking.event_name);
                    addCell(row, booking.customer_name);
                    addCell(row, booking.quantity);
                    addCell(
                        row,
                        `RM ${Number(booking.total_amount).toFixed(2)}`
                    );
                    addCell(row, booking.booking_status);

                    rows.appendChild(row);
                });
            }

            message.textContent =
                `${result.data.length} booking(s) found for your events.`;
        } catch (error) {
            message.className = "error";
            message.textContent = error.name === "AbortError"
                ? "The request timed out. Please try again."
                : error.message;
        } finally {
            clearTimeout(timer);
            loading = false;
            statusSelect.disabled = false;
            refreshButton.disabled = false;
        }
    }

    statusSelect.addEventListener("change", loadBookings);
    refreshButton.addEventListener("click", loadBookings);

    loadBookings();
})();