<?php
require_once '../includes/init.php';
requireAuth();

$user = new Mori\User();
$userId = currentUserId();
$userData = $user->getUser($userId);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $idNumber = $_POST['id_number'] ?? '';
        
        try {
            $user->updateProfile($userId, [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'id_number' => $idNumber
            ]);
            $success = 'Profile updated successfully';
            $userData = $user->getUser($userId); // refresh
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if ($new !== $confirm) {
            $error = 'New passwords do not match';
        } else {
            try {
                $user->changePassword($userId, $current, $new);
                $success = 'Password changed successfully';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - MORI BOOKINGS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-user"></i> My Profile</h1>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="profile-grid">
            <!-- Personal Information -->
            <div class="profile-card">
                <h2>Personal Information</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($userData['first_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($userData['last_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($userData['phone']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>ID Number</label>
                        <input type="text" name="id_number" value="<?php echo htmlspecialchars($userData['id_number'] ?? ''); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="profile-card">
                <h2>Change Password</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </div>

            <!-- Account Info -->
            <div class="profile-card">
                <h2>Account Info</h2>
                <p><strong>Member since:</strong> <?php echo formatDate($userData['created_at'], 'F j, Y'); ?></p>
                <p><strong>Total trips:</strong> <?php echo $userData['total_trips']; ?></p>
                <p><strong>Loyalty points:</strong> <?php echo $userData['loyalty_points']; ?></p>
                <p><strong>Free trips available:</strong> <?php echo $userData['free_trips_available']; ?></p>
                <p><strong>Account type:</strong> <?php echo ucfirst($userData['role']); ?></p>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>