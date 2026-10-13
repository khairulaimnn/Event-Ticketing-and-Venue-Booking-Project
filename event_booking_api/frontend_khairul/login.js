const loginForm = document.getElementById("loginForm");
const loginBtn = document.getElementById("loginBtn");
const loginMessage = document.getElementById("loginMessage");
const passwordInput = document.getElementById("password");

document.getElementById("showPassword")
  .addEventListener("change", event => {
    passwordInput.type = event.target.checked ? "text" : "password";
  });

loginForm.addEventListener("submit", async event => {
  event.preventDefault();

  if (!loginForm.reportValidity()) return;

  loginBtn.disabled = true;
  loginBtn.textContent = "Signing in...";
  loginMessage.className = "";
  loginMessage.textContent = "";

  try {
    const response = await fetch("../api/login.php", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json"
      },
      body: JSON.stringify({
        email: document.getElementById("email").value.trim(),
        password: passwordInput.value
      }),
      signal: AbortSignal.timeout(15000)
    });

    let result;

    try {
      result = await response.json();
    } catch {
      throw new Error("The login API did not return valid JSON.");
    }

    if (!response.ok || result.success !== true) {
      throw new Error(result.message || "Login failed.");
    }

    const roleNames = {
      admin: "Administrator",
      organiser: "Organiser",
      customer: "Customer"
    };

    const role = result.data?.role;

    if (!Object.hasOwn(roleNames, role)) {
      throw new Error("The API returned an unknown user role.");
    }

    passwordInput.value = "";
    loginMessage.className = "success";
    loginMessage.textContent =
      `Login successful. Welcome, ${result.data.name}! ` +
      `Your role is ${roleNames[role]}.`;

    if (role === "admin") {
        window.location.assign("admin.php");
    } else if (role === "organiser") {
        window.location.assign("organiser.php");
    } else if (role === "customer") {
        window.location.assign("customer.php");
    }
  } catch (error) {
    loginMessage.className = "error";
    loginMessage.textContent =
      error.name === "TimeoutError"
        ? "The request timed out. Please try again."
        : error.message;
  } finally {
    loginBtn.disabled = false;
    loginBtn.textContent = "Sign in";
  }
});