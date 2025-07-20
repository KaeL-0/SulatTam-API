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

    include '../checkToken.php';
    include '../db_connect.php';

    $username = $_GET['username'];

    if(!$username){
        http_response_code(400);
        echo json_encode(["error" => "Missing username"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT user_id, user_profile_url, username, bio, email FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
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
    

?>
