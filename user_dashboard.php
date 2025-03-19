<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'adopter') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$message = "";

// Display notification message
if (isset($_SESSION['notification'])) {
    echo "<div class='alert alert-info'>" . $_SESSION['notification'] . "</div>";
    unset($_SESSION['notification']); // Clear the notification after displaying it
}

// Handle adoption request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['adopt_pet'])) {
    $pet_name = $_POST['pet_name'];
    $pet_type = $_POST['pet_type'];

    // Check if the pet is still available in the database
    $check_availability = $conn->prepare("SELECT quantity FROM pets WHERE name = ? AND type = ?");
    $check_availability->bind_param("ss", $pet_name, $pet_type);
    $check_availability->execute();
    $availability_result = $check_availability->get_result();
    $availability_row = $availability_result->fetch_assoc();

    if ($availability_row && $availability_row['quantity'] > 0) {
        // Insert adoption request into adoption_requests table
        $stmt = $conn->prepare("INSERT INTO adoption_requests (user_id, pet_name, pet_type, adoption_date) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iss", $user_id, $pet_name, $pet_type);
        if ($stmt->execute()) {
            // Update pet quantity after adoption
            $update_quantity = $conn->prepare("UPDATE pets SET quantity = quantity - 1 WHERE name = ? AND type = ?");
            $update_quantity->bind_param("ss", $pet_name, $pet_type);
            $update_quantity->execute();
            $message = "<div class='alert alert-success'>Adoption request sent!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Request failed. Try again.</div>";
        }
        $stmt->close();
    } else {
        $message = "<div class='alert alert-danger'>Pet is not available for adoption.</div>";
    }
    $check_availability->close();
}

// Fetch adopted pets
$adopted_pets = [];
$adopted_query = $conn->prepare("SELECT pet_name, pet_type, adoption_date FROM adoption_requests WHERE user_id = ? ORDER BY adoption_date DESC");
$adopted_query->bind_param("i", $user_id);
$adopted_query->execute();
$adopted_result = $adopted_query->get_result();
while ($adopted_row = $adopted_result->fetch_assoc()) {
    $adopted_pets[] = $adopted_row;
}
$adopted_query->close();

// Fetch available pets
$available_pets = [];
$pet_query = $conn->prepare("SELECT name, type, quantity FROM pets WHERE quantity > 0");
$pet_query->execute();
$pet_result = $pet_query->get_result();

