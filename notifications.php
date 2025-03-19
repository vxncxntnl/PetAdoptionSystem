<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'adopter') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch notifications from the database
$notifications = [];

$notification_query = $conn->prepare("SELECT message, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$notification_query->bind_param("i", $user_id);
$notification_query->execute();
$notification_result = $notification_query->get_result();

while ($row = $notification_result->fetch_assoc()) {
    $notifications[] = $row;
}
$notification_query->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }

        .container {
            margin-top: 50px;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }

        .list-group-item {
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin-bottom: 10px;
            transition: background-color 0.3s;
        }

        .list-group-item:hover {
            background-color: #f1f1f1;
        }

        .text-muted {
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Notifications</h1>
    <ul class="list-group">
        <?php if (empty($notifications)) { ?>
            <li class="list-group-item">No notifications.</li>
        <?php } else {
            foreach ($notifications as $notification) { ?>
                <li class="list-group-item">
                    <?php echo htmlspecialchars($notification['message']); ?>
                    <small class="text-muted"><?php echo htmlspecialchars($notification['created_at']); ?></small>
                </li>
            <?php }
        } ?>
    </ul>
</div>

</body>
</html>