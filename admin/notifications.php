<?php
require_once '../includes/init.php';
requireAdmin();

$db = Mori\Database::getInstance();
$users = $db->fetchAll("SELECT id, first_name, last_name, email, phone FROM users WHERE role = 'customer' ORDER BY id DESC");
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $medium = $_POST['medium'] ?? 'email';
    $target = $_POST['target'] ?? 'all';
    $userIds = [];

    if ($target === 'all') {
        $userIds = array_column($users, 'id');
    } elseif ($target === 'selected' && isset($_POST['user_ids'])) {
        $userIds = $_POST['user_ids'];
    }

    if (!empty($title) && !empty($content) && !empty($userIds)) {
        $notification = new Mori\Notification();
        $count = $notification->sendBulk($userIds, 'admin', $title, $content, $medium);
        $message = "Notification sent to $count users.";
    } else {
        $message = "Please fill all fields and select recipients.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Send Notification - MORI BOOKINGS Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-dashboard">
    <?php include 'includes/admin_header.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h1><i class="fas fa-bell"></i> Send Notification</h1>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="content">Message</label>
                    <textarea id="content" name="content" rows="5" required></textarea>
                </div>
                <div class="form-group">
                    <label for="medium">Medium</label>
                    <select id="medium" name="medium">
                        <option value="email">Email</option>
                        <option value="sms">SMS</option>
                        <option value="both">Both</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="target">Recipients</label>
                    <select id="target" name="target">
                        <option value="all">All Customers</option>
                        <option value="selected">Selected Customers</option>
                    </select>
                </div>
                <div id="userSelect" style="display:none; max-height:300px; overflow-y:auto; border:1px solid #ddd; padding:10px;">
                    <?php foreach ($users as $user): ?>
                        <label style="display:block; margin-bottom:5px;">
                            <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Notification</button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('target').addEventListener('change', function() {
            document.getElementById('userSelect').style.display = this.value === 'selected' ? 'block' : 'none';
        });
    </script>

    <?php include 'includes/admin_footer.php'; ?>
</body>
</html>