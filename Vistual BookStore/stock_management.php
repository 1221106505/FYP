<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock Management - Virtual BookStore</title>
    <link rel="stylesheet" href="Main.css">
    <style>
        /* 所有CSS样式保持不变 */
        .stock-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .stock-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .search-filters {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-box, .category-filter, .action-btn {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .action-btn {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .action-btn:hover {
            opacity: 0.9;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .stock-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            min-width: 800px;
        }
        
        .stock-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .stock-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .stock-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .stock-low {
            background-color: #fff5f5;
            color: #e53e3e;
            font-weight: bold;
        }
        
        .stock-medium {
            background-color: #fffaf0;
            color: #dd6b20;
        }
        
        .stock-good {
            background-color: #f0fff4;
            color: #38a169;
        }
        
        .stock-indicator {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-edit {
            background: #4299e1;
            color: white;
        }
        
        .btn-delete {
            background: #e53e3e;
            color: white;
        }
        
        .btn-view {
            background: #38a169;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #6a11cb;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="stock-container">
        <div class="stock-header">
            <h1>📚 Stock Management</h1>
            <div class="search-filters">
                <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" name="search" class="search-box" placeholder="Search books..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="category" class="category-filter">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>" <?php echo $category == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="action-btn">Search</button>
                </form>
                <button class="action-btn" onclick="openAddBookModal()">➕ Add New Book</button>
                <a href="Main.html" class="action-btn">🏠 Back to Main</a>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message success">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <?php
            $total_books = count($books);
            $low_stock = array_filter($books, function($book) { 
                return $book['stock_quantity'] < 10 && $book['stock_quantity'] > 0; 
            });
            $out_of_stock = array_filter($books, function($book) { 
                return $book['stock_quantity'] == 0; 
            });
            $in_stock = array_filter($books, function($book) { 
                return $book['stock_quantity'] >= 10; 
            });
            $total_value = array_sum(array_map(function($book) { 
                return $book['price'] * $book['stock_quantity']; 
            }, $books));
            ?>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_books; ?></div>
                <div class="stat-label">Total Books</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #38a169;"><?php echo count($in_stock); ?></div>
                <div class="stat-label">In Stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #dd6b20;"><?php echo count($low_stock); ?></div>
                <div class="stat-label">Low Stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #e53e3e;"><?php echo count($out_of_stock); ?></div>
                <div class="stat-label">Out of Stock</div>
            </div>
        </div>

        <!-- Stock Table -->
        <div class="table-responsive">
            <table class="stock-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($books)): ?>
                        <?php foreach ($books as $book): 
                            // 确定库存状态
                            if ($book['stock_quantity'] == 0) {
                                $stock_class = 'stock-low';
                                $status = 'Out of Stock';
                            } elseif ($book['stock_quantity'] < 10) {
                                $stock_class = 'stock-medium';
                                $status = 'Low Stock';
                            } else {
                                $stock_class = 'stock-good';
                                $status = 'In Stock';
                            }
                        ?>
                        <tr>
                            <td><?php echo $book['book_id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($book['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['category_name'] ?? 'Uncategorized'); ?></td>
                            <td>$<?php echo number_format($book['price'], 2); ?></td>
                            <td class="<?php echo $stock_class; ?>">
                                <?php echo $book['stock_quantity']; ?>
                            </td>
                            <td>
                                <span class="stock-indicator <?php echo $stock_class; ?>">
                                    <?php echo $status; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <!-- 方法1：使用反引号（推荐） -->
                                    <button class="btn btn-edit" onclick="openEditStockModal(<?php echo $book['book_id']; ?>, `<?php echo addslashes($book['title']); ?>`, <?php echo $book['stock_quantity']; ?>)">
                                        Edit Stock
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                <?php if ($search || $category): ?>
                                    No books found matching your search criteria.
                                <?php else: ?>
                                    No books found. <a href="javascript:void(0)" onclick="openAddBookModal()">Add the first book</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Stock Modal -->
    <div id="editStockModal" class="modal">
        <div class="modal-content">
            <h2>Update Stock</h2>
            <form method="POST">
                <input type="hidden" name="book_id" id="editBookId">
                <input type="hidden" name="update_stock" value="1">
                
                <div class="form-group">
                    <label id="editBookTitle">Book Title</label>
                </div>
                
                <div class="form-group">
                    <label for="new_quantity">New Stock Quantity</label>
                    <input type="number" id="new_quantity" name="new_quantity" class="form-control" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="reason">Reason for Change</label>
                    <input type="text" id="reason" name="reason" class="form-control" placeholder="e.g., New shipment, Customer return, etc.">
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="closeModal('editStockModal')" style="background: #6c757d;">Cancel</button>
                    <button type="submit" class="btn btn-edit">Update Stock</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Book Modal -->
    <div id="addBookModal" class="modal">
        <div class="modal-content">
            <h2>Add New Book</h2>
            <form method="POST">
                <input type="hidden" name="add_book" value="1">
                
                <div class="form-group">
                    <label for="isbn">ISBN</label>
                    <input type="text" id="isbn" name="isbn" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="author">Author</label>
                    <input type="text" id="author" name="author" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="price">Price</label>
                    <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="stock_quantity">Initial Stock</label>
                    <input type="number" id="stock_quantity" name="stock_quantity" class="form-control" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="publisher">Publisher</label>
                    <input type="text" id="publisher" name="publisher" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="publish_date">Publish Date</label>
                    <input type="date" id="publish_date" name="publish_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="closeModal('addBookModal')" style="background: #6c757d;">Cancel</button>
                    <button type="submit" class="btn btn-edit">Add Book</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditStockModal(bookId, bookTitle, currentStock) {
            console.log('Opening modal for book:', bookId, bookTitle, currentStock);
            document.getElementById('editBookId').value = bookId;
            document.getElementById('editBookTitle').textContent = bookTitle;
            document.getElementById('new_quantity').value = currentStock;
            document.getElementById('editStockModal').style.display = 'block';
        }
        
        function openAddBookModal() {
            document.getElementById('addBookModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // 点击模态框外部关闭
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>