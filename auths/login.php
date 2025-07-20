<?php

    include '../allowedOrigins.php';

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
    }

    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header("Access-Control-Max-Age: 86400");
        http_response_code(200);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["error" => "Invalid request method"]);
        exit;
    }

    require '../vendor/autoload.php';
    use Firebase\JWT\JWT;

    $secretKey = "i_am_mikhael_a_web_dev";

    $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode(["error" => "email and password are required"]);
        exit;
    }

    include '../db_connect.php';

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(["error" => "Email or Password is incorrect"]);
        exit;
    }

    // Block unverified users
    if (!$user['is_verified']) {
        http_response_code(403);
        echo json_encode(["error" => "Please verify your email before logging in."]);
        exit;
    }

    $payload = [
        "iss" => "http://localhost/sulat_tam/api/login.php",
        "iat" => time(),
        "exp" => time() + (86400 * 7),
        "user_id" => $user['user_id'],
        "username" => $user['username']
    ];

    $jwt = JWT::encode($payload, $secretKey, 'HS256');

    setcookie("token", $jwt, [
        'expires' => time() + (86400 * 7),
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'None'
    ]);

    echo json_encode([
        "user" => [
            "id" => $user['user_id'],
            "user_profile_url" => $user["user_profile_url"],
            "username" => $user['username'],
            "bio" => $user['bio'],
            "email" => $user['email']
        ],
        "message" => "User login successful, redirecting to homepage..."
    ]);

?>
