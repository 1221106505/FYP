<?php
session_start();
require_once __DIR__ . '/../include/database.php';

// Get filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? 'all';
$price = $_GET['price'] ?? 'all';
$sort = $_GET['sort'] ?? 'title';

try {
    // Build query
    $sql = "SELECT b.*, c.name as category_name 
            FROM books b 
            LEFT JOIN categories c ON b.category_id = c.id 
            WHERE b.stock_quantity > 0";

    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($category !== 'all') {
        $sql .= " AND b.category_id = ?";
        $params[] = $category;
    }

    // Add price filtering
    if ($price !== 'all') {
        switch ($price) {
            case '0-20':
                $sql .= " AND b.price <= 20";
                break;
            case '20-50':
                $sql .= " AND b.price BETWEEN 20 AND 50";
                break;
            case '50-100':
                $sql .= " AND b.price BETWEEN 50 AND 100";
                break;
            case '100+':
                $sql .= " AND b.price > 100";
                break;
        }
    }

    // Add sorting
    switch ($sort) {
        case 'price_low':
            $sql .= " ORDER BY b.price ASC";
            break;
        case 'price_high':
            $sql .= " ORDER BY b.price DESC";
            break;
        case 'newest':
            $sql .= " ORDER BY b.publication_date DESC";
            break;
        default:
            $sql .= " ORDER BY b.title ASC";
    }

    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }

    echo json_encode(['success' => true, 'books' => $books]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>