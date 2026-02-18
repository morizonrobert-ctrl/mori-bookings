<?php
require_once '../includes/init.php';
requireRole('super_admin'); // Only super admin can manage users

$db = Mori\Database::getInstance();

$roleFilter = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';

$where = ["1=1"];
$params = [];

if (!empty($roleFilter)) {
    $where[] = "role = ?";
    $params[] = $roleFilter;
}
if (!empty($search)) {
    $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = implode(' AND ', $where);
$users = $db->fetchAll("SELECT * FROM users WHERE $whereClause ORDER BY id DESC", $params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - MORI BOOKINGS Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-dashboard">
    <?php include 'includes/admin_header.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h1><i class="fas fa-users"></i> Manage Users</h1>
        </div>

        <div class="filter-card">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <input type="text" name="search" placeholder="Search by name, email, phone" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <select name="role">
                        <option value="">All Roles</option>
                        <option value="customer" <?php echo $roleFilter === 'customer' ? 'selected' : ''; ?>>Customer</option>
                        <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="super_admin" <?php echo $roleFilter === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                        <option value="operator" <?php echo $roleFilter === 'operator' ? 'selected' : ''; ?>>Operator</option>
                        <option value="driver" <?php echo $roleFilter === 'driver' ? 'selected' : ''; ?>>Driver</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Verified</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo $u['id']; ?></td>
                        <td><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><?php echo htmlspecialchars($u['phone']); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $u['role'])); ?></td>
                        <td><span class="status-badge status-<?php echo $u['is_verified'] ? 'verified' : 'unverified'; ?>"><?php echo $u['is_verified'] ? 'Verified' : 'Unverified'; ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                        <td>
                            <a href="user_edit.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline"><i class="fas fa-edit"></i> Edit</a>
                            <?php if ($u['id'] != currentUserId()): ?>
                                <a href="user_delete.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i> Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include 'includes/admin_footer.php'; ?>
</body>
</html>