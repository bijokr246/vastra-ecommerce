<?php
// 1. START THE SESSION & CHECK FOR LOGIN
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// 2. INCLUDE DATABASE CONNECTION
include "../config.php";

// Get filter and sort parameters from URL, with defaults
$filter_status = $_GET['filter_status'] ?? 'pending';
$sort = $_GET['sort'] ?? 'newest';
$query_params = ['filter_status' => $filter_status, 'sort' => $sort];
$redirect_query_string = http_build_query($query_params);


// 3. HANDLE ACTIONS (APPROVE/REJECT)
if (isset($_GET['action']) && isset($_GET['request_id'])) {
    $request_id = intval($_GET['request_id']);
    
    // --- REJECT ACTION ---
    if ($_GET['action'] == 'reject') {
        $stmt_get_img = $conn->prepare("SELECT shop_image FROM seller_requests WHERE request_id = ?");
        $stmt_get_img->bind_param("i", $request_id);
        $stmt_get_img->execute();
        $img_result = $stmt_get_img->get_result()->fetch_assoc();
        if ($img_result && !empty($img_result['shop_image'])) {
            $pending_image_path = '../pending-shop-images/' . $img_result['shop_image'];
            if (file_exists($pending_image_path)) {
                unlink($pending_image_path);
            }
        }
        $stmt_get_img->close();
        $stmt = $conn->prepare("UPDATE seller_requests SET status = 'rejected' WHERE request_id = ?");
        $stmt->bind_param("i", $request_id);
        if ($stmt->execute()) { $_SESSION['message'] = "Seller request has been rejected."; }
        $stmt->close();
    }

    // --- MODIFIED: APPROVE ACTION (WITH SUBSCRIPTION LOGIC) ---
    if ($_GET['action'] == 'approve' && isset($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);

        $stmt_req = $conn->prepare(
            "SELECT shop_name, shop_description, shop_image, business_phone, 
                    building_name_or_number, landmark, locality_or_town, district, pincode, state 
             FROM seller_requests WHERE request_id = ?"
        );
        $stmt_req->bind_param("i", $request_id);
        $stmt_req->execute();
        $request_details = $stmt_req->get_result()->fetch_assoc();
        $stmt_req->close();

        if ($request_details) {
            $conn->begin_transaction();
            try {
                // Step 1: Update the request status to 'approved'
                $stmt1 = $conn->prepare("UPDATE seller_requests SET status = 'approved' WHERE request_id = ?");
                $stmt1->bind_param("i", $request_id);
                $stmt1->execute();
                $stmt1->close();

                // Step 2: Update the user's role to 'seller'
                $stmt2 = $conn->prepare("UPDATE login SET role = 'seller' WHERE user_id = ?");
                $stmt2->bind_param("i", $user_id);
                $stmt2->execute();
                $stmt2->close();

                // Step 3: Move the shop image (if it exists)
                $shop_image = $request_details['shop_image'];
                if (!empty($shop_image)) {
                    $source_path = '../pending-shop-images/' . $shop_image;
                    $destination_path = '../images/' . $shop_image;
                    if (file_exists($source_path) && !rename($source_path, $destination_path)) {
                        throw new Exception("Could not move the shop image.");
                    }
                }

                // Step 4: Insert into `shops` table
                $stmt3 = $conn->prepare("INSERT INTO shops (user_id, shop_name, shop_description, shop_image, business_phone, shop_status) VALUES (?, ?, ?, ?, ?, 'active')");
                $stmt3->bind_param("issss", $user_id, $request_details['shop_name'], $request_details['shop_description'], $shop_image, $request_details['business_phone']);
                $stmt3->execute();
                
                $new_shop_id = $conn->insert_id;
                if (!$new_shop_id) {
                    throw new Exception("Failed to create the shop record.");
                }
                $stmt3->close();

                // Step 5: Insert the address into the `shop_addresses` table
                $stmt4 = $conn->prepare(
                    "INSERT INTO shop_addresses (shop_id, building_name_or_number, landmark, locality_or_town, district, pincode, state) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt4->bind_param("issssss", 
                    $new_shop_id, $request_details['building_name_or_number'], $request_details['landmark'],
                    $request_details['locality_or_town'], $request_details['district'],
                    $request_details['pincode'], $request_details['state']
                );
                $stmt4->execute();
                $stmt4->close();
                
                // --- NEW: Step 6: Grant the free trial IF the user is a new seller ---
                // First, check if the user already has any subscription.
                $stmt_check_sub = $conn->prepare("SELECT subscription_id FROM seller_subscriptions WHERE user_id = ? LIMIT 1");
                $stmt_check_sub->bind_param("i", $user_id);
                $stmt_check_sub->execute();
                $has_subscription = $stmt_check_sub->get_result()->num_rows > 0;
                $stmt_check_sub->close();

                // Only add the free trial if they don't have a subscription history.
                // This prevents existing sellers from getting another free trial when adding a new shop.
                if (!$has_subscription) {
                    $free_trial_plan_id = 1; // Corresponds to the 'Free Trial' plan in subscription_plans table
                    $stmt5 = $conn->prepare(
                        "INSERT INTO seller_subscriptions (user_id, plan_id, start_date, end_date, status) 
                         VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active')"
                    );
                    $stmt5->bind_param("ii", $user_id, $free_trial_plan_id);
                    $stmt5->execute();
                    $stmt5->close();
                    $_SESSION['message'] = "Seller approved successfully! A 30-day free trial has been activated for this user.";
                } else {
                    $_SESSION['message'] = "Seller approved successfully! New shop has been created.";
                }
                
                // If all steps succeed, commit the transaction
                $conn->commit();

            } catch (Exception $e) {
                // If any step fails, roll back all changes
                $conn->rollback();
                $_SESSION['error'] = "Failed to approve seller: " . $e->getMessage();
            }
        }
    }
    header("Location: seller-requests.php?" . $redirect_query_string);
    exit();
}


