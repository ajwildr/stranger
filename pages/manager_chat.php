<?php
session_start();
require '../includes/db_connect.php';

// Ensure only managers can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Manager') {
    // header("Location: error.php");
    // exit;
    echo "<script>window.location.href = 'login.php';</script>";
}

// Initialize variables
$discussion_id = null;
$chats = [];
$error_message = '';

// Get the discussion_id from the URL
if (isset($_GET['discussion_id'])) {
    $discussion_id = filter_var($_GET['discussion_id'], FILTER_VALIDATE_INT);
    
    if ($discussion_id === false || $discussion_id === null) {
        // header("Location: error.php");
        // exit;
        echo "<script>window.location.href = 'error.php';</script>";
    }

    // Fetch all chat messages for the discussion_id along with the username
    try {
        $query = "SELECT chat_id, username, chat_msg, time 
                 FROM chat 
                 WHERE discussion_id = ? 
                 ORDER BY time ASC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $discussion_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $chats[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Error loading chat messages.";
        error_log("Database error: " . $e->getMessage());
    }
}

// Handle new chat message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_msg']) && $discussion_id) {
    $chat_msg = trim($_POST['chat_msg']);
    $username = $_SESSION['username'];

    if (!empty($chat_msg)) {
        try {
            $query = "INSERT INTO chat (discussion_id, chat_msg, username, time) 
                     VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iss", $discussion_id, $chat_msg, $username);
            
            if ($stmt->execute()) {
                // Redirect to avoid form resubmission
                // header("Location: manager_chat.php?discussion_id=" . $discussion_id);
                // exit;
                echo "<script>window.location.href = 'manager_chat.php?discussion_id=" . $discussion_id . "';</script>";
                exit;
            } else {
                $error_message = "Failed to send message.";
            }
            $stmt->close();
        } catch (Exception $e) {
            $error_message = "Error sending message.";
            error_log("Database error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Discussion Chat</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f0f0;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .chat-box {
            height: 400px;
            overflow-y: auto;
            padding: 15px;
            margin-bottom: 20px;
            background-color: transparent;
            max-height: 500px;
            border-bottom: 1px solid #ccc;
        }

        .chat-message {
            padding: 12px;
            margin-bottom: 12px;
            border-radius: 8px;
            background-color: rgba(0, 0, 0, 0.05);
            width: fit-content;
            max-width: 80%;
            word-wrap: break-word;
            font-size: 1.1em;
            position: relative;
        }

        .chat-message.current-user {
            background-color: rgba(0, 123, 255, 0.7);
            color: white;
            margin-left: auto;
        }

        .chat-message.other-user {
            background-color: rgba(248, 249, 250, 0.8);
            color: #333;
        }

        .chat-message span {
            font-size: 0.85em;
            color: rgba(0, 0, 0, 0.6);
            position: absolute;
            bottom: 5px;
            right: 10px;
        }

        .chat-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .chat-form textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid rgba(0, 0, 0, 0.2);
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            font-size: 1em;
            resize: none;
            height: 100px;
        }

        .chat-form button {
            padding: 12px 20px;
            background-color: rgba(0, 123, 255, 0.8);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.3s ease;
        }

        .chat-form button:hover {
            background-color: rgba(0, 123, 255, 1);
        }

        .error-message {
            color: #dc3545;
            padding: 10px;
            margin: 10px 0;
            background-color: rgba(220, 53, 69, 0.1);
            border-radius: 5px;
        }

        .chat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .back-link {
            text-decoration: none;
            color: #007bff;
            font-size: 1.1em;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="chat-header">
            <h1>Manager Discussion Chat</h1>
            <a href="manager_discussion.php" class="back-link">Back to Discussions</a>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="chat-box">
            <?php foreach ($chats as $chat): ?>
                <div class="chat-message <?= ($chat['username'] == $_SESSION['username']) ? 'current-user' : 'other-user' ?>">
                    <p><strong><?= htmlspecialchars($chat['username']) ?>:</strong> 
                       <?= nl2br(htmlspecialchars($chat['chat_msg'])) ?></p>
                    <span>Sent at <?= htmlspecialchars($chat['time']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <form class="chat-form" method="POST" action="manager_chat.php?discussion_id=<?= urlencode($discussion_id) ?>">
            <textarea name="chat_msg" placeholder="Type your message..." required></textarea>
            <button type="submit">Send Message</button>
        </form>
    </div>
</body>
</html>