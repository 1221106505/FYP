<?php
session_start();
require_once '../include/database.php';

// 检查用户权限
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: Login/Login.html");
    exit();
}

// 获取库存数据
function getStockData($search = '', $category = '') {
    global $pdo;
    
    $sql = "SELECT b.*, c.category_name 
            FROM books b 
            LEFT JOIN categories c ON b.category_id = c.category_id 
            WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($category) && $category !== 'all') {
        $sql .= " AND b.category_id = ?";
        $params[] = $category;
    }
    
    $sql .= " ORDER BY b.stock_quantity ASC, b.title ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// 获取分类列表
function getCategories() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY category_name");
    return $stmt->fetchAll();
}

// 处理库存更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_stock'])) {
        $book_id = $_POST['book_id'];
        $new_quantity = $_POST['new_quantity'];
        $reason = $_POST['reason'] ?? 'Manual adjustment';
        
        // 获取当前库存
        $stmt = $pdo->prepare("SELECT stock_quantity FROM books WHERE book_id = ?");
        $stmt->execute([$book_id]);
        $current_stock = $stmt->fetchColumn();
        
        // 计算库存变化
        $quantity_change = $new_quantity - $current_stock;
        
        // 更新库存
        $stmt = $pdo->prepare("UPDATE books SET stock_quantity = ? WHERE book_id = ?");
        $stmt->execute([$new_quantity, $book_id]);
        
        // 记录库存历史
        $change_type = $quantity_change > 0 ? 'IN' : ($quantity_change < 0 ? 'OUT' : 'ADJUST');
        $stmt = $pdo->prepare("INSERT INTO stock_history (book_id, change_type, quantity_change, previous_stock, new_stock, reason, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$book_id, $change_type, abs($quantity_change), $current_stock, $new_quantity, $reason, $_SESSION['user_id']]);
        
        $_SESSION['message'] = "Stock updated successfully!";
        header("Location: stock_management.php");
        exit();
    }
    
    if (isset($_POST['add_book'])) {
        $isbn = $_POST['isbn'];
        $title = $_POST['title'];
        $author = $_POST['author'];
        $category_id = $_POST['category_id'];
        $price = $_POST['price'];
        $stock_quantity = $_POST['stock_quantity'];
        $description = $_POST['description'];
        $publisher = $_POST['publisher'];
        $publish_date = $_POST['publish_date'];
        
        $stmt = $pdo->prepare("INSERT INTO books (isbn, title, author, category_id, price, stock_quantity, description, publisher, publish_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$isbn, $title, $author, $category_id, $price, $stock_quantity, $description, $publisher, $publish_date]);
        
        $_SESSION['message'] = "Book added successfully!";
        header("Location: stock_management.php");
        exit();
    }
}

// 获取搜索参数
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

// 获取数据
$books = getStockData($search, $category);
$categories = getCategories();
?>