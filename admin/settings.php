<?php
require_once '../includes/init.php';
requireRole('super_admin'); // Only super admin can change settings

$db = Mori\Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $settingKey = substr($key, 8);
            $db->update('system_settings', ['setting_value' => $value], 'setting_key = ?', [$settingKey]);
        }
    }
    $message = "Settings updated successfully.";
}

$settings = $db->fetchAll("SELECT * FROM system_settings ORDER BY category, setting_key");
$grouped = [];
foreach ($settings as $s) {
    $grouped[$s['category']][] = $s;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings - MORI BOOKINGS Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-dashboard">
    <?php include 'includes/admin_header.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h1><i class="fas fa-cog"></i> System Settings</h1>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" class="settings-form">
            <?php foreach ($grouped as $category => $items): ?>
                <div class="settings-category">
                    <h2><?php echo ucfirst($category); ?></h2>
                    <?php foreach ($items as $setting): ?>
                        <div class="form-group">
                            <label for="setting_<?php echo $setting['setting_key']; ?>">
                                <?php echo htmlspecialchars($setting['description'] ?: $setting['setting_key']); ?>
                            </label>
                            <?php if ($setting['setting_type'] === 'boolean'): ?>
                                <select name="setting_<?php echo $setting['setting_key']; ?>" id="setting_<?php echo $setting['setting_key']; ?>">
                                    <option value="true" <?php echo $setting['setting_value'] === 'true' ? 'selected' : ''; ?>>Yes</option>
                                    <option value="false" <?php echo $setting['setting_value'] === 'false' ? 'selected' : ''; ?>>No</option>
                                </select>
                            <?php elseif ($setting['setting_type'] === 'json' || $setting['setting_type'] === 'array'): ?>
                                <textarea name="setting_<?php echo $setting['setting_key']; ?>" id="setting_<?php echo $setting['setting_key']; ?>" rows="3"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                            <?php else: ?>
                                <input type="<?php echo $setting['setting_type'] === 'integer' ? 'number' : 'text'; ?>" 
                                       name="setting_<?php echo $setting['setting_key']; ?>" 
                                       id="setting_<?php echo $setting['setting_key']; ?>" 
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>

    <?php include 'includes/admin_footer.php'; ?>
</body>
</html>