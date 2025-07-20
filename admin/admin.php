<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // If not logged in, show login form
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        include '../db_connect.php';
        
        $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $password = trim($_POST['password'] ?? '');
        
        if ($email && $password) {
            // Check if user exists and is admin
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND is_admin = 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            $conn->close();
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user_id'] = $user['user_id'];
                $_SESSION['admin_username'] = $user['username'];
                header('Location: admin.php');
                exit();
            } else {
                $login_error = "Invalid credentials or insufficient permissions";
            }
        } else {
            $login_error = "Please enter both email and password";
        }
    }
    
    // Show login form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Admin Login</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter&family=Merriweather&family=Montserrat&display=swap" rel="stylesheet">
        <style>
            :root {
                --background-color: #FAFAFA;
                --primary-color: #00693E;
                --text-color: #1F2937;
                --link-color: #551A8B;
                --secondary-font: 'Merriweather', serif;
                --decorative-font: 'Montserrat', sans-serif;
            }

            body {
                font-family: 'Inter', sans-serif;
                background-color: var(--background-color);
                color: var(--text-color);
                margin: 0;
                padding: 0;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }

            .login-container {
                background: white;
                padding: 40px;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                width: 100%;
                max-width: 400px;
            }

            .login-header {
                text-align: center;
                margin-bottom: 30px;
                font-family: var(--decorative-font);
                color: var(--primary-color);
                font-size: 2rem;
                font-weight: bold;
            }

            .form-group {
                margin-bottom: 20px;
            }

            label {
                display: block;
                margin-bottom: 5px;
                font-weight: 500;
                color: var(--text-color);
            }

            input[type="email"],
            input[type="password"] {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 8px;
                font-size: 1rem;
                font-family: 'Inter', sans-serif;
                box-sizing: border-box;
            }

            input[type="email"]:focus,
            input[type="password"]:focus {
                outline: none;
                border-color: var(--primary-color);
                box-shadow: 0 0 0 2px rgba(0, 105, 62, 0.1);
            }

            .login-button {
                width: 100%;
                background-color: var(--primary-color);
                color: white;
                padding: 12px;
                border: none;
                border-radius: 8px;
                font-size: 1rem;
                font-family: var(--decorative-font);
                font-weight: 600;
                cursor: pointer;
                transition: background-color 0.2s ease;
            }

            .login-button:hover {
                background-color: #005932;
            }

            .error-message {
                background-color: #fee;
                color: #c33;
                padding: 10px;
                border-radius: 8px;
                margin-bottom: 20px;
                text-align: center;
                border: 1px solid #fcc;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h1 class="login-header">Admin Login</h1>
            
            <?php if (isset($login_error)): ?>
                <div class="error-message"><?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" name="login" class="login-button">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit();
}

include '../db_connect.php';

