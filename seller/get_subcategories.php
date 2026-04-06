<?php
// get_subcategories.php

// 1. Set the content type header to JSON
header('Content-Type: application/json');

// 2. Include database connection
include "../config.php";

// 3. Get the category_id from the URL, ensuring it's an integer
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Initialize an empty array for the results
$subcategories = [];

// 4. Check if a specific category was requested
if ($category_id > 0) {
    // A specific category is selected: fetch only its subcategories
    $stmt = $conn->prepare("SELECT subcategory_id, subcategory_name FROM subcategory WHERE category_id = ? ORDER BY subcategory_name ASC");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subcategories = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    // MODIFIED: No specific category (or "All Categories") is selected: fetch ALL subcategories
    $result = $conn->query("SELECT subcategory_id, subcategory_name FROM subcategory ORDER BY subcategory_name ASC");
    $subcategories = $result->fetch_all(MYSQLI_ASSOC);
}

$conn->close();

// 5. Output the subcategories array as a JSON string
echo json_encode($subcategories);