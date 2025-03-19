<?php
session_start();
include 'db.php'; // Include your database connection file

// Redirect non-admin users
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch statistics
$total_pending = $conn->query("SELECT COUNT(*) AS count FROM adoption_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$total_approved = $conn->query("SELECT COUNT(*) AS count FROM adoption_requests WHERE status = 'approved'")->fetch_assoc()['count'];
$total_available_pets = $conn->query("SELECT COUNT(*) AS count FROM pets WHERE status = 'available'")->fetch_assoc()['count'];

// Fetch pending adoption requests
$pending_requests = $conn->query("SELECT adoption_requests.id, users.name AS user_name, adoption_requests.pet_name, adoption_requests.pet_type, adoption_requests.status 
                                   FROM adoption_requests 
                                   JOIN users ON adoption_requests.user_id = users.id 
                                   WHERE adoption_requests.status = 'pending'");

// Fetch adopted pets
$adopted_pets = $conn->query("SELECT adoption_requests.id, users.name AS adopter_name, adoption_requests.pet_name, adoption_requests.pet_type 
                               FROM adoption_requests 
                               JOIN users ON adoption_requests.user_id = users.id 
                               WHERE adoption_requests.status = 'approved'");

// Handle approval/rejection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];

    // Check if pet exists before updating
    $get_pet_query = "SELECT pet_name, pet_type, user_id FROM adoption_requests WHERE id = ?";
    $stmt = $conn->prepare($get_pet_query);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pet = $result->fetch_assoc();
    
    if ($pet) {
        $update_query = "UPDATE adoption_requests SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $action, $request_id);
        $stmt->execute();

        // Set notification message
        if ($action == 'approved') {
            $notification_message = "Your adoption request for {$pet['pet_name']} has been approved!";
            $_SESSION['message'] = $notification_message;

            // Insert notification into the database
            $insert_notification = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $insert_notification->bind_param("is", $pet['user_id'], $notification_message);
            $insert_notification->execute();
            $insert_notification->close();
        } elseif ($action == 'rejected') {
            $notification_message = "Your adoption request for {$pet['pet_name']} has been rejected.";
            $_SESSION['message'] = $notification_message;

            // Insert notification into the database
            $insert_notification = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $insert_notification->bind_param("is", $pet['user_id'], $notification_message);
            $insert_notification->execute();
            $insert_notification->close();
        }

        // Refresh the pending requests to show updated status
        header("Location: admin_dashboard.php"); // Stay on the admin dashboard
        exit();
    }
}

// Handle pet addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_pet'])) {
    $pet_name = $_POST['pet_name'];
    $pet_type = $_POST['pet_type'];
    $pet_age = $_POST['pet_age'];
    $pet_breed = $_POST['pet_breed'];
    $pet_quantity = $_POST['pet_quantity'];

    $insert_pet_query = "INSERT INTO pets (name, type, age, breed, quantity, status) VALUES (?, ?, ?, ?, ?, 'available')";
    $stmt = $conn->prepare($insert_pet_query);
    $stmt->bind_param("ssssi", $pet_name, $pet_type, $pet_age, $pet_breed, $pet_quantity);
    $stmt->execute();

    $_SESSION['message'] = "Pet added successfully!";
    header("Location: admin_dashboard.php");
    exit();
}

// Handle pet removal
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_pet'])) {
    $pet_id = $_POST['pet_id'];

    $delete_pet_query = "DELETE FROM pets WHERE pet_id = ?";
    $stmt = $conn->prepare($delete_pet_query);
    $stmt->bind_param("i", $pet_id);
    $stmt->execute();

    $_SESSION['message'] = "Pet removed successfully!";
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch all pets for management
$pets = $conn->query("SELECT pet_id, name, type, age, breed, quantity FROM pets");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            text-align: center;
        }
        h2 {
            color: #333;
        }
        .stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
        }
        .stat-box {
            background: #007BFF;
            color: white;
            padding: 10px; /* Reduced padding */
            border-radius: 5px;
            width: 20%; /* Adjusted width */
            min-width: 120px; /* Minimum width for smaller screens */
            font-size: 1.2rem; /* Slightly smaller font size */
        }
        table {
            width: 70%; /* Adjusted width */
            margin: auto;
            border-collapse: collapse;
            background: #fff;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ccc;
            padding: 8px; /* Adjusted padding */
        }
        th {
            background: #007BFF;
            color: white;
        }
        td {
            background: #f9f9f9;
        }
        button {
            padding: 6px 10px; /* Adjusted padding */
            margin: 5px;
            border: none;
            color: white;
            cursor: pointer;
            border-radius: 5px;
            transition: background 0.3s;
        }
        button[name='action'][value='approved'] {
            background: green;
        }
        button[name='action'][value='rejected'] {
            background: red;
        }
        button[name='remove_pet'] {
            background: #dc3545; /* Bootstrap danger color */
        }
        button:hover {
            opacity: 0.9; /* Slightly transparent on hover */
        }
        a {
            display: inline-block;
            padding: 8px; /* Adjusted padding */
            background: #007BFF;
            color: white;
            text-decoration: none;
            margin-top: 20px;
            border-radius: 5px;
        }
        .message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            margin: 10px auto;
            width: 50%;
            border-radius: 5px;
        }
        /* Styles for Manage Pets section */
        .manage-pets-form {
            width: 70%; /* Adjusted width */
            margin: auto;
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .manage-pets-form input {
            margin-bottom: 10px;
            width: calc(100% - 20px);
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .manage-pets-form button {
            background: #007BFF;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            color: white;
            cursor: pointer;
            transition: background 0.3s;
        }
        .manage-pets-form button:hover {
            background: #0056b3; /* Darker blue on hover */
        }
    </style>
</head>
<body>
    <h2>Admin Dashboard</h2>
    
    <div class="stats">
        <div class="stat-box">
            <h4>Pending Requests</h4>
            <p><?php echo $total_pending; ?></p>
        </div>
        <div class="stat-box">
            <h4>Approved Adoptions</h4>
            <p><?php echo $total_approved; ?></p>
        </div>
        <div class="stat-box">
            <h4>Available Pets</h4>
            <p><?php echo $total_available_pets; ?></p>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])) { ?>
        <div class="message"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php } ?>

    <h2>Pending Adoption Requests</h2>
    <table>
        <tr>
            <th>#</th>
            <th>User</th>
            <th>Pet Name</th>
            <th>Pet Type</th>
            <th>Status</th>
            <th>Action</th>
            <th>Qualification Status</th>
        </tr>
        <?php while ($row = $pending_requests->fetch_assoc()) { ?>
        <tr>
            <td><?php echo $row['id']; ?></td>
            <td><?php echo $row['user_name']; ?></td>
            <td><?php echo $row['pet_name']; ?></td>
            <td><?php echo $row['pet_type']; ?></td>
            <td><?php echo ucfirst($row['status']); ?></td>
            <td>
                <form method="POST">
                    <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                    <button type="submit" name="action" value="approved">Approve</button>
                    <button type="submit" name="action" value="rejected">Reject</button>
                </form>
            </td>
            <td>
                <?php
                // Display qualification status based on the modal logic
                echo "<span style='color: green;'><strong>Qualified</strong></span>"; // Placeholder for actual logic
                ?>
            </td>
        </tr>
        <?php } ?>
    </table>

    <h2>Adopted Pets</h2>
    <table>
        <tr>
            <th>#</th>
            <th>Adopter</th>
            <th>Pet Name</th>
            <th>Pet Type</th>
        </tr>
        <?php while ($row = $adopted_pets->fetch_assoc()) { ?>
        <tr>
            <td><?php echo $row['id']; ?></td>
            <td><?php echo $row['adopter_name']; ?></td>
            <td><?php echo $row['pet_name']; ?></td>
            <td><?php echo $row['pet_type']; ?></td>
        </tr>
        <?php } ?>
    </table>

    <h2>Manage Pets</h2>
    <div class="manage-pets-form">
        <h3>Add New Pet</h3>
        <form method="POST">
            <input type="text" name="pet_name" placeholder="Pet Name" required>
            <input type="text" name="pet_type" placeholder="Pet Type" required>
            <input type="text" name="pet_age" placeholder="Pet Age" required>
            <input type="text" name="pet_breed" placeholder="Pet Breed" required>
            <input type="number" name="pet_quantity" placeholder="Quantity" required>
            <button type="submit" name="add_pet">Add Pet</button>
        </form>
    </div>

    <h3>Existing Pets</h3>
    <table>
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Type</th>
            <th>Age</th>
            <th>Breed</th>
            <th>Quantity</th>
            <th>Action</th>
        </tr>
        <?php while ($row = $pets->fetch_assoc()) { ?>
        <tr>
            <td><?php echo $row['pet_id']; ?></td>
            <td><?php echo $row['name']; ?></td>
            <td><?php echo $row['type']; ?></td>
            <td><?php echo $row['age']; ?></td>
            <td><?php echo $row['breed']; ?></td>
            <td><?php echo $row['quantity']; ?></td>
            <td>
                <form method="POST">
                    <input type="hidden" name="pet_id" value="<?php echo $row['pet_id']; ?>">
                    <button type="submit" name="remove_pet">Remove</button>
                </form>
            </td>
        </tr>
        <?php } ?>
    </table>

    <a href="logout.php">Logout</a>
</body>
</html>