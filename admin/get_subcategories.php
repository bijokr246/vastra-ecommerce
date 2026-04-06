<?php
/**
 * get_subcategories.php
 *
 * This script is a dedicated data provider for the subcategory dropdown.
 * - If a 'category_id' is provided, it returns only the subcategories for that category.
 * - If no 'category_id' is provided, it returns all subcategories.
 */

// 1. Include the database connection
include "../config.php"; // Adjust path if needed

// 2. Set the response header to JSON
header('Content-Type: application/json');

$response = [];

// 3. Check if a specific, valid category_id was sent
if (isset($_GET['category_id']) && is_numeric($_GET['category_id'])) {
    $category_id = (int)$_GET['category_id'];

    // Prepare and execute a query to get ONLY the subcategories for the given category
    $stmt = $conn->prepare("SELECT subcategory_id, subcategory_name FROM subcategory WHERE category_id = ? ORDER BY subcategory_name ASC");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} else {
    // If no valid category_id is provided, fetch ALL subcategories
    $result = $conn->query("SELECT subcategory_id, subcategory_name FROM subcategory ORDER BY subcategory_name ASC");
    $response = $result->fetch_all(MYSQLI_ASSOC);
}

// 5. Close the database connection.
$conn->close();

// 6. Encode the response array as a JSON string and output it.
echo json_encode($response);