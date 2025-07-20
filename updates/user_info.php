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

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$email) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required fields"]);
        exit;
    }

    if (!preg_match('/^[a-z0-9 ]+$/', strtolower($username))) {
        http_response_code(400);
        echo json_encode(["error" => "Username must be lowercase and alphanumeric only."]);
        exit;
    }

    function uploadToImgBB($filePath) {
        $apiKey = '86e4b824e34d60d47564105c44c514a5';
        $imageData = base64_encode(file_get_contents($filePath));

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.imgbb.com/1/upload?key=' . $apiKey,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'image' => $imageData
            ],
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            return null;
        }

        $result = json_decode($response, true);

        return $result['data']['url'] ?? null;
    }

    $imageUrl = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $_FILES['image']['tmp_name'];
        $uploadedUrl = uploadToImgBB($tmpPath);
        if ($uploadedUrl) {
            $imageUrl = $uploadedUrl;
        }
    }

    include '../db_connect.php';

    // Check for conflicts
    $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
    $checkStmt->bind_param("ssi", $username, $email, $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        http_response_code(409);
        echo json_encode(["error" => "Username or email already in use"]);
        $checkStmt->close();
        $conn->close();
        exit;
    }
    $checkStmt->close();

    // Build UPDATE
    $query = "UPDATE users SET username = ?, email = ?";
    $types = "ss";
    $params = [$username, $email];

    if (!empty($bio)) {
        $query .= ", bio = ?";
        $types .= "s";
        $params[] = $bio;
    }

    if ($imageUrl !== null) {
        $query .= ", user_profile_url = ?";
        $types .= "s";
        $params[] = $imageUrl;
    }

    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $query .= ", password = ?";
        $types .= "s";
        $params[] = $hashedPassword;
    }

    $query .= " WHERE user_id = ?";
    $types .= "i";
    $params[] = $userId;

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["error" => $stmt->error]);
        $stmt->close();
        $conn->close();
        exit;
    }

    $stmt->close();

    // Return updated user
    $fetchStmt = $conn->prepare("SELECT user_id, user_profile_url, username, bio, email FROM users WHERE user_id = ?");
    $fetchStmt->bind_param("i", $userId);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "User not found after update"]);
        $fetchStmt->close();
        $conn->close();
        exit;
    }

    $user = $result->fetch_assoc();
    $fetchStmt->close();
    $conn->close();

    http_response_code(200);
    echo json_encode([
        "user" => [
            "id" => $user['user_id'],
            "user_profile_url" => $user['user_profile_url'],
            "username" => $user['username'],
            "bio" => $user['bio'],
            "email" => $user['email']
        ],
        "message" => "User updated successfully, redirecting to profile..."
    ]);

?>
