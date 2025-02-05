<?php 
session_start(); 
require '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Get notification count for Manager, TeamLead, and TeamMember
$notificationCount = 0;
if ($role === 'Manager') {
    // Count pending extension requests
    $extensionQuery = "SELECT COUNT(*) as count FROM project_extensions WHERE status IS NULL";
    $result = $conn->query($extensionQuery);
    $row = $result->fetch_assoc();
    $notificationCount += $row['count'];

    // Count meetings requiring manager
    $meetingQuery = "SELECT COUNT(*) as count FROM meetings WHERE manager_required = 1 AND status = 'scheduled'";
    $result = $conn->query($meetingQuery);
    $row = $result->fetch_assoc();
    $notificationCount += $row['count'];

} elseif ($role === 'TeamLead') {
    // Count extension responses
    $extensionQuery = "SELECT COUNT(*) as count FROM project_extensions WHERE requested_by = ? AND (status = 'approved' OR status = 'rejected')";
    $stmt = $conn->prepare($extensionQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $notificationCount += $row['count'];

    // Count upcoming meetings
    $meetingQuery = "SELECT COUNT(*) as count FROM meetings WHERE created_by = ? AND status = 'scheduled'";
    $stmt = $conn->prepare($meetingQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $notificationCount += $row['count'];

} elseif ($role === 'TeamMember') {
    // Count unviewed tasks
    $taskQuery = "SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'not_viewed'";
    $stmt = $conn->prepare($taskQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $notificationCount += $row['count'];

    // Count upcoming meetings for team member's team
    if (isset($_SESSION['team_id'])) {
        $meetingQuery = "SELECT COUNT(*) as count FROM meetings WHERE team_id = ? AND status = 'scheduled'";
        $stmt = $conn->prepare($meetingQuery);
        $stmt->bind_param("i", $_SESSION['team_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $notificationCount += $row['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .notification-bell {
            position: relative;
            cursor: pointer;
            padding: 10px;
        }
        .notification-count {
            position: absolute;
            top: 0;
            right: 0;
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .btn {
            margin: 5px;
            display: inline-block;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .navbar {
            background-color: #f8f9fa;
            padding: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; padding: 0 20px;">
            <h1>Welcome to Collaborative Work Community</h1>
            <?php if ($role === 'Manager' || $role === 'TeamLead' || $role === 'TeamMember'): ?>
                <div class="notification-bell" onclick="window.location.href='<?php 
                    echo $role === 'Manager' ? 'manager_notifications.php' : 
                        ($role === 'TeamLead' ? 'teamlead_notifications.php' : 'teammember_notifications.php'); 
                ?>'">
                    🔔
                    <?php if ($notificationCount > 0): ?>
                        <span class="notification-count"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <h2>Role: <?= htmlspecialchars($role) ?></h2>
        <?php if ($role === 'HR'): ?>
            <a href="hr_panel.php" class="btn btn-primary">User creation</a>
            <a href="create_team.php" class="btn btn-primary">Create Team</a>
            <a href="project_performance.php" class="btn btn-primary">Project Performance</a>
            <a href="performance_analysis.php" class="btn btn-primary">Team members performance</a>
        <?php elseif ($role === 'TeamLead'): ?>
            <a href="team_lead_panel.php" class="btn btn-primary">Team Lead Panel</a>
            <a href="create_meeting.php" class="btn btn-primary">Create Meeting</a>
            <a href="view_meetings.php" class="btn btn-primary">View Meetings</a>
            <a href="team_projects.php" class="btn btn-primary">View Projects</a>
            <a href="discussion.php" class="btn btn-secondary">View Discussions</a>
            <a href="assign_tasks.php" class="btn btn-primary">Assign Tasks</a>
            <a href="file_sharing.php" class="btn btn-primary">File Share</a>
        <?php elseif ($role === 'Manager'): ?>
            <!-- <a href="manager_panel.php" class="btn btn-primary">Manager Analytics</a> -->
            <a href="manager_discussion.php" class="btn btn-primary">View Discussions</a>
            <a href="manager_meetings.php" class="btn btn-primary">Meetings</a>
            <a href="assign_project.php" class="btn btn-primary">Assign Project</a>
            <a href="manage_projects.php" class="btn btn-primary">Manage Projects</a>
            <a href="manager_file_sharing.php" class="btn btn-primary">Manage Files</a>
        <?php else: ?>
            <a href="team_member_dashboard.php" class="btn btn-primary">Team Member Panel</a>
            <a href="view_meetings.php" class="btn btn-primary">View Meetings</a>
            <a href="discussion.php" class="btn btn-secondary">View Discussions</a>
            <a href="file_sharing.php" class="btn btn-primary">File Share</a>
            
        <?php endif; ?>
        <!-- <a href="profile.php" class="btn btn-secondary">My Profile</a> -->
        <a href="../logout.php" class="btn btn-danger">Logout</a>
    </div>

    <?php if ($role === 'TeamMember' && $notificationCount > 0): ?>
        <script>
            // Optional: Show a welcome message with notification count
            window.onload = function() {
                alert('Welcome! You have ' + <?php echo $notificationCount; ?> + ' new notification(s)');
            }
        </script>
    <?php endif; ?>
</body>
</html>