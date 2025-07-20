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
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid or expired token"]);
    exit;
}

// Decode JSON input
$data = json_decode(file_get_contents("php://input"), true);

$articleId = intval($data['article_id'] ?? 0);
$body = trim($data['body'] ?? '');

if (!$articleId || empty($body)) {
    http_response_code(400);
    echo json_encode(["error" => "Missing article ID or comment body"]);
    exit;
}

include '../db_connect.php';

// Insert the comment
$stmt = $conn->prepare("INSERT INTO comments (article_id, user_id, body) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $articleId, $userId, $body);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to post comment"]);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// Fetch updated comments
$fetchStmt = $conn->prepare("
    SELECT 
        c.comment_id, c.body, c.created_at,
        u.username, u.user_profile_url
    FROM comments c
    JOIN users u ON c.user_id = u.user_id
    WHERE c.article_id = ?
    ORDER BY c.created_at DESC
");
$fetchStmt->bind_param("i", $articleId);
$fetchStmt->execute();
$result = $fetchStmt->get_result();

$comments = [];
while ($row = $result->fetch_assoc()) {
    $comments[] = [
        "id" => (int)$row['comment_id'],
        "body" => $row['body'],
        "date" => $row['created_at'],
        "username" => $row['username'],
        "userProfileImageUrl" => $row['user_profile_url']
    ];
}

$fetchStmt->close();
$conn->close();

http_response_code(200);
echo json_encode(["comments" => $comments]);
?>
