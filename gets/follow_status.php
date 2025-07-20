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

    require '../vendor/autoload.php';
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;

    $secretKey = "i_am_mikhael_a_web_dev";

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(["error" => "Invalid request method"]);
        exit;
    }

    if (!isset($_COOKIE['token'])) {
        http_response_code(401);
        echo json_encode(["error" => "Missing token"]);
        exit;
    }

    try {
        $decoded = JWT::decode($_COOKIE['token'], new Key($secretKey, 'HS256'));
        $followerId = $decoded->user_id;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid or expired token"]);
        exit;
    }

    $followeeId = $_GET['followee_id'] ?? null;

    if (!$followeeId || !is_numeric($followeeId) || $followeeId == $followerId) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid followee_id"]);
        exit;
    }

    include '../db_connect.php';

    // Check if already following
    $stmt = $conn->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followee_id = ?");
    $stmt->bind_param("ii", $followerId, $followeeId);
    $stmt->execute();
    $isFollowing = $stmt->get_result()->fetch_assoc() ? true : false;
    $stmt->close();

    // Count followers
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM follows WHERE followee_id = ?");
    $stmt->bind_param("i", $followeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $conn->close();

    echo json_encode([
        "following" => $isFollowing,
        "followersCount" => (int)$row['count']
    ]);

?>