// --- MODIFIED: 4. FETCH REQUESTS FOR DISPLAY ---
// Using CONCAT_WS with NULLIF to gracefully handle empty address fields.
$sql = "SELECT sr.request_id, sr.user_id, sr.shop_name, sr.business_phone, sr.shop_image, sr.requested_at, sr.status, 
               CONCAT(u.fname, ' ', u.lname) as full_name, u.email,
               CONCAT_WS(', ', NULLIF(sr.building_name_or_number, ''), NULLIF(sr.locality_or_town, ''), NULLIF(sr.district, ''), NULLIF(sr.pincode, '')) AS formatted_address
        FROM seller_requests sr 
        JOIN users u ON sr.user_id = u.user_id";

if (!empty($filter_status)) { $sql .= " WHERE sr.status = ?"; }

switch ($sort) {
    case 'oldest': $sql .= " ORDER BY sr.requested_at ASC"; break;
    case 'name_asc': $sql .= " ORDER BY sr.shop_name ASC"; break;
    case 'name_desc': $sql .= " ORDER BY sr.shop_name DESC"; break;
    default: $sql .= " ORDER BY sr.requested_at DESC"; break;
}

$stmt = $conn->prepare($sql);
if (!empty($filter_status)) { $stmt->bind_param("s", $filter_status); }
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Seller Requests | Vastra Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="../refresh.js"></script>
  <style>
    .filter-controls { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 20px; background-color: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; }
    .filter-controls .form-group { display: flex; flex-direction: column; }
    .filter-controls label { font-size: 0.85rem; margin-bottom: 5px; font-weight: 600; color: #495057; }
    .filter-controls select { padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 0.9rem; }
    .filter-controls .btn { align-self: flex-end; padding: 9px 15px; }
    .modal-overlay { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.85); align-items: center; justify-content: center; }
    .modal-content { position: relative; margin: auto; padding: 0; width: auto; max-width: 80%; max-height: 90%; }
    .modal-content img { width: auto; height: auto; max-width: 100%; max-height: 85vh; display: block; border-radius: 4px; }
    .modal-close-btn { position: absolute; top: -15px; right: 0px; color: #fff; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; }
    .modal-close-btn:hover, .modal-close-btn:focus { color: #bbb; text-decoration: none; }
    @media (max-width: 768px) { .modal-content { max-width: 90%; } }
  </style>
</head>
<body>
  <aside class="sidebar"><?php require "header.php" ?></aside>

  <main class="main-content">
    <h1 class="page-header">Seller Verification</h1>
    
    <!-- Session message display -->
    <?php if (isset($_SESSION['message'])): ?><div class="message success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div><?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?><div class="message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>

    <section class="content-card">
      <div class="page-header">
        <h2>Seller Requests</h2>
        <!-- Filter form -->
        <form action="seller-requests.php" method="GET" class="filter-controls">
            <div class="form-group">
                <label for="filter_status">Filter by Status:</label>
                <select name="filter_status" id="filter_status" onchange="this.form.submit()">
                    <option value="pending" <?php if ($filter_status == 'pending') echo 'selected'; ?>>Pending</option>
                    <option value="approved" <?php if ($filter_status == 'approved') echo 'selected'; ?>>Approved</option>
                    <option value="rejected" <?php if ($filter_status == 'rejected') echo 'selected'; ?>>Rejected</option>
                </select>
            </div>
            <div class="form-group">
                <label for="sort">Sort by:</label>
                <select name="sort" id="sort" onchange="this.form.submit()">
                    <option value="newest" <?php if ($sort == 'newest') echo 'selected'; ?>>Newest First</option>
                    <option value="oldest" <?php if ($sort == 'oldest') echo 'selected'; ?>>Oldest First</option>
                    <option value="name_asc" <?php if ($sort == 'name_asc') echo 'selected'; ?>>Shop Name (A-Z)</option>
                    <option value="name_desc" <?php if ($sort == 'name_desc') echo 'selected'; ?>>Shop Name (Z-A)</option>
                </select>
            </div>
            <?php if ($filter_status !== 'pending' || $sort !== 'newest'): ?>
                <a href="seller-requests.php" class="btn btn-sm btn-secondary">Clear Filters</a>
            <?php endif; ?>
        </form>
      </div>

      <table class="data-table">
        <thead>
          <tr>
            <th>Shop Name</th>
            <th>Owner</th>
            <th>Email</th>
            <th>Shop Address</th>
            <th>Business Phone</th>
            <th>Shop Image</th>
            <th>Submitted On</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($requests)): ?>
            <tr><td colspan="9" style="text-align: center;">No requests found matching your criteria.</td></tr>
          <?php else: ?>
            <?php foreach ($requests as $request): ?>
              <tr>
                <td><?php echo htmlspecialchars($request['shop_name']); ?></td>
                <td><?php echo htmlspecialchars($request['full_name']); ?></td>
                <td><?php echo htmlspecialchars($request['email']); ?></td>
                <td><?php echo htmlspecialchars($request['formatted_address'] ?: 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($request['business_phone'] ?? 'N/A'); ?></td>
                <td>
                    <?php if (!empty($request['shop_image'])): 
                        // Determine image path based on status
                        $image_dir = ($request['status'] === 'pending') ? '../pending-shop-images/' : '../images/';
                    ?>
                        <button class="btn btn-sm btn-info view-image-btn" 
                                data-image-url="<?php echo $image_dir . htmlspecialchars($request['shop_image']); ?>">
                            View Image
                        </button>
                    <?php else: ?>
                        <span class="text-muted">N/A</span>
                    <?php endif; ?>
                </td>
                <td><?php echo date('M d, Y', strtotime($request['requested_at'])); ?></td>
                <td><span class="status <?php echo htmlspecialchars($request['status']); ?>"><?php echo htmlspecialchars(ucfirst($request['status'])); ?></span></td>
                <td class="action-buttons">
                  <?php if ($request['status'] === 'pending'): ?>
                    <a class="btn btn-sm btn-primary" href="seller-requests.php?<?php echo $redirect_query_string; ?>&action=approve&request_id=<?php echo $request['request_id']; ?>&user_id=<?php echo $request['user_id']; ?>" onclick="return confirm('Are you sure you want to approve this seller?');">Approve</a>
                    <a class="btn btn-sm btn-secondary" href="seller-requests.php?<?php echo $redirect_query_string; ?>&action=reject&request_id=<?php echo $request['request_id']; ?>" onclick="return confirm('Are you sure you want to reject this request?');">Reject</a>
                  <?php else: ?>
                    <span class="text-muted">Action Taken</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </section>
  </main>

  <!-- Image Modal HTML -->
  <div id="imageModal" class="modal-overlay">
    <span class="modal-close-btn">&times;</span>
    <div class="modal-content">
        <img id="modalImage" src="" alt="Shop Image">
    </div>
  </div>

  <!-- JavaScript for Modal -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImage');
        const closeBtn = document.querySelector('.modal-close-btn');
        const viewButtons = document.querySelectorAll('.view-image-btn');

        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const imageUrl = this.dataset.imageUrl;
                modal.style.display = 'flex';
                modalImg.src = imageUrl;
            });
        });

        function closeModal() {
            modal.style.display = 'none';
        }

        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape") {
                closeModal();
            }
        });
    });
  </script>
</body>
</html>