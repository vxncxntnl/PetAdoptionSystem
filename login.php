<?php
session_start();
include 'db.php';

// Hardcoded admin credentials
$admin_email = "petadaption@gmail.com";
$admin_password = "admin123";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    if ($email === $admin_email && $password === $admin_password) {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_type'] = 'admin';
        $_SESSION['name'] = 'Administrator';
        header("Location: admin_dashboard.php");
        exit();
    }

    // Check users table for regular users
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['name'] = $user['name'];
            
            if ($user['user_type'] == 'admin') {
                header("Location: admin_dashboard.php");
                exit();
            } else {
                header("Location: user_dashboard.php");
                exit();
            }
        } else {
            $message = "<div class='alert alert-danger'>Invalid password.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>No user found with that email.</div>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: url('back.jpeg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Poppins', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            width: 100%;
            max-width: 380px;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.15);
            text-align: center;
        }
        h1 {
            font-size: 30px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .tagline {
            font-size: 15px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        .form-control {
            padding: 12px;
            margin: 10px 0;
            border-radius: 8px;
            font-size: 16px;
        }
        .btn-primary {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            transform: scale(1.05);
        }
        .register-link {
            margin-top: 15px;
            font-size: 14px;
        }
        .register-link a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="login-container">
    <h1>Welcome</h1>
    <p class="tagline">Get your family a new member.</p>
    <?php echo $message; ?>
    <form method="POST">
        <div class="mb-3">
            <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
        </div>
        <div class="mb-3">
            <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn btn-primary">Login</button>
    </form>
    <p class="register-link"><a href="register.php">Create new account</a></p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
