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
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid or expired token"]);
        exit;
    }

    include '../db_connect.php';

    $limit = $_GET['limit'] ?? 8;
    $page = $_GET['page'] ?? 1;
    $offset = ($page - 1) * $limit;

    $sql = "
        SELECT 
            a.article_id,
            u.username,
            u.user_profile_url,
            a.created_at AS post_date,
            a.title,
            a.about,
            a.slug,
            COUNT(f2.user_id) AS favorites
        FROM favorites f
        JOIN articles a ON f.article_id = a.article_id
        JOIN users u ON a.author_id = u.user_id
        LEFT JOIN favorites f2 ON a.article_id = f2.article_id
        WHERE f.user_id = ?
        GROUP BY a.article_id
        ORDER BY a.created_at DESC
        LIMIT ?
        OFFSET ?
    ";

    $checkStmt = $conn->prepare($sql);
    $checkStmt->bind_param('iii', $userId, $limit, $offset);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if (!$result) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to fetch articles"]);
        exit;
    }

    $articles = [];

    while ($row = $result->fetch_assoc()) {
        $articles[] = [
            "id" => $row["article_id"],
            "username" => $row["username"],
            "userProfileImageUrl" => $row["user_profile_url"],
            "postDate" => $row["post_date"],
            "title" => $row["title"],
            "about" => $row["about"],
            "favorites" => (int)$row["favorites"],
            "slug" => $row["slug"]
        ];
    }

    echo json_encode(["articles" => $articles]);

    $conn->close();

?>
