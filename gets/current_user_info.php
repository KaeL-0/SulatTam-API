<?php

    include '../allowedOrigins.php';
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
    }

    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header("Access-Control-Max-Age: 86400");
        http_response_code(200);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(["error" => "Invalid request method"]);
        exit;
    }

    require '../vendor/autoload.php';
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;

    $secretKey = "i_am_mikhael_a_web_dev";

    if (!isset($_COOKIE['token'])) {
        http_response_code(401);
        echo json_encode(["error" => "Missing token"]);
        exit;
    }

    try {
        $decoded = JWT::decode($_COOKIE['token'], new Key($secretKey, 'HS256'));
        $userId = $decoded->user_id;

        include '../db_connect.php';

        $stmt = $conn->prepare("SELECT user_id, user_profile_url, username, bio, email FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            echo json_encode(["user" => $user]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "User not found"]);
        }

        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid or expired token"]);
    }

?>
