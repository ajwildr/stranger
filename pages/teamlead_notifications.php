<?php
session_start();
require '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'TeamLead') {
    // header("Location: login.php");
    // exit();
    echo "<script>window.location.href = 'error.php';</script>";
}

$user_id = $_SESSION['user_id'];

// Fetch extension responses from last 2 days
$extensionQuery = "SELECT pe.*, p.title as project_title 
    FROM project_extensions pe 
    JOIN projects p ON pe.project_id = p.project_id 
    WHERE pe.requested_by = ? 
    AND (pe.status = 'approved' OR pe.status = 'rejected')
    AND pe.responded_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
    ORDER BY pe.responded_at DESC";
$stmt = $conn->prepare($extensionQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$extensionResult = $stmt->get_result();

// Fetch meetings
$meetingQuery = "SELECT * FROM meetings WHERE created_by = ? AND status = 'scheduled' ORDER BY scheduled_time ASC";
$stmt = $conn->prepare($meetingQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$meetingResult = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Lead Notifications</title>
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
            padding-top: 70px;
        }

        .navbar {
            background-color: var(--primary);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem;
        }

        .back-btn-container {
            position: relative;
            display: inline-block;
        }

        .back-btn {
            background-color: transparent;
            color: white;
            border: 2px solid white;
            padding: 0.5rem 1.5rem;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .back-btn:hover {
            background-color: white;
            color: var(--primary);
            transform: translateY(-1px);
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

        .status-approved {
            color: var(--success);
            font-weight: 600;
        }

        .status-rejected {
            color: var(--danger);
            font-weight: 600;
        }

        .timer {
            color: var(--accent);
            font-weight: 500;
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

        .section-title {
            color: var(--primary);
            font-weight: 600;
            margin: 2rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--accent);
        }

        .response-time {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-top: 0.5rem;
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
            <div class="back-btn-container">
                <a href="teamlead_dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left me-2"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <!-- Notification Header -->
        <div class="notification-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="notification-title">Your Notifications</h1>
                    <p class="notification-subtitle mb-0">Stay updated with your team's activities</p>
                </div>
                <button class="clear-all-btn" onclick="clearAllNotifications()">
                    <i class="fas fa-trash-alt me-2"></i>
                    Clear All
                </button>
            </div>
        </div>

        <!-- Project Extensions Section -->
        <h2 class="section-title">
            <i class="fas fa-project-diagram me-2"></i>
            Project Extension Responses
        </h2>
        
        <?php while ($extension = $extensionResult->fetch_assoc()): ?>
            <div class="notification-card card" id="extension-<?php echo $extension['extension_id']; ?>">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <h3 class="h5 mb-3"><?php echo htmlspecialchars($extension['project_title']); ?></h3>
                        <button class="close-btn" onclick="removeNotification('extension-<?php echo $extension['extension_id']; ?>')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <p class="mb-2">Status: <span class="status-<?php echo strtolower($extension['status']); ?>"><?php echo ucfirst($extension['status']); ?></span></p>
                    <p class="mb-2">Response: <?php echo htmlspecialchars($extension['response_note']); ?></p>
                    <p class="mb-2">New Due Date: <?php echo date('Y-m-d', strtotime($extension['new_due_date'])); ?></p>
                    <p class="response-time mb-0">Responded: <?php echo time_elapsed_string($extension['responded_at']); ?></p>
                </div>
            </div>
        <?php endwhile; ?>

        <!-- Meetings Section -->
        <h2 class="section-title">
            <i class="fas fa-calendar-check me-2"></i>
            Upcoming Meetings
        </h2>

        <?php while ($meeting = $meetingResult->fetch_assoc()): ?>
            <div class="notification-card card" id="meeting-<?php echo $meeting['meeting_id']; ?>">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <h3 class="h5 mb-3"><?php echo htmlspecialchars($meeting['title']); ?></h3>
                        <button class="close-btn" onclick="removeNotification('meeting-<?php echo $meeting['meeting_id']; ?>')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <p class="mb-2">Scheduled Time: <?php echo date('Y-m-d h:i A', strtotime($meeting['scheduled_time'])); ?></p>
                    <p class="timer mb-3" data-time="<?php echo $meeting['scheduled_time']; ?>">Time remaining: Calculating...</p>
                    <a href="view_meetings.php" class="btn btn-primary">
                        <i class="fas fa-video me-2"></i>
                        Join Meeting
                    </a>
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

    <?php
    // Helper function to format time elapsed
    function time_elapsed_string($datetime) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        if ($diff->d == 1) {
            return '1 day ago';
        } elseif ($diff->h >= 1) {
            return $diff->h . ' hours ago';
        } elseif ($diff->i >= 1) {
            return $diff->i . ' minutes ago';
        } else {
            return 'Just now';
        }
    }
    ?>
</body>
</html>