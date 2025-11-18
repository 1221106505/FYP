<?php
session_start();

// 检查用户是否登录且是管理员
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../Main.html');
    exit();
}

$admin_name = $_SESSION['username'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Virtual BookStore</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="logo">
                <h2>🎯 Admin Panel</h2>
                <p style="color: #bdc3c7; font-size: 0.9em; margin-top: 5px;">Virtual BookStore</p>
            </div>
            <ul class="nav-links">
                <li><a href="#" class="nav-link active" data-section="dashboard"><i>📊</i> Dashboard</a></li>
                <li><a href="#" class="nav-link" data-section="books"><i>📚</i> Book Management</a></li>
                <li><a href="#" class="nav-link" data-section="inventory"><i>📦</i> Inventory</a></li>
                <li><a href="#" class="nav-link" data-section="categories"><i>🏷️</i> Categories</a></li>
                <li><a href="#" class="nav-link" data-section="analytics"><i>📈</i> Analytics</a></li>
                <li><a href="#" class="nav-link" data-section="settings"><i>⚙️</i> Settings</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1 id="sectionTitle">Admin Dashboard</h1>
                <div class="user-info">
                    <a href="../Main.html" class="back-btn">🏠 Back to Main</a>
                    <span id="adminWelcome">Welcome, <?php echo htmlspecialchars($admin_name); ?></span>
                    <a href="../logout.php" class="logout-btn">🚪 Logout</a>
                </div>
            </div>

            <!-- Dashboard Section -->
            <section id="dashboard" class="content-section active">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number" id="totalBooks">0</div>
                        <div class="stat-label">Total Books</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="totalCategories">0</div>
                        <div class="stat-label">Categories</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="lowStockCount">0</div>
                        <div class="stat-label">Low Stock Items</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="outOfStock">0</div>
                        <div class="stat-label">Out of Stock</div>
                    </div>
                </div>

                <div class="chart-container">
                    <h3>📈 Sales Overview</h3>
                    <div class="chart-placeholder">
                        📊 Sales analytics dashboard will be implemented here
                    </div>
                </div>

                <div class="section-header">
                    <h3>🚀 Quick Actions</h3>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <button class="btn btn-primary" onclick="loadSectionData('books')">
                        <i>📚</i> Manage Books
                    </button>
                    <button class="btn btn-success" onclick="openAddBookModal()">
                        <i>➕</i> Add New Book
                    </button>
                    <button class="btn btn-warning" onclick="loadSectionData('inventory')">
                        <i>📦</i> View Inventory
                    </button>
                    <button class="btn btn-primary" onclick="loadSectionData('categories')">
                        <i>🏷️</i> Manage Categories
                    </button>
                </div>
            </section>

            <!-- Book Management Section -->
            <section id="books" class="content-section">
                <div class="section-header">
                    <h2>📚 Book Management</h2>
                    <button class="btn btn-primary" onclick="openAddBookModal()">
                        <i>➕</i> Add New Book
                    </button>
                </div>

                <div class="search-box">
                    <input type="text" id="bookSearch" class="search-input" placeholder="🔍 Search books by title, author, or ISBN..." onkeyup="searchBooks()">
                    <select id="categoryFilter" class="form-control" style="width: 200px;" onchange="filterBooks()">
                        <option value="all">All Categories</option>
                    </select>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-warning" onclick="filterByStock('low')">⚠️ Low Stock</button>
                        <button class="btn btn-danger" onclick="filterByStock('out')">❌ Out of Stock</button>
                        <button class="btn btn-success" onclick="filterByStock('all')">📦 All Stock</button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Rating</th>
                                <th>Total Sales</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="booksTable">
                            <tr><td colspan="10" class="loading">Loading books data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Inventory Section -->
            <section id="inventory" class="content-section">
                <div class="section-header">
                    <h2>📦 Inventory Management</h2>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-warning" onclick="loadLowStockItems()">
                            <i>⚠️</i> Low Stock
                        </button>
                        <button class="btn btn-danger" onclick="loadOutOfStockItems()">
                            <i>❌</i> Out of Stock
                        </button>
                    </div>
                </div>

                <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                    <div class="stat-card" style="border-top-color: var(--success);">
                        <div class="stat-number" id="inStockCount">0</div>
                        <div class="stat-label">In Stock</div>
                    </div>
                    <div class="stat-card" style="border-top-color: var(--warning);">
                        <div class="stat-number" id="inventoryLowStock">0</div>
                        <div class="stat-label">Low Stock</div>
                    </div>
                    <div class="stat-card" style="border-top-color: var(--danger);">
                        <div class="stat-number" id="inventoryOutOfStock">0</div>
                        <div class="stat-label">Out of Stock</div>
                    </div>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Book ID</th>
                                <th>Title</th>
                                <th>Current Stock</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryTable">
                            <tr><td colspan="7" class="loading">Loading inventory data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Categories Section -->
            <section id="categories" class="content-section">
                <div class="section-header">
                    <h2>🏷️ Category Management</h2>
                    <button class="btn btn-primary" onclick="openAddCategoryModal()">
                        <i>➕</i> Add Category
                    </button>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category Name</th>
                                <th>Book Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="categoriesTable">
                            <tr><td colspan="4" class="loading">Loading categories...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Analytics Section -->
            <section id="analytics" class="content-section">
                <div class="section-header">
                    <h2>📈 Sales Analytics</h2>
                </div>
                
                <div class="chart-container">
                    <h3>📊 Sales Trend (Last 30 Days)</h3>
                    <div class="chart-placeholder">
                        Sales trend chart will be displayed here
                    </div>
                </div>

                <div class="chart-container">
                    <h3>🔥 Top Selling Books</h3>
                    <div class="chart-placeholder">
                        Top sellers chart will be displayed here
                    </div>
                </div>

                <div class="chart-container">
                    <h3>🏷️ Revenue by Category</h3>
                    <div class="chart-placeholder">
                        Category revenue chart will be displayed here
                    </div>
                </div>
            </section>

            <!-- Settings Section -->
            <section id="settings" class="content-section">
                <div class="section-header">
                    <h2>⚙️ System Settings</h2>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Store Name</label>
                        <input type="text" class="form-control" value="Virtual BookStore">
                    </div>
                    <div class="form-group">
                        <label>Admin Email</label>
                        <input type="email" class="form-control" value="admin@virtualbookstore.com">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Store Description</label>
                    <textarea class="form-control" rows="3">Your ultimate online book destination</textarea>
                </div>
                
                <button class="btn btn-primary">💾 Save Settings</button>
            </section>
        </main>
    </div>

    <!-- Add/Edit Book Modal -->
    <div class="modal" id="bookModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Book</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="bookForm">
                    <input type="hidden" id="bookId">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bookTitle">📖 Title *</label>
                            <input type="text" id="bookTitle" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="bookAuthor">✍️ Author *</label>
                            <input type="text" id="bookAuthor" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bookCategory">🏷️ Category *</label>
                            <select id="bookCategory" class="form-control" required>
                                <option value="">Select Category</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="bookPrice">💰 Price (RM) *</label>
                            <input type="number" id="bookPrice" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bookStock">📦 Stock Quantity *</label>
                            <input type="number" id="bookStock" class="form-control" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="bookISBN">🔢 ISBN</label>
                            <input type="text" id="bookISBN" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bookPublisher">🏢 Publisher</label>
                            <input type="text" id="bookPublisher" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="bookPublishDate">📅 Publish Date</label>
                            <input type="date" id="bookPublishDate" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="bookDescription">📝 Description</label>
                        <textarea id="bookDescription" class="form-control" rows="4" placeholder="Enter book description..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal()">❌ Cancel</button>
                <button class="btn btn-primary" onclick="saveBook()">💾 Save Book</button>
            </div>
        </div>
    </div>

    <!-- Stock Update Modal -->
    <div class="modal" id="stockModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📦 Update Stock</h3>
                <button class="close" onclick="closeStockModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="stockForm">
                    <input type="hidden" id="stockBookId">
                    <div class="form-group">
                        <label>📖 Book Title</label>
                        <input type="text" id="stockBookTitle" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="newStockQuantity">🔄 New Stock Quantity *</label>
                        <input type="number" id="newStockQuantity" class="form-control" min="0" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeStockModal()">❌ Cancel</button>
                <button class="btn btn-primary" onclick="updateStock()">💾 Update Stock</button>
            </div>
        </div>
    </div>

    <script src="js/admin.js"></script>
</body>
</html>