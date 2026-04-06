<?php
session_start();
include "../config.php";

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['delete_address'])) {
        $address_id = (int)$_POST['address_id'];
        $sql = "DELETE FROM addresses WHERE address_id = ? AND user_id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $address_id, $user_id);
            $_SESSION['success_message'] = mysqli_stmt_execute($stmt) ? "Address deleted successfully." : "Error: Could not delete address.";
            mysqli_stmt_close($stmt);
            header("Location: manage-addresses.php");
            exit;
        }
    }

    if (isset($_POST['set_default'])) {
        $address_id = (int)$_POST['address_id'];
        mysqli_begin_transaction($conn);
        try {
            $stmt_unset = mysqli_prepare($conn, "UPDATE addresses SET is_default = 0 WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt_unset, "i", $user_id);
            mysqli_stmt_execute($stmt_unset);
            mysqli_stmt_close($stmt_unset);

            $stmt_set = mysqli_prepare($conn, "UPDATE addresses SET is_default = 1 WHERE address_id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt_set, "ii", $address_id, $user_id);
            mysqli_stmt_execute($stmt_set);
            mysqli_stmt_close($stmt_set);

            mysqli_commit($conn);
            $_SESSION['success_message'] = "Default address has been updated.";
        } catch (mysqli_sql_exception $exception) {
            mysqli_rollback($conn);
            $_SESSION['error_message'] = "Error updating default address.";
        }
        header("Location: manage-addresses.php");
        exit;
    }
}

$user_addresses = [];
$sql_addresses = "SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, address_id ASC";
if ($stmt_addresses = mysqli_prepare($conn, $sql_addresses)) {
    mysqli_stmt_bind_param($stmt_addresses, "i", $user_id);
    mysqli_stmt_execute($stmt_addresses);
    $result_addresses = mysqli_stmt_get_result($stmt_addresses);
    while ($address_row = mysqli_fetch_assoc($result_addresses)) {
        $user_addresses[] = $address_row;
    }
    mysqli_stmt_close($stmt_addresses);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Addresses | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../index.css">
    <style>
        body { background-color: var(--light-gray); }
        .manage-address-container { max-width: 900px; margin: 40px auto; padding-bottom: 50px; }
        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; /* Align to top for better wrapping on mobile */
            flex-wrap: wrap; /* Allow items to wrap on smaller screens */
            gap: 20px; /* Add gap for spacing when wrapped */
            margin-bottom: 30px; 
            padding: 0 15px; 
        }

        /* --- NEW: Group for title and back button --- */
        .page-title-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .page-header h1 { 
            font-size: 2.2rem; 
            color: var(--secondary); 
            margin: 0; /* Reset default margin */
        }
        
        /* --- NEW: Style for the back button --- */
        .btn-back-to-dash {
            display: inline-block;
            background-color: transparent;
            color: var(--secondary);
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-weight: 500;
            transition: all 0.3s;
            align-self: flex-start; /* Keep button aligned to the left */
        }
        .btn-back-to-dash:hover {
            background-color: #f0f0f0;
            border-color: #ccc;
            color: var(--primary);
        }
        .btn-back-to-dash .fas {
            margin-right: 8px;
        }

        .btn-add-new { background-color: var(--primary); color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: 500; transition: background-color 0.3s; }
        .btn-add-new:hover { background-color: #e0355b; }
        .btn-add-new .fas { margin-right: 8px; }

        .address-grid { display: grid; grid-template-columns: 1fr; gap: 25px; padding: 0 15px; }
        .address-item { background: white; border-radius: 8px; border: 1px solid #eee; padding: 20px; position: relative; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .address-item.is-default { border: 2px solid var(--primary); }
        .default-badge { position: absolute; top: 0; right: 20px; background-color: var(--primary); color: white; padding: 4px 12px; font-size: 0.8rem; font-weight: 500; border-radius: 0 0 8px 8px; }
        .address-body { line-height: 1.6; color: var(--dark-gray); }
        .address-body strong { font-weight: 600; color: var(--secondary); font-size: 1.1rem; display: block; margin-bottom: 5px; }
        .address-actions { border-top: 1px solid #f0f0f0; margin-top: 20px; padding-top: 15px; display: flex; gap: 20px; align-items: center; }
        .action-btn { background: none; border: none; color: var(--primary); cursor: pointer; font-weight: 500; padding: 0; font-size: 0.9rem; text-decoration: none; }
        .action-btn:hover { text-decoration: underline; }
        .action-btn.delete { color: #c0392b; }
        .message-box { padding: 15px; margin: 0 15px 20px 15px; border-radius: 5px; border: 1px solid transparent; text-align: center; }
        .message-box.success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .message-box.error-summary { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
    </style>
</head>
<body>
    <nav class="navbar">
       <div class="container nav-container">
            <a href="../index.php" class="logo">VASTRA</a>
            <?php include "nav_links.html"; ?>
            <div class="nav-icons"><a href="../dashboard.php" title="My Account"><i class="far fa-user"></i></a></div>
        </div>
    </nav>
    
    <main class="container manage-address-container">
        <div class="page-header">
            <div class="page-title-group">
                <a href="../dashboard.php" class="btn-back-to-dash"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                <h1>Manage Addresses</h1>
            </div>
            
            <a href="add-address.php?redirect_url=manage-addresses.php" class="btn-add-new"><i class="fas fa-plus"></i> Add a New Address</a>
        </div>

        <?php
        if (isset($_SESSION['success_message'])) {
            echo '<div class="message-box success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="message-box error-summary">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
            unset($_SESSION['error_message']);
        }
        ?>
        
        <div class="address-grid">
            <?php if (!empty($user_addresses)): foreach ($user_addresses as $address): ?>
            <div class="address-item <?php if ($address['is_default']) echo 'is-default'; ?>">
                <?php if ($address['is_default']): ?><div class="default-badge">Default</div><?php endif; ?>
                <div class="address-body">
                    <strong><?= htmlspecialchars($address['recipient_fname'] . ' ' . $address['recipient_lname']) ?></strong>
                    
                    <span><?= htmlspecialchars($address['house_address']) ?></span><br>
                    <?php if (!empty($address['landmark'])): ?><span>Landmark: <?= htmlspecialchars($address['landmark']) ?></span><br><?php endif; ?>
                    <span><?= htmlspecialchars($address['locality_or_town']) ?>, <?= htmlspecialchars($address['district']) ?></span><br>
                    <span><?= htmlspecialchars($address['state']) ?> - <?= htmlspecialchars($address['pincode']) ?></span><br>
                    <span style="margin-top:10px; display:inline-block;"><strong>Phone:</strong> <?= htmlspecialchars($address['mobile_number']) ?></span>
                </div>
                <div class="address-actions">
                    <a href="edit-address.php?id=<?= $address['address_id'] ?>" class="action-btn">Edit</a>|
                    <form method="POST" action="manage-addresses.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this address?');">
                        <input type="hidden" name="address_id" value="<?= $address['address_id'] ?>">
                        <button type="submit" name="delete_address" class="action-btn delete">Delete</button>
                    </form>
                    <?php if (!$address['is_default']): ?>
                    | <form method="POST" action="manage-addresses.php" style="display: inline;">
                        <input type="hidden" name="address_id" value="<?= $address['address_id'] ?>">
                        <button type="submit" name="set_default" class="action-btn">Set as Default</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; else: ?>
                <p style="text-align: center; padding-top: 20px;">You haven't added any addresses yet. Click the button above to add one.</p>
            <?php endif; ?>
        </div>
    </main>
    <?php include "../footer.html"; ?>
</body>
</html>