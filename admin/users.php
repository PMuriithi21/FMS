<?php
// admin/users.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/notification_helper.php';
requireRole('admin');

$db = getDB();

if (isset($_GET['read'])) {
    markNotificationAsRead((int)$_GET['read']);
}

$pageTitle = 'User Management';
$activeNav = 'users';

$success = '';
$error = '';

// =====================================================
// ADD USER
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {

    $name     = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = trim($_POST['role'] ?? '');

    if (!$name || !$username || !$phone || !$password || !$role) {

        $error = 'All fields are required.';

    } else {

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $db->prepare("INSERT INTO users (name, username, phone, email, password, role) VALUES (?, ?, ?, ?, ?, ?)");

        if ($stmt) {

            $stmt->bind_param("ssssss", $name, $username, $phone, $email, $hash, $role);

try {
    $stmt->execute();
    $success = "User '$username' created successfully.";
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate entry')) {
        $error = "Username already exists.";
    } else {
        $error = "Database error: " . $e->getMessage();
    }
}

$stmt->close();

        } else {
            $error = "Database error: " . $db->error;
        }
    }
}

// =====================================================
// TOGGLE USER STATUS
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {

    $uid2 = (int)($_POST['uid'] ?? 0);

    $newst = ($_POST['current_status'] ?? '') === 'active'
        ? 'inactive'
        : 'active';

    $stmt = $db->prepare("UPDATE users SET status = ? WHERE user_id = ?");

    if ($stmt) {

        $stmt->bind_param("si", $newst, $uid2);

        if ($stmt->execute()) {
            $success = "User status updated.";
        } else {
            $error = "Failed to update user status.";
        }

        $stmt->close();

    } else {
        $error = "Database error: " . $db->error;
    }
}

// =====================================================
// RESET PASSWORD
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_pw') {

    $uid2 = (int)($_POST['uid'] ?? 0);
    $newpw = trim($_POST['new_password'] ?? '');

    if (!empty($newpw)) {

        $hash = password_hash($newpw, PASSWORD_BCRYPT);

        $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");

        if ($stmt) {

            $stmt->bind_param("si", $hash, $uid2);

            if ($stmt->execute()) {
                $success = "Password reset successfully.";
            } else {
                $error = "Failed to reset password.";
            }

            $stmt->close();

        } else {
            $error = "Database error: " . $db->error;
        }

    } else {
        $error = "Please enter a new password.";
    }
}

// =====================================================
// UPDATE EMAIL
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_email') {

    $uid2 = (int)($_POST['uid'] ?? 0);
    $newEmail = trim($_POST['email'] ?? '');

    $stmt = $db->prepare("UPDATE users SET email = ? WHERE user_id = ?");

    if ($stmt) {

        $stmt->bind_param("si", $newEmail, $uid2);

        if ($stmt->execute()) {
            $success = "Email updated successfully.";
        } else {
            $error = "Failed to update email.";
        }

        $stmt->close();

    } else {
        $error = "Database error: " . $db->error;
    }
}

$users = $db->query("SELECT * FROM users ORDER BY role, name");

include __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success"><?= clean($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?= clean($error) ?></div>
<?php endif; ?>

<div class="grid-2">

    <div class="card">
        <div class="card-title">➕ Add New User</div>

        <form method="POST">

            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Jane Mwangi" required>
            </div>

            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" class="form-control" placeholder="e.g. jane.mwangi" required>
            </div>

            <div class="form-group">
                <label>Phone Number *</label>
                <input
                    type="text"
                    name="phone"
                    class="form-control"
                    placeholder="+254712345678"
                    required>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input
                    type="email"
                    name="email"
                    class="form-control"
                    placeholder="jane@example.com">
            </div>

            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" class="form-control" placeholder="Minimum 8 characters" required>
            </div>

            <div class="form-group">
                <label>Role *</label>

                <select name="role" class="form-control" required>
                    <option value="">— Select role —</option>
                    <option value="supervisor">Supervisor</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                ➕ Create User
            </button>

        </form>
    </div>

    <div class="card">

        <div class="card-title">🔑 Reset Password</div>

        <form method="POST">

            <input type="hidden" name="action" value="reset_pw">

            <div class="form-group">

                <label>Select User</label>

                <select name="uid" class="form-control" required>

                    <option value="">— Select user —</option>

                    <?php
                    $u2 = $db->query("SELECT user_id, name, username, role FROM users ORDER BY name");

                    while ($u = $u2->fetch_assoc()):
                    ?>

                    <option value="<?= $u['user_id'] ?>">
                        <?= clean($u['name']) ?>
                        (<?= clean($u['username']) ?> / <?= clean($u['role']) ?>)
                    </option>

                    <?php endwhile; ?>

                </select>

            </div>

            <div class="form-group">
                <label>New Password *</label>

                <input type="password"
                       name="new_password"
                       class="form-control"
                       placeholder="New password"
                       required>
            </div>

            <button type="submit" class="btn btn-brown btn-block">
                🔑 Reset Password
            </button>

        </form>

    </div>

</div>

<div class="card">

    <div class="card-title">👥 All Users</div>

    <div class="table-wrap">

        <table>

            <thead>

            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created</th>
                <th>Action</th>
                <th>Phone</th>
                <th>Email</th>
            </tr>

            </thead>

            <tbody>

            <?php while ($row = $users->fetch_assoc()): ?>

                <tr>

                    <td><?= $row['user_id'] ?></td>

                    <td><?= clean($row['name']) ?></td>

                    <td><?= clean($row['username']) ?></td>

                    <td>
                        <span class="badge">
                            <?= ucfirst($row['role']) ?>
                        </span>
                    </td>

                    <td>
                        <span class="badge badge-<?= $row['status'] ?>">
                            <?= ucfirst($row['status']) ?>
                        </span>
                    </td>

                    <td><?= $row['created_at'] ?></td>

                    <td>

                        <form method="POST">

                            <input type="hidden" name="action" value="toggle">

                            <input type="hidden" name="uid" value="<?= $row['user_id'] ?>">

                            <input type="hidden" name="current_status" value="<?= $row['status'] ?>">

                            <button
                                type="submit"
                                class="btn btn-sm <?= $row['status'] === 'active' ? 'btn-danger' : 'btn-brown' ?>">

                                <?= $row['status'] === 'active'
                                    ? 'Deactivate'
                                    : 'Activate' ?>

                            </button>

                        </form>

                    </td>

                    <td><?= clean($row['phone']) ?></td>

                    <td>
                        <form method="POST" style="display:flex;gap:6px;align-items:center;">
                            <input type="hidden" name="action" value="update_email">
                            <input type="hidden" name="uid" value="<?= $row['user_id'] ?>">
                            <input
                                type="email"
                                name="email"
                                value="<?= clean($row['email'] ?? '') ?>"
                                class="form-control"
                                style="min-width:160px;font-size:12px;padding:4px 8px;"
                                placeholder="email@example.com">
                            <button type="submit" class="btn btn-sm btn-brown">Save</button>
                        </form>
                    </td>

                </tr>

            <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>