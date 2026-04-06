<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}
include "../config.php";

$errors = [];
$success_message = '';

// --- Handle Form Submissions (Add, Edit, Toggle Status) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Action: Add or Edit a Plan ---
    if ($action === 'add' || $action === 'edit') {
        $plan_id = $_POST['plan_id'] ?? null;
        $plan_name = trim($_POST['plan_name']);
        $duration_days = filter_input(INPUT_POST, 'duration_days', FILTER_VALIDATE_INT);
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);

        // Server-side Validation
        if (empty($plan_name)) $errors[] = "Plan name is required.";
        if ($duration_days === false || $duration_days <= 0) $errors[] = "Duration must be a positive number of days.";
        if ($price === false || $price < 0) $errors[] = "Price must be a valid number (0 or more).";

        if (empty($errors)) {
            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO subscription_plans (plan_name, duration_days, price) VALUES (?, ?, ?)");
                $stmt->bind_param("sid", $plan_name, $duration_days, $price);
                $success_message = "Plan added successfully!";
            } else {
                $stmt = $conn->prepare("UPDATE subscription_plans SET plan_name = ?, duration_days = ?, price = ? WHERE plan_id = ?");
                $stmt->bind_param("sidi", $plan_name, $duration_days, $price, $plan_id);
                $success_message = "Plan updated successfully!";
            }
            if (!$stmt->execute()) {
                $errors[] = "Database error: " . $stmt->error;
                $success_message = '';
            }
            $stmt->close();
        }
    }

    // --- Action: Toggle Plan Status ---
    if ($action === 'toggle_status') {
        $plan_id = $_POST['plan_id'];
        $current_status = $_POST['current_status'];
        $new_status = ($current_status == 1) ? 0 : 1;
        
        $stmt = $conn->prepare("UPDATE subscription_plans SET is_active = ? WHERE plan_id = ?");
        $stmt->bind_param("ii", $new_status, $plan_id);
        $stmt->execute();
        $stmt->close();
        $success_message = "Plan status updated successfully!";
    }
}

