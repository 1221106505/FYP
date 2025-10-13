<?php
session_start();

// 检查用户是否登录且是管理员
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    // 如果不是管理员，重定向到主页
    header('Location: ../Main.html');
    exit();
}

// 如果一切正常，显示管理员面板
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Virtual BookStore</title>
    <style>
        /* 复制您之前的管理员面板CSS样式 */
        :root {
            --primary: #2c3e50;
            --secondary: #34495e;
            --accent: #3498db;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8f9fa;
            color: #333;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* 复制所有之前的管理员面板CSS样式 */
        .sidebar {
            width: 250px;
            background: var(--primary);
            color: white;
            padding: 20px 0;
        }

        .logo {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--secondary);
            margin-bottom: 20px;
        }

        .logo h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.2rem;
        }

        .nav-links {
            list-style: none;
        }

        .nav-links li {
            margin-bottom: 5px;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s;
        }

        .nav-links a:hover, .nav-links a.active {
            background: var(--secondary);
            color: white;
            border-left: 4px solid var(--accent);
        }

        .main-content {
            flex: 1;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: var(--primary);
            font-size: 1.5rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }

        .content-section {
            display: none;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .content-section.active {
            display: block;
        }

        /* 复制所有其他CSS样式... */
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="logo">
                <h2>📊 Admin Panel</h2>
            </div>
            <ul class="nav-links">
                <li><a href="#" class="nav-link active" data-section="dashboard"><i>📈</i> Dashboard</a></li>
                <li><a href="#" class="nav-link" data-section="books"><i>📚</i> Book Management</a></li>
                <li><a href="#" class="nav-link" data-section="orders"><i>🛒</i> Customer Orders</a></li>
                <li><a href="#" class="nav-link" data-section="analytics"><i>📊</i> Sales Analytics</a></li>
                <li><a href="#" class="nav-link" data-section="inventory"><i>📦</i> Inventory</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1 id="sectionTitle">Admin Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (Admin)</span>
                    <a href="logout.php" class="logout-btn">Logout</a>
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
                        <div class="stat-number" id="totalOrders">0</div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="totalRevenue">RM 0</div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="lowStock">0</div>
                        <div class="stat-label">Low Stock Items</div>
                    </div>
                </div>

                <!-- 其他面板内容保持不变 -->
            </section>

            <!-- 其他面板部分保持不变 -->
        </main>
    </div>

    <!-- Add/Edit Book Modal -->
    <div class="modal" id="bookModal">
        <!-- 模态框内容保持不变 -->
    </div>

    <script>
        // API endpoints - 使用正确的相对路径
        const API_ENDPOINTS = {
            books: '../api/get_books.php',
            bookDetail: '../api/get_book_detail.php',
            categories: '../api/get_categories.php',
            stock: '../api/get_stock_data.php'
        };

        // 复制之前的所有JavaScript功能
        // Navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                
                const sectionId = this.getAttribute('data-section');
                document.querySelectorAll('.content-section').forEach(section => {
                    section.classList.remove('active');
                });
                document.getElementById(sectionId).classList.add('active');
                
                document.getElementById('sectionTitle').textContent = this.textContent.trim();
                
                loadSectionData(sectionId);
            });
        });

        // 复制所有其他JavaScript函数...
        
        // 初始化
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Admin panel loaded');
            loadDashboardData();
        });
    </script>
</body>
</html>