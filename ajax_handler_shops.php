<?php

include "config.php";
define('UPLOAD_PATH', 'images/'); // Define path for images

// Basic validation: ensure an action is specified
if (!isset($_GET['action'])) {
    die("Invalid request.");
}

$action = $_GET['action'];

// ACTION 1: Get the HTML grid of shop cards
if ($action === 'get_shop_grid') {
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $search_term = '%' . $query . '%';

    $sql = "SELECT 
                s.shop_id, 
                s.shop_name, 
                s.shop_description, 
                s.shop_image,
                sa.locality_or_town, 
                sa.district
            FROM 
                shops s
            INNER JOIN 
                shop_addresses sa ON s.shop_id = sa.shop_id
            WHERE 
                s.shop_status = 'active'";
    
    if (!empty($query)) {
        $sql .= " AND (s.shop_name LIKE ? OR s.shop_description LIKE ? OR sa.locality_or_town LIKE ? OR sa.district LIKE ?)";
    }
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!empty($query)) {
        mysqli_stmt_bind_param($stmt, "ssss", $search_term, $search_term, $search_term, $search_term);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        while ($shop = mysqli_fetch_assoc($result)) {
            // Truncate description for the card view
            $short_description = strlen($shop['shop_description']) > 100 
                ? substr($shop['shop_description'], 0, 100) . '...' 
                : $shop['shop_description'];
            
            // Combine locality and district for a cleaner address display
            $full_address = htmlspecialchars($shop['locality_or_town'] . ', ' . $shop['district']);
            
            // Output the HTML for each shop card
            echo '
            <div class="shop-card" 
                 data-shop-name="' . htmlspecialchars($shop['shop_name']) . '" 
                 data-shop-address="' . $full_address . '" 
                 data-full-description="' . htmlspecialchars($shop['shop_description']) . '" 
                 data-shop-image="' . UPLOAD_PATH . htmlspecialchars($shop['shop_image']) . '">
                <img src="' . UPLOAD_PATH . htmlspecialchars($shop['shop_image']) . '" alt="' . htmlspecialchars($shop['shop_name']) . '">
                <div class="shop-card-content">
                    <h3>' . htmlspecialchars($shop['shop_name']) . '</h3>
                    <p class="shop-location"><i class="fas fa-map-marker-alt"></i> ' . $full_address . '</p>
                    <p class="shop-description">' . htmlspecialchars($short_description) . '</p>
                    <div class="card-actions">
                        <button class="btn-read-more">Read More</button>
                        <!-- CRITICAL UPDATE: Link now uses shop_id -->
                        <a href="shop-products.php?id=' . $shop['shop_id'] . '" class="btn-visit-shop">Visit Shop</a>
                    </div>
                </div>
            </div>';
        }
    } else {
        echo '<p style="text-align:center; grid-column: 1 / -1; color: #555;">No shops found matching your search.</p>';
    }
    mysqli_stmt_close($stmt);
    exit;
}

// ACTION 2: Get JSON data for search suggestions
if ($action === 'get_shop_suggestions') {
    header('Content-Type: application/json');
    $suggestions = [];
    if (isset($_GET['q']) && !empty(trim($_GET['q']))) {
        $query = trim($_GET['q']);
        $search_term = '%' . $query . '%';
        
        // UPDATED: Query now correctly targets the `shops` table
        $sql = "SELECT DISTINCT shop_name FROM shops WHERE shop_status = 'active' AND shop_name LIKE ? LIMIT 5";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $search_term);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $suggestions[] = $row['shop_name'];
            }
            mysqli_stmt_close($stmt);
        }
    }
    echo json_encode($suggestions);
    exit;
}
?>