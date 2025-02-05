<?php
session_start();
require '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Manager') {
    // header("Location: login.php");
    // exit();
    echo "<script>window.location.href = 'error.php';</script>";
}

// Fetch extension requests that are pending and less than 2 days old
$extensionQuery = "SELECT pe.*, p.title as project_title, u.username 
    FROM project_extensions pe 
    JOIN projects p ON pe.project_id = p.project_id 
    JOIN users u ON pe.requested_by = u.user_id 
    WHERE pe.status = 'pending' 
    AND pe.requested_date >= DATE_SUB(CURRENT_DATE, INTERVAL 2 DAY)";
$extensionResult = $conn->query($extensionQuery);

// Fetch meetings requiring manager attention
$meetingQuery = "SELECT * FROM meetings 
    WHERE manager_required = 1 
    AND (status = 'scheduled' OR status = 'in_progress')";
$meetingResult = $conn->query($meetingQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #34495e;
            --background: #f8f9fa;
            --accent: #3498db;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            min-height: 100vh;
        }

        .navbar {
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .notification-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
            position: relative;
        }

        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .btn-clear-all {
            background-color: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .btn-clear-all:hover {
            background-color: var(--primary);
            color: white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }

        .btn-clear-all:active {
            transform: translateY(1px);
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .section-header {
            color: var(--primary);
            font-weight: 600;
            margin: 2rem 0 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }

        .notification {
            background: #ffffff;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            position: relative;
            transition: all 0.3s ease;
        }

        .notification:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .notification.expired {
            opacity: 0.6;
        }

        .notification h3 {
            color: var(--primary);
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }

        .notification p {
            color: #666;
            margin-bottom: 0.5rem;
        }

        .notification .close {
            position: absolute;
            right: 1rem;
            top: 1rem;
            background: none;
            border: none;
            color: #999;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
        }

        .notification .close:hover {
            color: #dc3545;
        }

        .notification-date {
            font-size: 0.85rem;
            color: #888;
            margin-top: 1rem;
            border-top: 1px solid #eee;
            padding-top: 0.5rem;
        }

        .btn-back {
            color: var(--primary);
            border: 2px solid var(--primary);
            background: transparent;
            padding: 0.5rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: var(--primary);
            color: white;
        }

        .no-notifications {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            color: #666;
        }

        .timer {
            color: var(--accent);
            font-weight: 500;
        }

        .action-btn {
            background-color: var(--accent);
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            display: inline-block;
            margin-top: 1rem;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background-color: #2980b9;
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container d-flex justify-content-between align-items-center">
            <h1 class="h4 mb-0">Notifications</h1>
            <a href="manager_dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </nav>

    <div class="notification-container">
        <div class="header-actions">
            <button class="btn-clear-all">
                <i class="fas fa-trash-alt"></i>Clear All Notifications
            </button>
        </div>

        <h2 class="section-header">
            <i class="fas fa-clock me-2"></i>Project Extension Requests
        </h2>
        <?php 
        if ($extensionResult->num_rows > 0):
            while ($extension = $extensionResult->fetch_assoc()): 
                $requestDate = new DateTime($extension['requested_date']);
                $now = new DateTime();
                $diff = $requestDate->diff($now)->days;
        ?>
            <div class="notification <?php echo ($diff >= 2) ? 'expired' : ''; ?>" 
                 id="extension-<?php echo $extension['extension_id']; ?>"
                 data-date="<?php echo $extension['requested_date']; ?>">
                <button class="close" onclick="removeNotification('extension-<?php echo $extension['extension_id']; ?>')">×</button>
                <h3><?php echo htmlspecialchars($extension['project_title']); ?></h3>
                <p><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($extension['username']); ?></p>
                <p><i class="fas fa-calendar me-2"></i>New Due Date: <?php echo $extension['new_due_date']; ?></p>
                <p><i class="fas fa-comment me-2"></i>Reason: <?php echo htmlspecialchars($extension['reason']); ?></p>
                <a href="manage_projects.php" class="action-btn">View Request</a>
                <div class="notification-date">
                    <i class="fas fa-clock me-2"></i>Requested: <?php echo $extension['requested_date']; ?>
                </div>
            </div>
        <?php 
            endwhile; 
        else:
        ?>
            <div class="no-notifications">
                <i class="fas fa-check-circle fs-4 mb-3 text-success"></i>
                <p>No pending extension requests</p>
            </div>
        <?php 
        endif; 
        ?>

        <h2 class="section-header">
            <i class="fas fa-calendar me-2"></i>Meetings Requiring Manager
        </h2>
        <?php 
        if ($meetingResult->num_rows > 0):
            while ($meeting = $meetingResult->fetch_assoc()): 
        ?>
            <div class="notification" id="meeting-<?php echo $meeting['meeting_id']; ?>">
                <button class="close" onclick="removeNotification('meeting-<?php echo $meeting['meeting_id']; ?>')">×</button>
                <h3><?php echo htmlspecialchars($meeting['title']); ?></h3>
                <p><i class="fas fa-clock me-2"></i>Scheduled Time: <?php echo $meeting['scheduled_time']; ?></p>
                <p><i class="fas fa-info-circle me-2"></i>Status: <?php echo ucfirst($meeting['status']); ?></p>
                <?php if ($meeting['status'] === 'in_progress'): ?>
                    <a href="<?php echo $meeting['meet_link']; ?>" class="action-btn" target="_blank">
                        <i class="fas fa-video me-2"></i>Join Now
                    </a>
                <?php else: ?>
                    <p class="timer" data-time="<?php echo $meeting['scheduled_time']; ?>">
                        <i class="fas fa-hourglass-half me-2"></i>Calculating...
                    </p>
                <?php endif; ?>
            </div>
        <?php 
            endwhile;
        else:
        ?>
            <div class="no-notifications">
                <i class="fas fa-check-circle fs-4 mb-3 text-success"></i>
                <p>No upcoming meetings requiring attention</p>
            </div>
        <?php 
        endif; 
        ?>
    </div>

    <script>
        function removeNotification(id) {
            const element = document.getElementById(id);
            element.style.opacity = '0';
            setTimeout(() => {
                element.style.display = 'none';
            }, 300);
        }

        function clearAllNotifications() {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 300);
            });
        }

        function updateTimers() {
            const timers = document.querySelectorAll('.timer');
            timers.forEach(timer => {
                const scheduledTime = new Date(timer.dataset.time);
                const now = new Date();
                const timeLeft = scheduledTime - now;

                if (timeLeft > 0) {
                    const hours = Math.floor(timeLeft / (1000 * 60 * 60));
                    const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                    timer.innerHTML = `<i class="fas fa-hourglass-half me-2"></i>Time remaining: ${hours}h ${minutes}m`;
                } else {
                    timer.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Meeting should have started';
                }
            });
        }

        function checkExpiredNotifications() {
            const extensionNotifications = document.querySelectorAll('[id^="extension-"]');
            extensionNotifications.forEach(notification => {
                const requestDate = new Date(notification.dataset.date);
                const now = new Date();
                const diffDays = Math.floor((now - requestDate) / (1000 * 60 * 60 * 24));
                
                if (diffDays >= 2) {
                    notification.classList.add('expired');
                    setTimeout(() => {
                        notification.style.opacity = '0';
                        setTimeout(() => {
                            notification.style.display = 'none';
                        }, 300);
                    }, 1000);
                }
            });
        }

        // Add event listener for the clear all button
        document.querySelector('.btn-clear-all').addEventListener('click', clearAllNotifications);

        setInterval(updateTimers, 60000);
        updateTimers();

        setInterval(checkExpiredNotifications, 3600000);
        checkExpiredNotifications();
    </script>
</body>
</html>