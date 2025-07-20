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

    $limit = $_GET['limit'] ?? 10;
    $page = $_GET['page'] ?? 1;
    $offset = ($page - 1) * 8;

    $username = $_GET['username'];

    if(!$username){
        http_response_code(400);
        echo json_encode(["error" => "Missing username"]);
        exit;
    }

    $sql = "
        SELECT 
            a.article_id,
            u.username,
            u.user_profile_url,
            a.created_at AS post_date,
            a.title,
            a.about,
            a.slug,
            COUNT(f.user_id) AS favorites
        FROM articles a
        JOIN users u ON a.author_id = u.user_id
        LEFT JOIN favorites f ON a.article_id = f.article_id
        WHERE u.username = ?
        GROUP BY a.article_id
        ORDER BY a.created_at DESC
        LIMIT ?
        OFFSET ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to prepare statement"]);
        exit;
    }

    $stmt->bind_param("sii", $username, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

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

    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM articles
        INNER JOIN users ON articles.author_id = users.user_id
        WHERE users.username = ?
    ");

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to prepare statement"]);
        exit;
    }

    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to fetch articles count"]);
        exit;
    }

    $row = $result->fetch_assoc();
    $count = $row['count'];

    echo json_encode([
        "articles" => $articles,
        "count" => $count
    ]);

    $stmt->close();
    $conn->close();

?>