// Handle Approve or Reject Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pending_article_id = intval($_POST['pending_article_id'] ?? 0);

    if ($pending_article_id > 0) {
        if ($action === 'approve') {
            // Fetch pending article
            $stmt = $conn->prepare("SELECT * FROM pending_articles WHERE pending_article_id = ?");
            $stmt->bind_param("i", $pending_article_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $article = $result->fetch_assoc();
            $stmt->close();

            if ($article) {
                // Insert into articles
                $stmt = $conn->prepare("INSERT INTO articles (author_id, title, about, slug, content) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $article['author_id'], $article['title'], $article['about'], $article['slug'], $article['content']);
                $stmt->execute();
                $new_article_id = $stmt->insert_id;
                $stmt->close();

                // Move tags
                $tagStmt = $conn->prepare("SELECT tag_id FROM pending_article_tags WHERE pending_article_id = ?");
                $tagStmt->bind_param("i", $pending_article_id);
                $tagStmt->execute();
                $tagResult = $tagStmt->get_result();
                while ($tag = $tagResult->fetch_assoc()) {
                    $linkStmt = $conn->prepare("INSERT INTO article_tags (article_id, tag_id) VALUES (?, ?)");
                    $linkStmt->bind_param("ii", $new_article_id, $tag['tag_id']);
                    $linkStmt->execute();
                    $linkStmt->close();
                }
                $tagStmt->close();

                // Delete pending tags
                $stmt = $conn->prepare("DELETE FROM pending_article_tags WHERE pending_article_id = ?");
                $stmt->bind_param("i", $pending_article_id);
                $stmt->execute();
                $stmt->close();

                // Delete pending article
                $stmt = $conn->prepare("DELETE FROM pending_articles WHERE pending_article_id = ?");
                $stmt->bind_param("i", $pending_article_id);
                $stmt->execute();
                $stmt->close();
            }

        } elseif ($action === 'reject') {
            // Delete pending tags
            $stmt = $conn->prepare("DELETE FROM pending_article_tags WHERE pending_article_id = ?");
            $stmt->bind_param("i", $pending_article_id);
            $stmt->execute();
            $stmt->close();

            // Delete pending article
            $stmt = $conn->prepare("DELETE FROM pending_articles WHERE pending_article_id = ?");
            $stmt->bind_param("i", $pending_article_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    header('Location: admin.php');
    exit();
}

// Fetch All Pending Articles
$pendingArticles = $conn->query("
    SELECT pa.*, u.username 
    FROM pending_articles pa
    JOIN users u ON pa.author_id = u.user_id
    ORDER BY pa.created_at ASC
");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter&family=Merriweather&family=Montserrat&display=swap" rel="stylesheet">
    <style>
        :root {
            --background-color: #FAFAFA;
            --primary-color: #00693E;
            --text-color: #1F2937;
            --link-color: #551A8B;
            --secondary-font: 'Merriweather', serif;
            --decorative-font: 'Montserrat', sans-serif;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            margin: 0;
            padding: 0;
        }

        header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            font-family: var(--decorative-font);
            font-size: 1.5rem;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-link {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            transition: background-color 0.2s ease;
        }

        .logout-link:hover {
            background-color: rgba(255, 255, 255, 0.3);
            text-decoration: none;
        }

        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .article {
            background: white;
            border: 1px solid #ddd;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: box-shadow 0.2s ease;
        }

        .article:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .article h2 {
            font-family: var(--secondary-font);
            color: var(--primary-color);
            font-size: 1.5rem;
            margin-top: 0;
        }

        .article p {
            line-height: 1.5;
            font-size: 1rem;
        }

        strong {
            font-family: var(--decorative-font);
            font-weight: 600;
        }

        .buttons {
            margin-top: 20px;
        }

        button {
            font-family: var(--decorative-font);
            padding: 10px 20px;
            font-size: 0.9rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .approve {
            background-color: var(--primary-color);
            color: white;
        }

        .approve:hover {
            background-color: #005932;
        }

        .reject {
            background-color: #f44336;
            color: white;
        }

        .reject:hover {
            background-color: #d93025;
        }

        a {
            color: var(--link-color);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .no-articles {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>

<header>
    <div class="header-title">Admin Panel – Pending Articles</div>
    <div class="header-user">
        <span>Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
        <a href="admin.php?logout=1" class="logout-link">Logout</a>
    </div>
</header>

<div class="container">
    <?php if ($pendingArticles->num_rows > 0): ?>
        <?php while ($article = $pendingArticles->fetch_assoc()): ?>
            <div class="article">
                <h2><?= htmlspecialchars($article['title']) ?></h2>
                <p><strong>Author:</strong> <?= htmlspecialchars($article['username']) ?></p>
                <p><strong>Description:</strong> <?= htmlspecialchars($article['about']) ?></p>
                <p><?= nl2br(htmlspecialchars($article['content'])) ?></p>

                <form method="POST" class="buttons">
                    <input type="hidden" name="pending_article_id" value="<?= $article['pending_article_id'] ?>">
                    <button type="submit" name="action" value="approve" class="approve">Approve</button>
                    <button type="submit" name="action" value="reject" class="reject">Reject</button>
                </form>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="no-articles">No pending articles to review.</div>
    <?php endif; ?>
</div>

<?php $conn->close(); ?>

</body>
</html>