while ($pet_row = $pet_result->fetch_assoc()) {
    $available_pets[] = $pet_row;
}
$pet_query->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            background: linear-gradient(135deg, #007BFF, #0056b3);
            color: white;
            padding: 30px;
            position: fixed;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
        }

        .sidebar h2 {
            font-weight: bold;
            margin-bottom: 10px;
        }

        .sidebar p {
            font-size: 16px;
            margin-bottom: 20px;
        }

        .sidebar a {
            width: 100%;
            text-align: center;
            margin-bottom: 15px;
            font-weight: bold;
            transition: background 0.3s;
            color: white;
            text-decoration: none;
        }

        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .container {
            margin-left: 270px;
            padding: 50px;
        }

        h1 {
            text-align: center;
            color: #333;
            font-weight: bold;
        }

        .pet-container {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .pet-card {
            background: white;
            padding: 20px;
            text-align: center;
            width: 280px;
            border-radius: 15px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .pet-card:hover {
            transform: translateY(-5px);
        }

        .pet-card img {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border: 3px solid #007BFF;
            border-radius: 15px;
        }

        .btn-adopt {
            margin-top: 10px;
            font-weight: bold;
            background: #007BFF;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            transition: background 0.3s;
        }

        .btn-adopt:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h2>Welcome!</h2>
    <p><?php echo htmlspecialchars($user_name); ?></p>
    <a href="notifications.php" class="btn btn-warning">Notifications 
        <?php if (isset($_SESSION['notification_count']) && $_SESSION['notification_count'] > 0) { ?>
            <span class="badge bg-danger"><?php echo $_SESSION['notification_count']; ?></span>
        <?php } ?>
    </a>
    <a href="logout.php" class="btn btn-danger">Logout</a>
</div>

<!-- Main Content -->
<div class="container">
    <h1 id="pet-title" style="text-align: center;">Available Pets for Adoption</h1>

    <?php echo $message; ?>

    <div id="available-pets">
        <!-- Cats Section -->
        <h2>Cats</h2>
        <div class="pet-container">
            <?php
            // Cat images
            $cat_images = [
                "Whiskers" => "images/cat1.webp",
                "Mittens" => "images/cat2.jpg",
                "Luna" => "images/cat3.webp",
                "Cleo" => "images/cat4.jpg"
            ];

            // Cat ages in months
            $cat_ages = [
                "Whiskers" => "8 months",
                "Mittens" => "6 months",
                "Luna" => "10 months",
                "Cleo" => "12 months"
            ];

            // Cat breeds (includes a Pinoy cat)
            $cat_breeds = [
                "Whiskers" => "Persian",
                "Mittens" => "Siamese",
                "Luna" => "Bengal",
                "Cleo" => "Pusang Pinoy (Native Cat)"
            ];

            // Cat genders (2 boys, 2 girls)
            $cat_genders = [
                "Whiskers" => "Male",
                "Mittens" => "Male",
                "Luna" => "Female",
                "Cleo" => "Female"
            ];

            foreach ($available_pets as $pet) {
                if ($pet['type'] == 'cat' && isset($cat_images[$pet['name']])) {
                    $image_path = $cat_images[$pet['name']];
                    $age = isset($cat_ages[$pet['name']]) ? $cat_ages[$pet['name']] : "Unknown months";
                    $breed = isset($cat_breeds[$pet['name']]) ? $cat_breeds[$pet['name']] : "Unknown breed";
                    $gender = isset($cat_genders[$pet['name']]) ? $cat_genders[$pet['name']] : "Unknown";

                    echo "
                    <div class='pet-card'>
                        <img src='$image_path' alt='{$pet['name']}' class='img-fluid' />
                        <p><strong>Name:</strong> {$pet['name']}</p>
                        <p><strong>Breed:</strong> $breed</p>
                        <p><strong>Age:</strong> $age</p>
                        <p><strong>Gender:</strong> $gender</p>
                        <button type='button' class='btn btn-primary btn-adopt' data-bs-toggle='modal' data-bs-target='#qualificationModal' 
                            data-pet-name='{$pet['name']}' data-pet-type='cat'>Adopt</button>
                    </div>
                    ";
                }
            }
            ?>
        </div>

        <!-- Dogs Section -->
        <h2>Dogs</h2>
        <div class="pet-container">
            <?php
            // Dog images
            $dog_images = [
                "Buddy" => "images/dog1.webp",
                "Max" => "images/dog2.jpg",
                "Luna" => "images/dog3.jpg",
                "Charlie" => "images/dog4.jpg"
            ];

            // Dog ages in months
            $dog_ages = [
                "Buddy" => "10 months",
                "Max" => "8 months",
                "Luna" => "12 months",
                "Charlie" => "6 months"
            ];

            // Dog breeds
            $dog_breeds = [
                "Buddy" => "Golden Retriever",
                "Max" => "German Shepherd",
                "Luna" => "Siberian Husky",
                "Charlie" => "Aspins (Asong Pinoy)"
            ];

            // Dog genders
            $dog_genders = [
                "Buddy" => "Male",
                "Max" => "Male",
                "Luna" => "Female",
                "Charlie" => "Male"
            ];

            foreach ($available_pets as $pet) {
                if ($pet['type'] == 'dog' && isset($dog_images[$pet['name']])) {
                    $image_path = $dog_images[$pet['name']];
                    $age = isset($dog_ages[$pet['name']]) ? $dog_ages[$pet['name']] : "Unknown months";
                    $breed = isset($dog_breeds[$pet['name']]) ? $dog_breeds[$pet['name']] : "Unknown breed";
                    $gender = isset($dog_genders[$pet['name']]) ? $dog_genders[$pet['name']] : "Unknown";

                    echo "
                    <div class='pet-card'>
                        <img src='$image_path' alt='{$pet['name']}' class='img-fluid' />
                        <p><strong>Name:</strong> {$pet['name']}</p>
                        <p><strong>Breed:</strong> $breed</p>
                        <p><strong>Age:</strong> $age</p>
                        <p><strong>Gender:</strong> $gender</p>
                        <button type='button' class='btn btn-primary btn-adopt' data-bs-toggle='modal' data-bs-target='#qualificationModal' 
                            data-pet-name='{$pet['name']}' data-pet-type='dog'>Adopt</button>
                    </div>
                    ";
                }
            }
            ?>
        </div>
    </div>
</div>

<!-- Qualification Modal -->
<div class="modal fade" id="qualificationModal" tabindex="-1" aria-labelledby="qualificationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qualificationModalLabel">Adoption Qualification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="qualificationForm">
                    <div class="mb-3">
                        <label for="user_age" class="form-label">Your Age:</label>
                        <input type="number" class="form-control" id="user_age" required min="18" placeholder="Enter your age">
                    </div>
                    <div class="mb-3">
                        <label for="user_address" class="form-label">Your Address:</label>
                        <textarea class="form-control" id="user_address" required placeholder="Enter your address"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="user_experience" class="form-label">Do you have previous experience with pets?</label>
                        <select class="form-select" id="user_experience" required>
                            <option value="">Select...</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="living_situation" class="form-label">Do you have enough space for a pet?</label>
                        <select class="form-select" id="living_situation" required>
                            <option value="">Select...</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                    <input type="hidden" id="modal_pet_name">
                    <input type="hidden" id="modal_pet_type">
                    <button type="submit" class="btn btn-primary">Submit Qualification</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Populate modal with pet data
    const adoptButtons = document.querySelectorAll('.btn-adopt');
    adoptButtons.forEach(button => {
        button.addEventListener('click', function() {
            const petName = this.getAttribute('data-pet-name');
            const petType = this.getAttribute('data-pet-type');
            document.getElementById('modal_pet_name').value = petName;
            document.getElementById('modal_pet_type').value = petType;
        });
    });

    // Handle qualification form submission
    document.getElementById('qualificationForm').addEventListener('submit', function(event) {
        event.preventDefault(); // Prevent default form submission

        // Get user age
        const userAge = document.getElementById('user_age').value;
        if (userAge < 18) {
            alert("You must be at least 18 years old to adopt a pet.");
            return;
        }

        // Get pet data
        const petName = document.getElementById('modal_pet_name').value;
        const petType = document.getElementById('modal_pet_type').value;

        // Create a hidden form to submit the adoption request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = ''; // Current page

        const nameInput = document.createElement('input');
        nameInput.type = 'hidden';
        nameInput.name = 'pet_name';
        nameInput.value = petName;

        const typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.name = 'pet_type';
        typeInput.value = petType;

        const adoptInput = document.createElement('input');
        adoptInput.type = 'hidden';
        adoptInput.name = 'adopt_pet';
        adoptInput.value = '1'; // Indicate that this is an adoption request

        form.appendChild(nameInput);
        form.appendChild(typeInput);
        form.appendChild(adoptInput);
        document.body.appendChild(form);
        form.submit(); // Submit the form
    });
</script>

</body>
</html>