// Fetch all plans to display in the table
$plans = [];
$result = $conn->query("SELECT * FROM subscription_plans ORDER BY plan_id");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $plans[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Subscription Plans | Vastra Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 25px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; position: relative; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; top: 10px; right: 20px; }
        .form-group { margin-bottom: 20px; position: relative; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        
        /* --- THIS IS THE CORRECTED PART --- */
        .btn-primary { 
            background-color: var(--primary); /* Corrected from --primary-color */
            color: white; 
        }
        .btn-primary:hover {
            background-color: var(--secondary); /* Added a hover effect */
        }
        /* --- END OF CORRECTION --- */

        .btn-danger { background-color: #dc3545; color: white; }
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .error-text { color: #dc3545; font-size: 0.8em; display: block; margin-top: 5px; height: 1em; }
        .input-error { border-color: #dc3545 !important; }
    </style>
</head>
<body>
<aside class="sidebar"><?php require "header.php"; ?></aside>
<div class="main-content">
    <header class="header">
        <h1>Manage Subscription Plans</h1>
        <button id="addPlanBtn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Plan</button>
    </header>
    <main>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error) echo "<p>$error</p>"; ?>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <section class="content-card">
            <h2>Current Plans</h2>
            <table class="data-table">
                <thead>
                    <tr><th>ID</th><th>Plan Name</th><th>Duration</th><th>Price</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $plan): ?>
                    <tr>
                        <td><?php echo $plan['plan_id']; ?></td>
                        <td><?php echo htmlspecialchars($plan['plan_name']); ?></td>
                        <td><?php echo $plan['duration_days']; ?> Days</td>
                        <td>₹<?php echo number_format($plan['price'], 2); ?></td>
                        <td>
                            <span class="status <?php echo $plan['is_active'] ? 'active' : 'expired'; ?>">
                                <?php echo $plan['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm edit-btn" 
                                    data-id="<?php echo $plan['plan_id']; ?>"
                                    data-name="<?php echo htmlspecialchars($plan['plan_name']); ?>"
                                    data-duration="<?php echo $plan['duration_days']; ?>"
                                    data-price="<?php echo $plan['price']; ?>">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <form action="" method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="plan_id" value="<?php echo $plan['plan_id']; ?>">
                                <input type="hidden" name="current_status" value="<?php echo $plan['is_active']; ?>">
                                <button type="submit" class="btn btn-sm <?php echo $plan['is_active'] ? 'btn-danger' : 'btn-success'; ?>" onclick="return confirm('Are you sure you want to change the status?')">
                                    <i class="fas fa-power-off"></i> <?php echo $plan['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

<!-- Add/Edit Plan Modal -->
<div id="planModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h2 id="modalTitle">Add New Plan</h2>
        <form id="planForm" method="POST" action="" novalidate>
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="plan_id" id="planId">
            <div class="form-group">
                <label for="plan_name">Plan Name</label>
                <input type="text" id="plan_name" name="plan_name" required>
                <span class="error-text"></span>
            </div>
            <div class="form-group">
                <label for="duration_days">Duration (in days)</label>
                <input type="number" id="duration_days" name="duration_days" min="1" required>
                <span class="error-text"></span>
            </div>
            <div class="form-group">
                <label for="price">Price (₹)</label>
                <input type="number" id="price" name="price" min="0" step="0.01" required>
                <span class="error-text"></span>
            </div>
            <button type="submit" class="btn btn-primary">Save Plan</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('planModal');
    const addBtn = document.getElementById('addPlanBtn');
    const closeBtn = document.querySelector('.close-btn');
    const form = document.getElementById('planForm');
    const modalTitle = document.getElementById('modalTitle');
    
    const planNameInput = document.getElementById('plan_name');
    const durationInput = document.getElementById('duration_days');
    const priceInput = document.getElementById('price');
    const allInputs = [planNameInput, durationInput, priceInput];

    function clearErrors() {
        allInputs.forEach(input => {
            input.classList.remove('input-error');
            input.nextElementSibling.textContent = '';
        });
    }

    addBtn.onclick = function() {
        modalTitle.textContent = 'Add New Plan';
        form.reset();
        clearErrors();
        document.getElementById('formAction').value = 'add';
        modal.style.display = 'block';
    }

    document.querySelectorAll('.edit-btn').forEach(button => {
        button.onclick = function() {
            modalTitle.textContent = 'Edit Plan';
            clearErrors();
            document.getElementById('formAction').value = 'edit';
            document.getElementById('planId').value = this.dataset.id;
            planNameInput.value = this.dataset.name;
            durationInput.value = this.dataset.duration;
            priceInput.value = this.dataset.price;
            modal.style.display = 'block';
        }
    });
    
    closeBtn.onclick = () => modal.style.display = 'none';
    window.onclick = (event) => { if (event.target == modal) modal.style.display = 'none'; }

    function validatePlanName() {
        const errorSpan = planNameInput.nextElementSibling;
        if (planNameInput.value.trim() === '') {
            planNameInput.classList.add('input-error');
            errorSpan.textContent = 'Plan name is required.';
            return false;
        }
        planNameInput.classList.remove('input-error');
        errorSpan.textContent = '';
        return true;
    }

    function validateDuration() {
        const errorSpan = durationInput.nextElementSibling;
        const duration = parseInt(durationInput.value, 10);
        if (isNaN(duration) || duration <= 0) {
            durationInput.classList.add('input-error');
            errorSpan.textContent = 'Duration must be a positive number.';
            return false;
        }
        durationInput.classList.remove('input-error');
        errorSpan.textContent = '';
        return true;
    }

    function validatePrice() {
        const errorSpan = priceInput.nextElementSibling;
        const price = parseFloat(priceInput.value);
        if (priceInput.value.trim() === '' || isNaN(price) || price < 0) {
            priceInput.classList.add('input-error');
            errorSpan.textContent = 'Price must be 0 or a positive number.';
            return false;
        }
        priceInput.classList.remove('input-error');
        errorSpan.textContent = '';
        return true;
    }
    
    planNameInput.addEventListener('input', validatePlanName);
    durationInput.addEventListener('input', validateDuration);
    priceInput.addEventListener('input', validatePrice);

    form.addEventListener('submit', function(e) {
        const isNameValid = validatePlanName();
        const isDurationValid = validateDuration();
        const isPriceValid = validatePrice();

        if (!isNameValid || !isDurationValid || !isPriceValid) {
            e.preventDefault();
        }
    });
});
</script>
</body>
</html>