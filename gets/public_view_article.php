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

    if (!isset($_COOKIE['token'])) {
        http_response_code(401);
        echo json_encode(["error" => "Missing token"]);
        exit;
    }

    include '../checkToken.php';
    include '../db_connect.php';

    $username = $_GET['username'] ?? '';
    $slug = $_GET['slug'] ?? '';

    if (empty($username) || empty($slug)) {
        http_response_code(400);
        echo json_encode(["error" => "Username and slug are required"]);
        exit;
    }

    // Fetch article and author
    $sql = "
        SELECT 
            a.article_id,
            a.title,
            a.about,
            a.content,
            a.slug,
            a.created_at AS post_date,
            u.user_id AS author_id,
            u.username,
            u.user_profile_url
        FROM articles a
        JOIN users u ON a.author_id = u.user_id
        LEFT JOIN follows fo ON u.user_id = fo.followee_id
        WHERE u.username = ? AND a.slug = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $username, $slug);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "Article not found"]);
        exit;
    }

    $article = $result->fetch_assoc();

    $tagSql = "
        SELECT t.name
        FROM article_tags at
        JOIN tags t ON at.tag_id = t.tag_id
        JOIN articles a ON at.article_id = a.article_id
        JOIN users u ON a.author_id = u.user_id
        WHERE u.username = ? AND a.slug = ?
    ";

    $tagStmt = $conn->prepare($tagSql);
    $tagStmt->bind_param('ss', $username, $slug);
    $tagStmt->execute();
    $tagResult = $tagStmt->get_result();

    $tags = [];
    while ($tagRow = $tagResult->fetch_assoc()) {
        $tags[] = $tagRow['name'];
    }

    $commentSql = "
        SELECT c.comment_id, c.body, c.created_at, u.username, u.user_profile_url
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.article_id = ?
        ORDER BY c.created_at DESC
    ";

    $commentStmt = $conn->prepare($commentSql);
    $commentStmt->bind_param('i', $article["article_id"]);
    $commentStmt->execute();
    $commentResult = $commentStmt->get_result();

    $comments = [];
    while ($row = $commentResult->fetch_assoc()) {
        $comments[] = [
            "id" => (int)$row["comment_id"],
            "body" => $row["body"],
            "date" => $row["created_at"],
            "username" => $row["username"],
            "userProfileImageUrl" => $row["user_profile_url"]
        ];
    }

    $response = [
        "article" => [
            "id" => $article["article_id"],
            "title" => $article["title"],
            "about" => $article["about"],
            "content" => $article["content"],
            "slug" => $article["slug"],
            "postDate" => $article["post_date"],
            "tags" => $tags
        ],
        "author" => [
            "id" => (int)$article["author_id"],
            "username" => $article["username"],
            "userProfileImageUrl" => $article["user_profile_url"],
        ],
        "comments" => $comments
    ];

    echo json_encode($response);
    $conn->close();

?>
