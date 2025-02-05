<?php
session_start();
require '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'TeamMember') {
    // header("Location: login.php");
    echo "<script>window.location.href = 'error.php';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];

// Fetch unviewed tasks
$taskQuery = "SELECT t.*, u.username as assigned_by_name 
    FROM tasks t 
    JOIN users u ON t.assigned_by = u.user_id 
    WHERE t.assigned_to = ? AND t.status = 'not_viewed'
    ORDER BY t.created_at DESC";
$stmt = $conn->prepare($taskQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$taskResult = $stmt->get_result();

// Fetch upcoming meetings
$meetingQuery = "SELECT * FROM meetings 
    WHERE team_id = ? 
    AND (status = 'scheduled' OR status = 'in_progress')
    ORDER BY scheduled_time ASC";
$stmt = $conn->prepare($meetingQuery);
$stmt->bind_param("i", $team_id);
$stmt->execute();
$meetingResult = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Member Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #34495e;
            --background: #f8f9fa;
            --accent: #3498db;
            --success: #2ecc71;
            --warning: #f1c40f;
            --danger: #e74c3c;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            min-height: 100vh;
        }

        .navbar {
            background-color: var(--primary);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem;
        }

        .notification-card {
            background: #ffffff;
            border-radius: 10px;
            border: none;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .notification-header {
            background-color: #ffffff;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .notification-title {
            color: var(--primary);
            font-weight: 600;
            margin: 0;
        }

        .notification-subtitle {
            color: var(--secondary);
            margin-top: 0.5rem;
        }

        .timer {
            color: var(--accent);
            font-weight: 500;
        }

        .meeting-status {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-scheduled {
            background-color: var(--warning);
            color: #000;
        }

        .status-in-progress {
            background-color: var(--success);
            color: #fff;
        }

        .clear-all-btn {
            background-color: var(--danger);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .clear-all-btn:hover {
            background-color: #c0392b;
            transform: translateY(-1px);
        }

        .close-btn {
            background: none;
            border: none;
            color: var(--secondary);
            font-size: 1.25rem;
            opacity: 0.5;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            opacity: 1;
            color: var(--danger);
        }

        .back-btn {
            background-color: var(--accent);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background-color: #2980b9;
            color: white;
            transform: translateY(-1px);
        }

        .section-title {
            color: var(--primary);
            font-weight: 600;
            margin: 2rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--accent);
        }

        .points-badge {
            background-color: var(--accent);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.875rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark fixed-top">
        <div class="container d-flex justify-content-between align-items-center">
            <h1 class="navbar-brand mb-0">
                <i class="fas fa-bell me-2"></i>
                Notifications
            </h1>
            <a href="teammember_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left me-2"></i>
                Back to Dashboard
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container" style="margin-top: 100px;">
        <!-- Notification Header -->
        <div class="notification-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="notification-title">Your Notifications</h1>
                    <p class="notification-subtitle mb-0">Stay updated with your tasks and meetings</p>
                </div>
                <button class="clear-all-btn" onclick="clearAllNotifications()">
                    <i class="fas fa-trash-alt me-2"></i>
                    Clear All
                </button>
            </div>
        </div>

        <!-- Tasks Section -->
        <h2 class="section-title">
            <i class="fas fa-tasks me-2"></i>
            New Tasks Assigned
        </h2>
        
        <?php while ($task = $taskResult->fetch_assoc()): ?>
        <div class="notification-card card" id="task-<?php echo $task['task_id']; ?>">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h3 class="h5 mb-3"><?php echo htmlspecialchars($task['task_title']); ?></h3>
                        <span class="points-badge">
                            <i class="fas fa-star me-1"></i>
                            <?php echo $task['points']; ?> Points
                        </span>
                    </div>
                    <button class="close-btn" onclick="removeNotification('task-<?php echo $task['task_id']; ?>')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="mt-3">
                    <p class="mb-2">
                        <i class="fas fa-user me-2"></i>
                        Assigned by: <?php echo htmlspecialchars($task['assigned_by_name']); ?>
                    </p>
                    <p class="mb-2">
                        <i class="fas fa-align-left me-2"></i>
                        <?php echo htmlspecialchars($task['task_description']); ?>
                    </p>
                    <?php if ($task['due_date']): ?>
                    <p class="mb-3">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Due: <?php echo $task['due_date']; ?>
                    </p>
                    <?php endif; ?>
                    <a href="t_member_mytasks.php" class="btn btn-primary">
                        <i class="fas fa-eye me-2"></i>
                        View Task Details
                    </a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>

        <!-- Meetings Section -->
        <h2 class="section-title">
            <i class="fas fa-calendar-check me-2"></i>
            Team Meetings
        </h2>

        <?php while ($meeting = $meetingResult->fetch_assoc()): ?>
        <div class="notification-card card" id="meeting-<?php echo $meeting['meeting_id']; ?>">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h3 class="h5 mb-3"><?php echo htmlspecialchars($meeting['title']); ?></h3>
                        <span class="meeting-status status-<?php echo $meeting['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $meeting['status'])); ?>
                        </span>
                    </div>
                    <button class="close-btn" onclick="removeNotification('meeting-<?php echo $meeting['meeting_id']; ?>')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="mt-3">
                    <p class="mb-2">
                        <i class="fas fa-align-left me-2"></i>
                        <?php echo htmlspecialchars($meeting['description']); ?>
                    </p>
                    <p class="mb-2">
                        <i class="fas fa-clock me-2"></i>
                        <?php echo $meeting['scheduled_time']; ?>
                    </p>
                    <?php if ($meeting['status'] === 'in_progress'): ?>
                    <a href="<?php echo $meeting['meet_link']; ?>" class="btn btn-success" target="_blank">
                        <i class="fas fa-video me-2"></i>
                        Join Meeting Now
                    </a>
                    <?php else: ?>
                    <p class="timer mb-0" data-time="<?php echo $meeting['scheduled_time']; ?>">
                        <i class="fas fa-hourglass-half me-2"></i>
                        Time remaining: Calculating...
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    <script>
        function removeNotification(id) {
            document.getElementById(id).style.display = 'none';
        }

        function clearAllNotifications() {
            const notifications = document.querySelectorAll('.notification-card');
            notifications.forEach(notification => {
                notification.style.display = 'none';
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
                    timer.textContent = `Time remaining: ${hours}h ${minutes}m`;
                } else {
                    timer.textContent = 'Meeting should have started';
                }
            });
        }

        setInterval(updateTimers, 60000);
        updateTimers();
    </script>
</body>
</html>