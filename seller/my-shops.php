<?php
session_start();
include "../config.php";

// --- 1. SECURITY AND AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../seller-login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$stmt_role = $conn->prepare("SELECT role FROM login WHERE user_id = ? AND role = 'seller'");
$stmt_role->bind_param("i", $user_id);
$stmt_role->execute();
if ($stmt_role->get_result()->num_rows === 0) {
    session_destroy();
    header('Location: ../seller-login.php?error=access_denied');
    exit();
}
$stmt_role->close();


// --- 2. PRE-FETCH DATA FOR FILTERS (by District) ---
$districts = [];
$dist_stmt = $conn->prepare("
    SELECT DISTINCT sa.district 
    FROM shop_addresses sa
    JOIN shops s ON sa.shop_id = s.shop_id
    WHERE s.user_id = ? AND sa.district IS NOT NULL AND sa.district != '' 
    ORDER BY sa.district ASC
");
$dist_stmt->bind_param("i", $user_id);
$dist_stmt->execute();
$districts_result = $dist_stmt->get_result();
while ($row = $districts_result->fetch_assoc()) {
    $districts[] = $row['district'];
}
$dist_stmt->close();


// --- 3. DATA FETCHING WITH FILTERING AND SORTING ---
$filter_status = $_GET['filter_status'] ?? 'all';
$filter_district = $_GET['filter_district'] ?? 'all';
$search_query = $_GET['search_query'] ?? '';

$sql = "
    SELECT 
        s.shop_id, s.shop_name, s.shop_description, s.shop_image, s.business_phone, 
        s.shop_status, s.created_at,
        CONCAT_WS(', ', sa.building_name_or_number, sa.locality_or_town, sa.district, sa.pincode) AS formatted_address
    FROM shops s
    LEFT JOIN shop_addresses sa ON s.shop_id = sa.shop_id
    WHERE s.user_id = ?
";

$params = [$user_id];
$types = "i";

if ($filter_status === 'active' || $filter_status === 'inactive') { $sql .= " AND s.shop_status = ?"; $params[] = $filter_status; $types .= "s"; }
if ($filter_district !== 'all' && !empty($filter_district)) { $sql .= " AND sa.district = ?"; $params[] = $filter_district; $types .= "s"; }
if (!empty($search_query)) { $sql .= " AND s.shop_name LIKE ?"; $params[] = "%" . $search_query . "%"; $types .= "s"; }
$sql .= " ORDER BY CASE s.shop_status WHEN 'active' THEN 1 WHEN 'inactive' THEN 2 ELSE 3 END, s.created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt === false) { die("Error preparing the SQL statement: " . $conn->error); }
$stmt->bind_param($types, ...$params);
$stmt->execute();
$shops_result = $stmt->get_result();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Shops | Vastra</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="../refresh.js"></script>
  <style>
    .shop-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }
    .shop-card {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
        display: flex;
        flex-direction: column;
        width: 100%;
        flex: 1 1 280px;
        max-width: 340px;
        transition: transform 0.2s, box-shadow 0.2s;
        overflow: hidden;
    }
    .shop-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
    }
    
    /* === MODIFIED/FINAL CSS FOR STANDARDIZED IMAGES === */
    .shop-card-image {
        width: 100%;
        /* This modern CSS property creates a container with a 16:9 aspect ratio. */
        aspect-ratio: 16 / 9;
        background-color: #f0f0f0; /* Shows while image is loading */
        overflow: hidden; /* Important for clean corners */
    }
    .shop-card-image img {
        width: 100%;
        height: 100%;
        /* This is the key property. It scales the image to cover the entire container without distortion, cropping any excess. */
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    .shop-card:hover .shop-card-image img {
        transform: scale(1.05); /* Optional: nice zoom effect on hover */
    }
    /* === END OF MODIFIED CSS === */

    .shop-card-image-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        background-color: #e9ecef;
    }
    .shop-card-image-placeholder i {
        font-size: 48px;
        color: #adb5bd;
    }
    .shop-card-content {
        padding: 15px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        position: relative;
    }
    .shop-card-content .status-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
        color: white;
    }
    .status.active { background-color: #28a745; }
    .status.inactive { background-color: #dc3545; }
    .shop-card-content h3 {
        margin: 0 0 8px 0;
        font-size: 1.2em;
        padding-right: 70px;
    }
    .shop-card-address, .shop-card-phone {
        display: flex;
        align-items: center;
        color: #555;
        font-size: 13px;
        margin-bottom: 8px;
    }
    .shop-card-address i, .shop-card-phone i {
        margin-right: 8px;
        color: #007bff;
        width: 16px;
        text-align: center;
    }
    .shop-card-content p {
        margin: 0 0 15px 0;
        color: #666;
        font-size: 14px;
        flex-grow: 1;
        line-height: 1.5;
    }
    .shop-card-actions {
        display: flex;
        gap: 10px;
        margin-top: auto;
        border-top: 1px solid #eee;
        padding-top: 15px;
        margin-top: 15px;
    }
    .shop-card-actions .btn {
        flex: 1;
        text-align: center;
        padding: 8px;
        text-decoration: none;
        border-radius: 5px;
        color: #fff;
        font-weight: bold;
        font-size: 14px;
        transition: background-color 0.2s;
    }
    .btn-edit { background-color: #ff3f6c; }
    .btn-edit:hover { background-color: #d9365c; }
    .btn-delete { background-color: #dc3545; }
    .btn-delete:hover { background-color: #c82333; }
    .btn-activate { background-color: #28a745; }
    .btn-activate:hover { background-color: #218838; }
    .no-shops-message {
        width: 100%;
        text-align: center;
        padding: 40px;
        background-color: #f9f9f9;
        border: 1px dashed #ccc;
        border-radius: 8px;
    }
    .filter-bar { background-color: #f8f9fa; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
    .filter-form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 15px; }
    .filter-group { display: flex; flex-direction: column; }
    .filter-group label { font-weight: bold; margin-bottom: 5px; font-size: 14px; color: #555; }
    .filter-group select, .filter-group input { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; min-width: 150px; }
    .filter-group input[type="text"] { min-width: 200px; }
    .filter-buttons { display: flex; gap: 10px; }
    .btn-reset { background-color: #6c757d; color: white; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
    .btn-reset:hover { background-color: #5a6268; }
  </style>
</head>
<body>
  <div class="sidebar"><?php require "sidebar.php"; ?></div>

  <div class="main">
    <div class="header">
      <h1>My Shops</h1>
      <a href="add-shop.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Shop</a>
    </div>
    <div class="content">
      
      <!-- The rest of your HTML code remains exactly the same -->
      <div class="filter-bar">
        <form action="my-shops.php" method="GET" class="filter-form">
          <div class="filter-group">
            <label for="search_query">Shop Name</label>
            <input type="text" name="search_query" id="search_query" placeholder="Search by name..." value="<?php echo htmlspecialchars($search_query); ?>">
          </div>
          <div class="filter-group">
            <label for="filter_district">District</label>
            <select name="filter_district" id="filter_district">
                <option value="all">All Districts</option>
                <?php foreach ($districts as $district): ?>
                    <option value="<?php echo htmlspecialchars($district); ?>" <?php if ($filter_district === $district) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($district); ?>
                    </option>
                <?php endforeach; ?>
            </select>
          </div>
          <div class="filter-group">
            <label for="filter_status">Status</label>
            <select name="filter_status" id="filter_status">
              <option value="all" <?php if ($filter_status === 'all') echo 'selected'; ?>>All Statuses</option>
              <option value="active" <?php if ($filter_status === 'active') echo 'selected'; ?>>Active</option>
              <option value="inactive" <?php if ($filter_status === 'inactive') echo 'selected'; ?>>Inactive</option>
            </select>
          </div>
          <div class="filter-buttons">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="my-shops.php" class="btn btn-reset">Reset</a>
          </div>
        </form>
      </div>

      <div class="shop-grid">
        <?php if ($shops_result->num_rows > 0): ?>
          <?php while ($shop = $shops_result->fetch_assoc()): ?>
            <div class="shop-card">
              <div class="shop-card-image">
                <?php if (!empty($shop['shop_image'])): ?>
                  <img src="../images/<?php echo htmlspecialchars($shop['shop_image']); ?>" alt="<?php echo htmlspecialchars($shop['shop_name']); ?>">
                <?php else: ?>
                  <div class="shop-card-image-placeholder"><i class="fas fa-store"></i></div>
                <?php endif; ?>
              </div>
              <div class="shop-card-content">
                <span class="status-badge status <?php echo strtolower(htmlspecialchars($shop['shop_status'])); ?>"><?php echo htmlspecialchars($shop['shop_status']); ?></span>
                <h3><?php echo htmlspecialchars($shop['shop_name']); ?></h3>
                <div class="shop-card-address"><i class="fas fa-map-marker-alt"></i><span><?php echo htmlspecialchars($shop['formatted_address'] ?? 'No address provided'); ?></span></div>
                <?php if (!empty($shop['business_phone'])): ?>
                  <div class="shop-card-phone"><i class="fas fa-phone"></i><span><?php echo htmlspecialchars($shop['business_phone']); ?></span></div>
                <?php endif; ?>
                <p><?php echo htmlspecialchars(substr($shop['shop_description'], 0, 100)) . (strlen($shop['shop_description']) > 100 ? '...' : ''); ?></p>
                <div class="shop-card-actions">
                  <a href="edit-shop.php?id=<?php echo $shop['shop_id']; ?>" class="btn btn-edit"><i class="fas fa-edit"></i> Edit</a>
                  <?php if ($shop['shop_status'] === 'active'): ?>
                    <a href="delete-shop.php?id=<?php echo $shop['shop_id']; ?>&action=deactivate" class="btn btn-delete" onclick="return confirm('Are you sure? This will deactivate the shop and all of its products.');"><i class="fas fa-times-circle"></i> Deactivate</a>
                  <?php else: ?>
                    <a href="delete-shop.php?id=<?php echo $shop['shop_id']; ?>&action=activate" class="btn btn-activate" onclick="return confirm('Are you sure you want to activate this shop?');"><i class="fas fa-check-circle"></i> Activate</a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="no-shops-message">
            <h2>No shops found matching your criteria.</h2>
            <?php if ($filter_status === 'all' && $filter_district === 'all' && empty($search_query)): ?>
              <p>Click the "Add New Shop" button to get started!</p>
            <?php else: ?>
                <p>Try adjusting your search or filter settings.</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      
      <?php $stmt->close(); $conn->close(); ?>
    </div>
  </div>
</body>
</html>