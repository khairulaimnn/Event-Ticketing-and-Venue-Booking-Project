<?php
session_name("event_booking_session");
session_start();
header("Cache-Control: no-store");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    http_response_code(405);
    exit("Please use the Logout button.");
}

$token = $_POST["csrf_token"] ?? "";
$expected = $_SESSION["csrf_token"] ?? "";

if (
    !is_string($token)
    || $expected === ""
    || !hash_equals($expected, $token)
) {
    http_response_code(403);
    exit("Invalid request. Reload your dashboard and try again.");
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(session_name(), "", [
        "expires" => time() - 3600,
        "path" => $params["path"],
        "domain" => $params["domain"],
        "secure" => $params["secure"],
        "httponly" => $params["httponly"],
        "samesite" => $params["samesite"] ?? "Strict"
    ]);
}

session_destroy();

header("Location: login.html", true, 303);
exit;