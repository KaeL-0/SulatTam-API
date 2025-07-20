<?php

    include '../allowedOrigins.php';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
    }
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");

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
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    if (!isset($_POST['username'], $_POST['email'], $_POST['password'])) {
        http_response_code(400);
        echo json_encode(["error" => "username, email, and password are required"]);
        exit;
    }

    $username = trim(htmlspecialchars($_POST['username']));
    $email = $_POST['email'];
    $password = trim($_POST['password']);

    if (!$username || !$email || !$password) {
        http_response_code(400);
        echo json_encode(["error" => "Missing credentials"]);
        exit;
    }

    if (!preg_match('/^[a-z0-9 ]+$/', $username)) {
        http_response_code(400);
        echo json_encode(["error" => "Username must be lowercase and alphanumeric only."]);
        exit;
    }

    if (!str_ends_with($email, '@fit.edu.ph')) {
        http_response_code(400);
        echo json_encode(["error" => "Only @fit.edu.ph emails are allowed"]);
        exit;
    }

    //Password Strength Check
    if (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/\d/', $password) ||
        !preg_match('/[^a-zA-Z\d]/', $password)
    ) {
        http_response_code(400);
        echo json_encode([
            "error" => "Password must be 8+ characters with upper/lower, number, symbol."
        ]);
        exit;
    }

    include '../db_connect.php';

    // Check for existing users
    $checkStmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $checkStmt->bind_param("ss", $username, $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    $shouldDelete = false;
    $conflictMessage = "";

    while ($existingUser = $checkResult->fetch_assoc()) {
        // If verified user exists with same username or email, reject
        if ($existingUser['is_verified'] == 1) {
            if ($existingUser['username'] === $username) {
                $conflictMessage = "Username already exists and is verified";
            } else {
                $conflictMessage = "Email already exists and is verified";
            }
            http_response_code(409);
            echo json_encode(["error" => $conflictMessage]);
            $checkStmt->close();
            $conn->close();
            exit;
        }
        
        // If unverified user exists, mark for deletion
        if ($existingUser['is_verified'] == 0) {
            $shouldDelete = true;
        }
    }
    $checkStmt->close();

    // Delete unverified users with same username or email
    if ($shouldDelete) {
        $deleteStmt = $conn->prepare("DELETE FROM users WHERE (username = ? OR email = ?) AND is_verified = 0");
        $deleteStmt->bind_param("ss", $username, $email);
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $verificationToken = bin2hex(random_bytes(32));

    // Insert User as Unverified
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, verification_token) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $email, $hashedPassword, $verificationToken);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["error" => $stmt->error]);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();
    $conn->close();

    $verificationLink = "https://sulat-tam.alwaysdata.net/auths/verify.php?token=$verificationToken";

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp-relay.brevo.com';
        $mail->SMTPAuth = true;
        $mail->Username = '9290e6001@smtp-brevo.com';
        $mail->Password = 'xsmtpsib-36e5314ea5ccece8d7a6de374866d116f681ee7a7782bc5004c012e848d1fa95-mMsrPcv4CzD5AFH8';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('sulat.tamaraw@gmail.com', 'SulatTam Website');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Verify Your SulatTam Account';
        $mail->Body    = "
            <h2>Welcome to SulatTam!</h2>
            <p>Please click the link below to verify your email address:</p>
            <p><a href='$verificationLink'>Verify My Email</a></p>
            <p>If you didn't create this account, please ignore this email.</p>
        ";

        $mail->send();

        http_response_code(201);
        echo json_encode(["message" => "Please check your outlook inbox to verify your account."]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Email could not be sent. Mailer Error: {$mail->ErrorInfo}"]);
    }

?>