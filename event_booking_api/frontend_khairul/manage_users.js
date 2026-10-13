const form = document.getElementById("userForm");
const fields = document.getElementById("formFields");
const nameInput = document.getElementById("userName");
const emailInput = document.getElementById("userEmail");
const roleInput = document.getElementById("userRole");
const passwordInput = document.getElementById("userPassword");
const userMessage = document.getElementById("userMessage");
const listMessage = document.getElementById("listMessage");
const rows = document.getElementById("userRows");
const refreshButton = document.getElementById("refreshUsers");
const cancelButton = document.getElementById("cancelEdit");

const csrfToken = document.querySelector(
  'meta[name="csrf-token"]'
).content;

const currentUserId = Number(
  document.querySelector('meta[name="current-user-id"]').content
);

let editingId = null;
let busy = false;
let loading = false;

async function apiRequest(method = "GET", id = null, payload = null) {
  const url = "../api/users.php" +
    (id === null ? "" : `?id=${encodeURIComponent(id)}`);

  const headers = {
    Accept: "application/json"
  };

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

function showMessage(text, error = false) {
  userMessage.textContent = text;
  userMessage.className = error ? "error" : "success";
}

function errorText(error) {
  return error.name === "TimeoutError"
    ? "The request timed out. Refresh the user list to check the result."
    : error.message;
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
  passwordInput.required = true;

  document.getElementById("formTitle").textContent = "Create user";
  document.getElementById("saveUser").textContent = "Create user";
  document.getElementById("passwordHint").textContent =
    "Use at least 6 characters.";

  cancelButton.hidden = true;
}

function editUser(user) {
  if (busy || loading) return;

  editingId = user.user_id;
  nameInput.value = user.name;
  emailInput.value = user.email;
  roleInput.value = user.role;
  passwordInput.value = "";
  passwordInput.required = false;

  document.getElementById("formTitle").textContent = "Edit user";
  document.getElementById("saveUser").textContent = "Save changes";
  document.getElementById("passwordHint").textContent =
    "Leave the password blank to keep the existing password.";

  cancelButton.hidden = false;
  userMessage.textContent = "";
  nameInput.focus();
  form.scrollIntoView({ behavior: "smooth", block: "center" });
}

function renderUsers(users) {
  rows.replaceChildren();

  if (users.length === 0) {
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.colSpan = 5;
    cell.textContent = "No users found.";
    row.appendChild(cell);
    rows.appendChild(row);
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

    const actions = document.createElement("td");

    if (Number(user.user_id) === currentUserId) {
      actions.textContent = "Current account";
    } else {
      const editButton = document.createElement("button");
      editButton.type = "button";
      editButton.textContent = "Edit";
      editButton.addEventListener("click", () => editUser(user));

      const deleteButton = document.createElement("button");
      deleteButton.type = "button";
      deleteButton.textContent = "Delete";
      deleteButton.className = "danger-button";
      deleteButton.addEventListener("click", () => deleteUser(user));

      actions.className = "row-actions";
      actions.append(editButton, deleteButton);
    }

    row.appendChild(actions);
    rows.appendChild(row);
  });
}

async function loadUsers() {
  loading = true;
  updateControls();
  listMessage.className = "";
  listMessage.textContent = "Loading users...";
  rows.replaceChildren();

  try {
    const result = await apiRequest();

    if (!Array.isArray(result.data)) {
      throw new Error("Unexpected users response format.");
    }

    renderUsers(result.data);
    listMessage.textContent = `${result.data.length} user(s) found.`;
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

  if (nameInput.value.trim() === "") {
    showMessage("Enter a full name.", true);
    nameInput.focus();
    return;
  }

  const payload = {
    name: nameInput.value.trim(),
    email: emailInput.value.trim(),
    role: roleInput.value
  };

  if (editingId === null || passwordInput.value !== "") {
    payload.password = passwordInput.value;
  }

  busy = true;
  updateControls();
  userMessage.className = "";
  userMessage.textContent = "Saving user...";

  try {
    const result = await apiRequest(
      editingId === null ? "POST" : "PUT",
      editingId,
      payload
    );

    resetForm();
    showMessage(result.message || "User saved successfully.");
    await loadUsers();
  } catch (error) {
    showMessage(errorText(error), true);
  } finally {
    busy = false;
    updateControls();
  }
});

async function deleteUser(user) {
  if (busy || loading) return;

  if (!window.confirm(`Delete the account for ${user.name}?`)) {
    return;
  }

  busy = true;
  updateControls();
  userMessage.className = "";
  userMessage.textContent = "Deleting user...";

  try {
    const result = await apiRequest("DELETE", user.user_id);

    if (Number(editingId) === Number(user.user_id)) {
      resetForm();
    }

    showMessage(result.message || "User deleted successfully.");
    await loadUsers();
  } catch (error) {
    showMessage(errorText(error), true);
  } finally {
    busy = false;
    updateControls();
  }
}

cancelButton.addEventListener("click", () => {
  resetForm();
  userMessage.textContent = "";
});

refreshButton.addEventListener("click", loadUsers);

loadUsers();