 // ============ GLOBAL VARIABLES ============
    // API endpoints
    const API_ENDPOINTS = {
        books: 'get_book.php',
        bookDetail: 'get_book_detail.php',
        categories: 'get_categories.php',
        stock: 'get_stock_data.php',
        saveBook: 'add_new_book.php',
        deleteBook: 'delete_book.php',
        updateStock: 'update_book_stock.php',
        searchBook: 'search_book.php',
        checkDuplicate: 'check_duplicate_book.php',
        orders: 'getorder.php',
        orderDetail: 'get_single_order.php',
        updateOrder: 'update_order.php',
        salesOverview: 'sales_overview.php',
        salesAnalytics: 'sales_analytics.php',
        addCategory: 'add_category.php',
        updateCategory: 'update_category.php',
        deleteCategory: 'delete_category.php',
        // New Admin Management APIs
        getCurrentAdmin: 'get_current_admin.php',
        getAdmins: 'get_admins.php',
        getAdminDetail: 'get_admin_detail.php',
        saveAdmin: 'save_admin.php',
        updateAdmin: 'edit_admin.php',
        deleteAdmin: 'delete_admin.php',
        sendPasswordEmail: 'send_password_email.php',
        getPermissions: 'get_permissions.php',
        generatePDFReport: 'generate_report.php'
    };

    // Current state
    let currentBooks = [];
    let currentCategories = [];
    let currentOrders = [];
    let currentAdmins = [];
    let currentPermissions = [];
    let currentInventoryFilter = 'all';
    let currentOrderId = null;
    let currentGeneratedISBN = '';
    let lastSearchTimeout = null;
    let currentAdminInfo = null;
    let isSuperAdmin = false;
    
    // Permission system
    let currentAdminPermissions = [];
    let accessibleSections = [];

    // Order status mapping
    const ORDER_STATUS_MAP = {
        'confirmed': { text: '✅ Confirmed', class: 'status-confirmed' },
        'shipped': { text: '🚚 Shipped', class: 'status-shipped' },
        'delivered': { text: '📦 Delivered', class: 'status-delivered' },
        'cancelled': { text: '❌ Cancelled', class: 'status-cancelled' }
    };

    // Admin role mapping
    const ADMIN_ROLE_MAP = {
        'superadmin': { text: '👑 Super Admin', class: 'role-superadmin' },
        'admin': { text: '👤 Admin', class: 'role-admin' }
    };

    // Admin status mapping
    const ADMIN_STATUS_MAP = {
        'active': { text: '✅ Active', class: 'status-active' },
        'inactive': { text: '❌ Inactive', class: 'status-inactive' }
    };

    // ============ PERMISSION SYSTEM ============
    
    // 检查是否有特定权限
    function hasPermission(permissionKey) {
        // 超级管理员拥有所有权限
        if (isSuperAdmin) {
            return true;
        }
        
        // 检查权限列表
        return currentAdminPermissions.some(perm => perm.permission_key === permissionKey);
    }
    
    // 检查是否有任意指定权限
    function hasAnyPermission(permissionKeys) {
        if (isSuperAdmin) return true;
        return permissionKeys.some(key => hasPermission(key));
    }
    
    // 检查是否有所有指定权限
    function hasAllPermissions(permissionKeys) {
        if (isSuperAdmin) return true;
        return permissionKeys.every(key => hasPermission(key));
    }

    // 根据权限控制导航菜单显示
    function updateNavigationBasedOnPermissions() {
    console.log('Updating navigation for:', {
        sections: accessibleSections,
        isSuperAdmin: isSuperAdmin
    });
    
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        const section = link.getAttribute('data-section');
        const parentLi = link.parentElement;
        
        // 仪表板默认显示给所有人
        if (section === 'dashboard') {
            parentLi.style.display = 'list-item';
            return;
        }
        
        // 管理员管理只对超级管理员显示
        if (section === 'admin-management') {
            parentLi.style.display = isSuperAdmin ? 'list-item' : 'none';
            return;
        }
        
        // 其他页面根据可访问页面数组决定
        if (accessibleSections.includes(section)) {
            parentLi.style.display = 'list-item';
        } else {
            parentLi.style.display = 'none';
        }
    });
}

    // 根据权限控制仪表板快速操作按钮
    function updateDashboardQuickActions() {
        const actionsContainer = document.getElementById('quickActionsContainer');
        if (!actionsContainer) return;
        
        // 重新构建快速操作按钮
        const quickActions = [];
        
        // 书籍管理相关
        if (hasAnyPermission(['view_books', 'manage_books'])) {
            quickActions.push(`
                <button class="btn btn-primary" onclick="loadSectionData('book-inventory')">
                    <i>📚</i> Manage Books & Inventory
                </button>
            `);
        }
        
        if (hasPermission('manage_books')) {
            quickActions.push(`
                <button class="btn btn-success" onclick="openAddBookModal()">
                    <i>➕</i> Add New Book
                </button>
            `);
        }
        
        // 订单管理
        if (hasPermission('manage_orders')) {
            quickActions.push(`
                <button class="btn btn-info" onclick="loadSectionData('orders')">
                    <i>📦</i> View Orders
                </button>
            `);
        }
        
        // 销售报告
        if (hasPermission('view_analytics')) {
            quickActions.push(`
                <button class="btn btn-primary" onclick="loadSectionData('analytics')">
                    <i>📈</i> View Sales Report 
                </button>
            `);
        }
        
        // 管理员管理（仅超级管理员）
        if (isSuperAdmin) {
            quickActions.push(`
                <button id="adminManagementBtn" class="btn btn-warning" onclick="loadSectionData('admin-management')">
                    <i>👥</i> Manage Admins
                </button>
            `);
        }
        
        // 如果没有权限，显示消息
        if (quickActions.length === 0) {
            actionsContainer.innerHTML = `
                <div class="access-denied">
                    <i>🔒</i>
                    <h3>No Access Permissions</h3>
                    <p>You don't have permission to perform any actions.</p>
                    <p>Please contact your super administrator.</p>
                </div>
            `;
        } else {
            actionsContainer.innerHTML = quickActions.join('');
        }
    }

    // 根据权限控制书籍管理部分
    function updateBookManagementSection() {
        // 控制添加按钮
        const addBookBtn = document.getElementById('addBookBtn');
        if (addBookBtn) {
            addBookBtn.style.display = hasPermission('manage_books') ? 'inline-flex' : 'none';
        }
        
        // 控制表格操作按钮
        setTimeout(() => {
            document.querySelectorAll('#booksInventoryTable .action-buttons').forEach(actionsCell => {
                const editBtn = actionsCell.querySelector('.btn-primary');
                const stockBtn = actionsCell.querySelector('.btn-warning');
                const deleteBtn = actionsCell.querySelector('.btn-danger');
                
                if (editBtn) {
                    editBtn.style.display = hasPermission('manage_books') ? 'inline-flex' : 'none';
                }
                
                if (stockBtn) {
                    stockBtn.style.display = hasPermission('manage_inventory') ? 'inline-flex' : 'none';
                }
                
                if (deleteBtn) {
                    deleteBtn.style.display = hasPermission('manage_books') ? 'inline-flex' : 'none';
                }
                
                // 如果所有按钮都隐藏了，隐藏整个单元格
                const visibleButtons = actionsCell.querySelectorAll('.btn[style*="inline-flex"], .btn:not([style*="none"])');
                if (visibleButtons.length === 0) {
                    actionsCell.style.display = 'none';
                }
            });
        }, 500);
    }

    // 根据权限控制订单管理部分
    function updateOrderManagementSection() {
        // 控制表格操作按钮
        setTimeout(() => {
            document.querySelectorAll('#ordersTable .action-buttons').forEach(actionsCell => {
                const viewBtn = actionsCell.querySelector('.btn-primary');
                const updateBtn = actionsCell.querySelector('.btn-warning');
                
                if (viewBtn) {
                    viewBtn.style.display = hasPermission('manage_orders') ? 'inline-flex' : 'none';
                }
                
                if (updateBtn) {
                    updateBtn.style.display = hasPermission('manage_orders') ? 'inline-flex' : 'none';
                }
                
                // 如果所有按钮都隐藏了，隐藏整个单元格
                const visibleButtons = actionsCell.querySelectorAll('.btn[style*="inline-flex"], .btn:not([style*="none"])');
                if (visibleButtons.length === 0) {
                    actionsCell.style.display = 'none';
                }
            });
        }, 500);
    }

    // 根据权限控制分类管理部分
    function updateCategoryManagementSection() {
        const addCategoryBtn = document.getElementById('addCategoryBtn');
        if (addCategoryBtn) {
            addCategoryBtn.style.display = hasPermission('manage_categories') ? 'inline-flex' : 'none';
        }
        
        // 控制表格操作按钮
        setTimeout(() => {
            document.querySelectorAll('#categoriesTable .action-buttons').forEach(actionsCell => {
                const editBtn = actionsCell.querySelector('.btn-warning');
                const deleteBtn = actionsCell.querySelector('.btn-danger');
                
                if (editBtn) {
                    editBtn.style.display = hasPermission('manage_categories') ? 'inline-flex' : 'none';
                }
                
                if (deleteBtn) {
                    deleteBtn.style.display = hasPermission('manage_categories') ? 'inline-flex' : 'none';
                }
                
                // 如果所有按钮都隐藏了，隐藏整个单元格
                const visibleButtons = actionsCell.querySelectorAll('.btn[style*="inline-flex"], .btn:not([style*="none"])');
                if (visibleButtons.length === 0) {
                    actionsCell.style.display = 'none';
                }
            });
        }, 500);
    }

    // 应用特定页面的权限控制
    function applySectionPermissions(sectionId) {
        switch(sectionId) {
            case 'dashboard':
                // 仪表板权限已由updateDashboardQuickActions处理
                break;
            case 'book-inventory':
                updateBookManagementSection();
                break;
            case 'orders':
                updateOrderManagementSection();
                break;
            case 'categories':
                updateCategoryManagementSection();
                break;
            case 'analytics':
                // 如果没有查看报告的权限，显示提示
                if (!hasPermission('view_analytics') && !isSuperAdmin) {
                    document.querySelector('#analytics').innerHTML = `
                        <div class="access-denied">
                            <i>🔒</i>
                            <h3>Access Denied</h3>
                            <p>You don't have permission to view analytics reports.</p>
                            <p>Please contact your super administrator for access.</p>
                        </div>
                    `;
                }
                break;
            case 'admin-management':
                // 管理员管理部分只对超级管理员开放
                if (!isSuperAdmin) {
                    document.querySelector('#admin-management').innerHTML = `
                        <div class="access-denied">
                            <i>🔒</i>
                            <h3>Access Denied</h3>
                            <p>Admin management is only available for super administrators.</p>
                        </div>
                    `;
                }
                break;
        }
    }
function hasPermission(permissionKey) {
    console.log('Checking permission:', permissionKey, {
        isSuperAdmin: isSuperAdmin,
        currentPermissions: currentAdminPermissions
    });
    
    // 超级管理员拥有所有权限
    if (isSuperAdmin) {
        console.log('Super admin has all permissions');
        return true;
    }
    
    // 如果权限数据为空，使用fallback
    if (!currentAdminPermissions || currentAdminPermissions.length === 0) {
        console.warn('No permissions loaded, using fallback');
        return hasFallbackPermission(permissionKey);
    }
    
    // 检查权限列表
    const hasPerm = currentAdminPermissions.some(perm => {
        const match = perm.permission_key === permissionKey;
        if (match) console.log('Permission found:', perm);
        return match;
    });
    
    console.log('Permission result:', hasPerm);
    return hasPerm;
}

// 添加fallback权限检查（当权限数据加载失败时使用）
function hasFallbackPermission(permissionKey) {
    // 默认所有管理员都有这些基本权限
    const basicPermissions = ['view_dashboard', 'view_books', 'view_orders', 'view_analytics'];
    return basicPermissions.includes(permissionKey);
}

// 初始化权限系统
// 修复权限系统初始化
async function initializePermissionSystem() {
    try {
        const response = await fetch(API_ENDPOINTS.getCurrentAdmin);
        const data = await response.json();
        
        if (data.success) {
            currentAdminInfo = data;
            isSuperAdmin = data.role === 'superadmin';
            
            console.log('Admin info loaded:', {
                username: data.username,
                role: data.role,
                isSuperAdmin: isSuperAdmin
            });
            
            updateAdminDisplay();
            
            // 加载权限数据（非超级管理员才需要）
            if (!isSuperAdmin) {
                await loadAdminPermissions();
            } else {
                currentAdminPermissions = [];
                accessibleSections = ['dashboard', 'book-inventory', 'orders', 'categories', 'analytics', 'admin-management'];
            }
            
            // 更新界面
            updateNavigationBasedOnPermissions();
            updateDashboardQuickActions();
            
            // 应用当前页面的权限控制
            const activeSection = document.querySelector('.content-section.active');
            if (activeSection) {
                const sectionId = activeSection.id;
                applySectionPermissions(sectionId);
            }
            
        } else {
            console.error('Failed to load admin info:', data.error);
            fallbackToDefault('Failed to load admin info');
        }
    } catch (error) {
        console.error('Error initializing permission system:', error);
        fallbackToDefault('Network error: ' + error.message);
    }
}

// 添加权限加载函数
async function loadAdminPermissions() {
    try {
        // 假设你有获取权限的API，或者修改 get_current_admin.php 返回权限
        const response = await fetch('get_admin_permissions.php');
        const data = await response.json();
        
        if (data.success) {
            currentAdminPermissions = data.permissions || [];
            accessibleSections = data.accessible_sections || [];
            
            console.log('Admin permissions loaded:', {
                permissions: currentAdminPermissions,
                accessibleSections: accessibleSections
            });
        } else {
            console.warn('Could not load permissions:', data.error);
            currentAdminPermissions = [];
            accessibleSections = ['dashboard']; // 默认只有仪表板
        }
    } catch (error) {
        console.error('Error loading permissions:', error);
        currentAdminPermissions = [];
        accessibleSections = ['dashboard'];
    }
}

    // ============ INITIALIZATION ============
    
    document.addEventListener('DOMContentLoaded', async function() {
    console.log('Admin panel initialized');
    
    // Check current admin role
    await checkAdminRole();
    
    // 初始化权限系统
    await initializePermissionSystem();
    
    // Load initial data
    loadDashboardData();
    
    // 加载客户下拉列表
    await loadCustomerDropdown();
    
    // 初始化销售报告
    if (document.getElementById('analytics').classList.contains('active')) {
        loadSalesAnalytics();
    }
    
    // Update clock
    updateClock();
    setInterval(updateClock, 60000);
    
    // Set up navigation
    setupNavigation();
    
    // Set up permissions listeners
    setupPermissionsListeners();
});
    async function checkAdminRole() {
        console.log('=== CHECKING ADMIN ROLE ===');
        
        try {
            console.log('Making API request to:', API_ENDPOINTS.getCurrentAdmin);
            const response = await fetch(API_ENDPOINTS.getCurrentAdmin);
            console.log('Response status:', response.status, response.statusText);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('Parsed JSON data:', data);
            
            if (data.success) {
                console.log('✅ API returned success');
                console.log('Admin data received:', {
                    username: data.username,
                    role: data.role,
                    admin_id: data.admin_id
                });
                
                currentAdminInfo = data;
                isSuperAdmin = data.role === 'superadmin';
                
                console.log('isSuperAdmin determined:', isSuperAdmin, 'based on role:', data.role);
                
                updateAdminDisplay();
                
                // 如果API返回了欢迎消息，也显示
                if (data.welcome_message) {
                    showNotification(data.welcome_message, 'info');
                }
            } else {
                console.error('❌ API returned error:', data.error);
                
                // 检查是否是未登录错误
                if (data.error && data.error.includes('Not logged in')) {
                    console.warn('User not logged in via session');
                    // 显示登录提示
                    showNotification('Please login first', 'warning');
                    // 可以重定向到登录页面
                    // setTimeout(() => window.location.href = 'admin_login.html', 2000);
                }
                
                // 使用默认值继续
                fallbackToDefault('API error: ' + data.error);
            }
        } catch (error) {
            console.error('❌ Network/fetch error:', error);
            console.error('Error details:', error.message);
            fallbackToDefault('Network error: ' + error.message);
        }
    }

    function fallbackToDefault(reason) {
        console.log('Using fallback admin info. Reason:', reason);
        currentAdminInfo = { 
            username: 'Administrator', 
            role: 'admin' 
        };
        isSuperAdmin = false;
        updateAdminDisplay();
    }

    function updateAdminDisplay() {
        // 更新欢迎消息
        updateWelcomeMessage();
        
        // 控制管理员管理功能的显示
        toggleAdminManagement();
    }

    function updateWelcomeMessage() {
        const welcomeElement = document.getElementById('adminWelcome');
        
        if (!currentAdminInfo) {
            welcomeElement.innerHTML = 'Welcome, <strong>Admin</strong>';
            return;
        }
        
        // 根据角色创建徽章
        let roleBadge = '';
        if (currentAdminInfo.role === 'superadmin') {
            roleBadge = '<span class="role-badge superadmin-badge">👑 Super Admin</span>';
        } else {
            roleBadge = '<span class="role-badge admin-badge">👤 Admin</span>';
        }
        
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit'
        });
        const dateString = now.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric'
        });
        
        welcomeElement.innerHTML = `
            Welcome, <strong>${currentAdminInfo.username}</strong>
            ${roleBadge}
            <span class="time-info">• ${dateString} ${timeString}</span>
        `;
    }

    function toggleAdminManagement() {
        const superAdminElements = [
            'adminManagementLink',
            'adminManagementBtn',
            'addAdminBtn'
        ];
        
        superAdminElements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                if (isSuperAdmin) {
                    element.style.display = 'inline-flex';
                } else {
                    element.style.display = 'none';
                }
            }
        });
        
        // 特别处理导航链接的显示方式
        const adminManagementLink = document.getElementById('adminManagementLink');
        if (adminManagementLink) {
            adminManagementLink.style.display = isSuperAdmin ? 'list-item' : 'none';
        }
    }

    function updateClock() {
        if (!currentAdminInfo) return;
        
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit'
            
        });
        const dateString = now.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric'
        });
        
        const welcomeElement = document.getElementById('adminWelcome');
        
        let roleBadge = '';
        if (currentAdminInfo.role === 'superadmin') {
            roleBadge = '<span class="role-badge superadmin-badge">👑 Super Admin</span>';
        } else {
            roleBadge = '<span class="role-badge admin-badge">👤 Admin</span>';
        }
        
        welcomeElement.innerHTML = `
            Welcome, <strong>${currentAdminInfo.username}</strong>
            ${roleBadge}
            <span class="time-info">• ${dateString} ${timeString}</span>
        `;
    }

    function setupNavigation() {
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // 如果点击的是管理员管理，但不是超级管理员，阻止访问
                if (this.getAttribute('data-section') === 'admin-management' && !isSuperAdmin) {
                    showNotification('Access denied. Super Admin only.', 'error');
                    return;
                }
                
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                
                const sectionId = this.getAttribute('data-section');
                document.querySelectorAll('.content-section').forEach(section => {
                    section.classList.remove('active');
                });
                document.getElementById(sectionId).classList.add('active');
                
                const sectionTitles = {
                    'dashboard': 'Dashboard',
                    'book-inventory': 'Book & Inventory Management',
                    'orders': 'Order Management',
                    'categories': 'Category Management',
                    'analytics': 'Sales Report Section',
                    'admin-management': 'Admin Management',
                    'settings': 'System Settings'
                };
                document.getElementById('sectionTitle').textContent = sectionTitles[sectionId];
                
                loadSectionData(sectionId);
            });
        });
    }

    function loadSectionData(sectionId) {
    switch(sectionId) {
        case 'dashboard':
            // 只需调用一次，它会处理所有数据
            loadDashboardData();
            break;
        case 'book-inventory':
            loadBookInventoryData();
            break;
        case 'orders':
            loadOrdersData();
            break;
        case 'analytics':
            loadSalesAnalytics();
            break;
        case 'categories':
            loadCategoriesData();
            break;
        case 'admin-management':
            if (isSuperAdmin) {
                loadAdminManagementData();
            } else {
                showNotification('Access denied. Super Admin only.', 'error');
                document.querySelector('.nav-link[data-section="dashboard"]').click();
            }
            break;
    }
}

    // ============ DASHBOARD FUNCTIONS ============
    
    // ============ DASHBOARD FUNCTIONS ============
async function loadDashboardData() {
    try {
        console.log('Loading dashboard data from:', API_ENDPOINTS.salesOverview);
        const response = await fetch(API_ENDPOINTS.salesOverview);
        const result = await response.json();
        
        console.log('Dashboard API response:', result);
        
        if (result.success) {
            // 注意：数据在 result.data 中，不是 result 本身
            updateDashboardStats(result.data);
            updateSalesOverviewChart(result.data);
        } else {
            console.error('Error loading dashboard data:', result.error);
            showNotification('Error loading dashboard data: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error loading dashboard data:', error);
        showNotification('Error loading dashboard data', 'error');
    }
}

function updateDashboardStats(data) {
    console.log('Updating dashboard stats with data:', data);
    
    // 1. 更新今日收入
    const todayRevenue = document.getElementById('todayRevenue');
    if (todayRevenue) {
        // 使用 revenue_formatted 已经有 RM 格式
        const revenueText = data.today?.revenue_formatted || 'RM 0.00';
        console.log('Setting todayRevenue to:', revenueText);
        todayRevenue.textContent = revenueText;
        
        // 同时设置原始值到 data-raw 属性，方便其他地方使用
        todayRevenue.setAttribute('data-raw', data.today?.revenue || 0);
    } else {
        console.error('Element #todayRevenue not found!');
    }
    
    // 2. 更新库存数据
    if (data.inventory) {
        const totalBooks = document.getElementById('totalBooks');
        const lowStockCount = document.getElementById('lowStockCount');
        const outOfStock = document.getElementById('outOfStock');
        
        if (totalBooks) totalBooks.textContent = data.inventory.total_books || 0;
        if (lowStockCount) lowStockCount.textContent = data.inventory.low_stock || 0;
        if (outOfStock) outOfStock.textContent = data.inventory.out_of_stock || 0;
    }
    
    // 3. 更新总订单数
    const totalOrders = document.getElementById('totalOrders');
    if (totalOrders) {
        // 使用 total.orders（历史总订单数）
        const totalOrdersValue = data.total?.orders || 0;
        totalOrders.textContent = totalOrdersValue;
    }
    
    // 4. 更新类别总数
    loadCategoriesCount();
    
    // 5. 更新总客户数
    const totalCustomers = document.getElementById('totalCustomers');
    if (totalCustomers) {
        totalCustomers.textContent = data.total?.customers || data.active_customers?.total || 0;
    }
    
    // 6. 更新增长信息（可选）
    const revenueGrowth = document.getElementById('revenueGrowth');
    if (revenueGrowth && data.growth_rates) {
        const growth = data.growth_rates.revenue_growth || 0;
        let growthHTML = '';
        
        if (growth > 0) {
            growthHTML = `<span style="color: #27ae60;">↑ ${growth.toFixed(1)}%</span>`;
        } else if (growth < 0) {
            growthHTML = `<span style="color: #e74c3c;">↓ ${Math.abs(growth).toFixed(1)}%</span>`;
        } else {
            growthHTML = '<span style="color: #666;">0%</span>';
        }
        
        revenueGrowth.innerHTML = growthHTML;
    }
}

async function loadCategoriesCount() {
    try {
        const response = await fetch(API_ENDPOINTS.categories);
        const data = await response.json();
        
        if (data.success) {
            const categories = data.categories || data.data || [];
            document.getElementById('totalCategories').textContent = categories.length;
        }
    } catch (error) {
        console.error('Error loading categories count:', error);
        document.getElementById('totalCategories').textContent = '0';
    }
}

    // ============ ORDER FUNCTIONS ============
    
    async function loadOrdersData() {
        try {
            const response = await fetch(API_ENDPOINTS.orders);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                currentOrders = data.orders || data.data || [];
                updateOrderStats(currentOrders);
                displayOrders(currentOrders);
                updateOrderManagementSection();
            } else {
                console.error('Error loading orders:', data.error);
                document.getElementById('ordersTable').innerHTML = 
                    '<tr><td colspan="7" class="error">Error loading orders: ' + (data.error || 'Unknown error') + '</td></tr>';
            }
        } catch (error) {
            console.error('Error loading orders:', error);
            document.getElementById('ordersTable').innerHTML = 
                '<tr><td colspan="7" class="error">Error loading orders. Please check the console.</td></tr>';
        }
    }

    function updateOrderStats(orders) {
        const confirmed = orders.filter(order => order.status === 'confirmed').length;
        const shipped = orders.filter(order => order.status === 'shipped').length;
        const delivered = orders.filter(order => order.status === 'delivered').length;
        const cancelled = orders.filter(order => order.status === 'cancelled').length;
        
        document.getElementById('confirmedOrders').textContent = confirmed;
        document.getElementById('shippedOrders').textContent = shipped;
        document.getElementById('deliveredOrders').textContent = delivered;
        document.getElementById('cancelledOrders').textContent = cancelled;
    }

    function displayOrders(orders) {
        const ordersTable = document.getElementById('ordersTable');
        
        if (!orders || orders.length === 0) {
            ordersTable.innerHTML = '<tr><td colspan="7" class="loading">No orders found</td></tr>';
            return;
        }

        ordersTable.innerHTML = orders.map(order => {
            let status = order.status;
            if (status === 'pending') {
                status = 'confirmed';
            }
            
            const statusInfo = ORDER_STATUS_MAP[status] || { text: status, class: 'status-confirmed' };
            const customerName = order.recipient_name || order.customer_name || `Customer ${order.customer_id || 'N/A'}`;
            const contactEmail = order.contact_email || order.email || 'No email';
            
            return `
            <tr>
                <td><strong>#${order.order_id || order.id || 'N/A'}</strong></td>
                <td>
                    <div><strong>${customerName}</strong></div>
                    <div style="font-size: 0.8em; color: #666;">${contactEmail}</div>
                </td>
                <td>${order.order_date ? new Date(order.order_date).toLocaleDateString() : 'N/A'}</td>
                <td>
                    <div>${order.item_count || order.total_items || 0} items</div>
                    <div style="font-size: 0.8em; color: #666;">${order.total_quantity || 0} total</div>
                </td>
                <td><strong>RM ${parseFloat(order.total_amount || order.total_price || 0).toFixed(2)}</strong></td>
                <td>
                    <span class="order-status ${statusInfo.class}">
                        ${statusInfo.text}
                    </span>
                </td>
                <td class="action-buttons">
                    <button class="btn btn-sm btn-primary" onclick="viewOrderDetails(${order.order_id || order.id})">
                        👁️ View
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="openUpdateOrderStatusModal(${order.order_id || order.id}, '${status}')">
                        ✏️ Update
                    </button>
                </td>
            </tr>
            `;
        }).join('');
    }

    function filterOrders() {
        const statusFilter = document.getElementById('orderStatusFilter').value;
        
        let filteredOrders = currentOrders || [];
        
        if (statusFilter !== 'all') {
            filteredOrders = filteredOrders.filter(order => {
                let status = order.status;
                if (status === 'pending') {
                    status = 'confirmed';
                }
                return status === statusFilter;
            });
        }
        
        displayOrders(filteredOrders);
        updateOrderManagementSection();
    }

    function searchOrders() {
        if (lastSearchTimeout) {
            clearTimeout(lastSearchTimeout);
        }
        
        lastSearchTimeout = setTimeout(function() {
            const searchTerm = document.getElementById('orderSearch').value.toLowerCase();
            
            if (searchTerm.length < 2) {
                displayOrders(currentOrders);
                updateOrderManagementSection();
                return;
            }
            
            const filteredOrders = (currentOrders || []).filter(order => {
                const orderId = (order.order_id || order.id || '').toString();
                const customerName = (order.recipient_name || order.recipient_name|| '').toLowerCase();
                const contactEmail = (order.contact_email || order.email || '').toLowerCase();
                
                return orderId.includes(searchTerm) ||
                       customerName.includes(searchTerm) ||
                       contactEmail.includes(searchTerm);
            });
            
            displayOrders(filteredOrders);
            updateOrderManagementSection();
        }, 300);
    }

    function refreshOrders() {
        loadOrdersData();
    }

    async function viewOrderDetails(orderId) {
        try {
            const response = await fetch(`${API_ENDPOINTS.orderDetail}?order_id=${orderId}`);
            const data = await response.json();
            
            if (data.success) {
                showOrderDetailsModal(data.order || data.data);
            } else {
                showNotification('Error loading order details: ' + (data.error || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error('Error loading order details:', error);
            showNotification('Error loading order details', 'error');
        }
    }

    function showOrderDetailsModal(order) {
        if (!order) {
            showNotification('No order data available', 'error');
            return;
        }
        
        currentOrderId = order.order_id || order.id;
        
        document.getElementById('orderModalTitle').textContent = `Order #${currentOrderId} Details`;
        
        const itemsHtml = order.items && order.items.length > 0 ? order.items.map(item => `
            <div class="order-item" style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin-bottom: 8px;">
                <div style="font-weight: 600; margin-bottom: 5px;">${item.title || item.name || 'Unknown Book'}</div>
                <div style="display: flex; gap: 15px; font-size: 0.9em; color: #666;">
                    <span>Qty: ${item.quantity || 0}</span>
                    <span>Price: RM ${parseFloat(item.unit_price || item.price || 0).toFixed(2)}</span>
                    <span>Subtotal: RM ${parseFloat(item.subtotal || (item.quantity * (item.unit_price || item.price) || 0)).toFixed(2)}</span>
                </div>
            </div>
        `).join('') : '<div style="color: #666; font-style: italic;">No items found</div>';
        
        let displayStatus = order.status;
        if (displayStatus === 'pending') {
            displayStatus = 'confirmed';
        }
        
        document.getElementById('orderModalBody').innerHTML = `
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4 style="color: var(--primary); margin-bottom: 10px;">Customer Information</h4>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                        <div style="margin-bottom: 8px;"><strong>Customer ID:</strong> ${order.customer_id || 'N/A'}</div>
                        <div style="margin-bottom: 8px;"><strong>Name:</strong> ${order.recipient_name || order.recipient_name|| 'N/A'}</div>
                        <div style="margin-bottom: 8px;"><strong>Email:</strong> ${order.contact_email || order.email || 'N/A'}</div>
                        <div style="margin-bottom: 8px;"><strong>Phone:</strong> ${order.contact_phone || order.phone || 'N/A'}</div>
                    </div>
                </div>
                
                <div>
                    <h4 style="color: var(--primary); margin-bottom: 10px;">Order Information</h4>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                        <div style="margin-bottom: 8px;"><strong>Order Date:</strong> ${order.order_date ? new Date(order.order_date).toLocaleString() : 'N/A'}</div>
                        <div style="margin-bottom: 8px;">
                            <strong>Status:</strong> 
                            <span class="order-status ${ORDER_STATUS_MAP[displayStatus]?.class || 'status-confirmed'}">
                                ${ORDER_STATUS_MAP[displayStatus]?.text || displayStatus || 'Confirmed'}
                            </span>
                        </div>
                        <div style="margin-bottom: 8px;"><strong>Total Amount:</strong> RM ${parseFloat(order.total_amount || order.total_price || 0).toFixed(2)}</div>
                    </div>
                </div>
                
                <div style="grid-column: 1 / -1;">
                    <h4 style="color: var(--primary); margin-bottom: 10px;">Shipping Address</h4>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; white-space: pre-line;">
                        ${order.shipping_address || order.address || 'No shipping address provided'}
                    </div>
                </div>
                
                <div style="grid-column: 1 / -1;">
                    <h4 style="color: var(--primary); margin-bottom: 10px;">Order Items (${order.items ? order.items.length : 0})</h4>
                    <div style="max-height: 200px; overflow-y: auto;">
                        ${itemsHtml}
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('orderModalFooter').innerHTML = `
            <button class="btn" onclick="closeOrderDetailsModal()">Close</button>
            <button class="btn btn-warning" onclick="openUpdateOrderStatusModal(${currentOrderId}, '${displayStatus}')">Update Status</button>
        `;
        
        document.getElementById('orderDetailsModal').style.display = 'flex';
    }

    function closeOrderDetailsModal() {
        document.getElementById('orderDetailsModal').style.display = 'none';
        currentOrderId = null;
    }

    function openUpdateOrderStatusModal(orderId, currentStatus) {
        currentOrderId = orderId;
        
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.id = 'updateStatusModal';
        modal.style.display = 'flex';
        
        let displayStatus = currentStatus;
        if (displayStatus === 'pending') {
            displayStatus = 'confirmed';
        }
        
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header">
                    <h3>Update Order Status</h3>
                    <button class="close" onclick="closeUpdateStatusModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="newOrderStatus">Select New Status:</label>
                        <select id="newOrderStatus" class="form-control">
                            <option value="confirmed" ${displayStatus === 'confirmed' ? 'selected' : ''}>✅ Confirmed</option>
                            <option value="shipped" ${displayStatus === 'shipped' ? 'selected' : ''}>🚚 Shipped</option>
                            <option value="delivered" ${displayStatus === 'delivered' ? 'selected' : ''}>📦 Delivered</option>
                            <option value="cancelled" ${displayStatus === 'cancelled' ? 'selected' : ''}>❌ Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn" onclick="closeUpdateStatusModal()">Cancel</button>
                    <button class="btn btn-primary" onclick="updateOrderStatus()">Update Status</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }

    function closeUpdateStatusModal() {
        const modal = document.getElementById('updateStatusModal');
        if (modal) modal.remove();
        currentOrderId = null;
    }

    async function updateOrderStatus() {
        if (!currentOrderId) return;
        
        const newStatus = document.getElementById('newOrderStatus').value;
        
        if (!newStatus) {
            showNotification('Please select a status', 'warning');
            return;
        }
        
        try {
            const response = await fetch(API_ENDPOINTS.updateOrder, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_id: currentOrderId,
                    status: newStatus
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Order status updated successfully!', 'success');
                closeUpdateStatusModal();
                closeOrderDetailsModal();
                loadOrdersData();
            } else {
                showNotification('Error updating order status: ' + result.error, 'error');
            }
        } catch (error) {
            console.error('Error updating order status:', error);
            showNotification('Error updating order status', 'error');
        }
    }

    // ============ BOOK FUNCTIONS ============
    
    async function loadBookInventoryData() {
        try {
            const [booksResponse, categoriesResponse] = await Promise.all([
                fetch(API_ENDPOINTS.books),
                fetch(API_ENDPOINTS.categories)
            ]);

            const booksData = await booksResponse.json();
            const categoriesData = await categoriesResponse.json();

            if (booksData.success) {
                currentBooks = booksData.books || booksData.data || [];
                updateBookInventoryStats(currentBooks);
                displayBookInventory(currentBooks);
                document.getElementById('totalBookCount').textContent = currentBooks.length;
                updateBookManagementSection();
            }

            if (categoriesData.success) {
                currentCategories = categoriesData.categories || categoriesData.data || [];
                loadCategoryFilter();
            }
        } catch (error) {
            console.error('Error loading book inventory:', error);
            document.getElementById('booksInventoryTable').innerHTML = '<tr><td colspan="10" class="error">Error loading data</td></tr>';
        }
    }

    function updateBookInventoryStats(books) {
        const inStockCount = books.filter(book => {
            const stock = parseInt(book.stock_quantity);
            return stock > 10;
        }).length;
        
        const lowStockCount = books.filter(book => {
            const stock = parseInt(book.stock_quantity);
            return stock >= 1 && stock <= 10;
        }).length;
        
        const outOfStockCount = books.filter(book => {
            const stock = parseInt(book.stock_quantity);
            return stock === 0;
        }).length;
        
        document.getElementById('inStockCount').textContent = inStockCount;
        document.getElementById('inventoryLowStock').textContent = lowStockCount;
        document.getElementById('inventoryOutOfStock').textContent = outOfStockCount;
    }

    function displayBookInventory(books) {
        const tableBody = document.getElementById('booksInventoryTable');
        
        if (books.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="10" class="loading">No books found</td></tr>';
            return;
        }

        tableBody.innerHTML = books.map(book => {
            const stockStatusClass = getStockStatusClass(book.stock_quantity);
            const stockStatusText = getStockStatusText(book.stock_quantity);
            
            return `
            <tr>
                <td>${book.book_id}</td>
                <td><strong>${book.title}</strong></td>
                <td>${book.author}</td>
                <td>${book.category_name || 'N/A'}</td>
                <td>RM ${parseFloat(book.price).toFixed(2)}</td>
                <td>${book.stock_quantity}</td>
                <td style="font-family: 'Courier New', monospace;">
                    ${book.isbn || '<span style="color:#999;font-style:italic">Auto-generated</span>'}
                </td>
                <td>
                    <span class="${stockStatusClass}">
                        ${stockStatusText}
                    </span>
                </td>
                <td>${book.updated_at || 'N/A'}</td>
                <td class="action-buttons">
                    <button class="btn btn-sm btn-primary" onclick="openEditBookModal(${book.book_id})">✏️ Edit</button>
                    <button class="btn btn-sm btn-warning" onclick="openStockModal(${book.book_id}, '${book.title.replace(/'/g, "\\'")}', ${book.stock_quantity})">📦 Stock</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteBook(${book.book_id})">🗑️ Delete</button>
                </td>
            </tr>
            `;
        }).join('');
    }

    function getStockStatusClass(stock) {
        const stockNum = parseInt(stock);
        
        if (stockNum === 0) {
            return 'out-of-stock';
        }
        if (stockNum >= 1 && stockNum <= 10) {
            return 'low-stock';
        }
        return 'in-stock';
    }

    function getStockStatusText(stock) {
        const stockNum = parseInt(stock);
        
        if (stockNum === 0) {
            return 'Out of Stock';
        }
        if (stockNum >= 1 && stockNum <= 10) {
            return 'Low Stock';
        }
        return 'In Stock';
    }

    async function loadCategoryFilter() {
        try {
            const response = await fetch(API_ENDPOINTS.categories);
            const data = await response.json();
            
            const categoryFilter = document.getElementById('categoryFilter');
            categoryFilter.innerHTML = '<option value="all">All Categories</option>';
            
            if (data.success) {
                currentCategories = data.categories || data.data || [];
                currentCategories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.category_id;
                    option.textContent = category.category_name;
                    categoryFilter.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading categories:', error);
        }
    }

    function searchBooks() {
        if (lastSearchTimeout) {
            clearTimeout(lastSearchTimeout);
        }
        
        lastSearchTimeout = setTimeout(async function() {
            const searchTerm = document.getElementById('bookSearch').value.trim().toLowerCase();
            
            if (searchTerm.length < 2) {
                displayBookInventory(currentBooks);
                updateBookManagementSection();
                return;
            }
            
            try {
                const response = await fetch(`${API_ENDPOINTS.searchBook}?search=${encodeURIComponent(searchTerm)}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    const searchResults = data.books || data.data || [];
                    displayBookInventory(searchResults);
                    updateBookManagementSection();
                } else {
                    localSearchBooks(searchTerm);
                }
            } catch (error) {
                console.error('Error searching books via API:', error);
                localSearchBooks(searchTerm);
            }
        }, 300);
    }

    function localSearchBooks(searchTerm) {
        const filteredBooks = currentBooks.filter(book => {
            const titleMatch = book.title && book.title.toLowerCase().includes(searchTerm);
            const authorMatch = book.author && book.author.toLowerCase().includes(searchTerm);
            const isbnMatch = book.isbn && book.isbn.toLowerCase().includes(searchTerm);
            const categoryMatch = book.category_name && book.category_name.toLowerCase().includes(searchTerm);
            
            return titleMatch || authorMatch || isbnMatch || categoryMatch;
        });
        
        displayBookInventory(filteredBooks);
        updateBookManagementSection();
    }

    function filterBooks() {
        const categoryId = document.getElementById('categoryFilter').value;
        if (categoryId === 'all') {
            displayBookInventory(currentBooks);
            updateBookManagementSection();
        } else {
            const filteredBooks = currentBooks.filter(book => book.category_id == categoryId);
            displayBookInventory(filteredBooks);
            updateBookManagementSection();
        }
    }

    function filterByStock() {
        const stockFilter = document.getElementById('stockFilter').value;
        
        let filteredBooks = currentBooks || [];
        
        switch(stockFilter) {
            case 'in_stock':
                filteredBooks = filteredBooks.filter(book => {
                    const stock = parseInt(book.stock_quantity);
                    return stock > 10;
                });
                break;
            case 'low_stock':
                filteredBooks = filteredBooks.filter(book => {
                    const stock = parseInt(book.stock_quantity);
                    return stock >= 1 && stock <= 10;
                });
                break;
            case 'out_of_stock':
                filteredBooks = filteredBooks.filter(book => {
                    const stock = parseInt(book.stock_quantity);
                    return stock === 0;
                });
                break;
            case 'all':
            default:
                break;
        }
        
        displayBookInventory(filteredBooks);
        updateBookManagementSection();
    }

    function loadAllBooks() {
        document.getElementById('stockFilter').value = 'all';
        document.getElementById('categoryFilter').value = 'all';
        document.getElementById('bookSearch').value = '';
        displayBookInventory(currentBooks);
        updateBookManagementSection();
    }

    function showLowStockBooks() {
        document.getElementById('stockFilter').value = 'low_stock';
        filterByStock();
    }

    function showOutOfStockBooks() {
        document.getElementById('stockFilter').value = 'out_of_stock';
        filterByStock();
    }

    // ============ BOOK MODAL FUNCTIONS ============
    
    function openAddBookModal() {
        // 检查权限
        if (!hasPermission('manage_books') && !isSuperAdmin) {
            showNotification('You do not have permission to add books', 'error');
            return;
        }
        
        document.getElementById('modalTitle').textContent = 'Add New Book';
        document.getElementById('bookForm').reset();
        document.getElementById('bookId').value = '';
        
        const isbnInput = document.getElementById('bookISBN');
        isbnInput.readOnly = true;
        isbnInput.classList.remove('isbn-generated');
        
        const newISBN = generateRandomISBN();
        setGeneratedISBN(newISBN);
        
        document.getElementById('duplicateTitleWarning').style.display = 'none';
        
        const titleInput = document.getElementById('bookTitle');
        titleInput.removeEventListener('input', checkDuplicateTitle);
        titleInput.addEventListener('input', checkDuplicateTitle);
        
        loadCategoriesForModal();
        document.getElementById('bookModal').style.display = 'flex';
    }

    async function openEditBookModal(bookId) {
        // 检查权限
        if (!hasPermission('manage_books') && !isSuperAdmin) {
            showNotification('You do not have permission to edit books', 'error');
            return;
        }
        
        try {
            const response = await fetch(`${API_ENDPOINTS.bookDetail}?book_id=${bookId}`);
            const data = await response.json();
            
            if (data.success) {
                const book = data.book || data.data;
                document.getElementById('modalTitle').textContent = 'Edit Book';
                document.getElementById('bookId').value = book.book_id;
                document.getElementById('bookTitle').value = book.title;
                document.getElementById('bookAuthor').value = book.author;
                document.getElementById('bookPrice').value = book.price;
                document.getElementById('bookStock').value = book.stock_quantity;
                
                const isbnInput = document.getElementById('bookISBN');
                if (book.isbn && book.isbn.trim() !== '') {
                    isbnInput.value = book.isbn;
                    isbnInput.readOnly = false;
                    isbnInput.classList.remove('isbn-generated');
                    
                    const statusLabel = document.getElementById('isbnStatusLabel');
                    statusLabel.textContent = 'Manual';
                    statusLabel.style.background = '#f39c12';
                } else {
                    const newISBN = generateRandomISBN();
                    isbnInput.value = newISBN;
                    isbnInput.readOnly = true;
                    isbnInput.classList.add('isbn-generated');
                    
                    const statusLabel = document.getElementById('isbnStatusLabel');
                    statusLabel.textContent = 'Auto-generated';
                    statusLabel.style.background = '#3498db';
                }
                
                document.getElementById('bookDescription').value = book.description || '';
                document.getElementById('bookPublisher').value = book.publisher || '';
                document.getElementById('bookPublishDate').value = book.publish_date || '';
                
                await loadCategoriesForModal();
                document.getElementById('bookCategory').value = book.category_id;
                
                document.getElementById('duplicateTitleWarning').style.display = 'none';
                
                document.getElementById('bookModal').style.display = 'flex';
            }
        } catch (error) {
            console.error('Error loading book:', error);
            showNotification('Error loading book data', 'error');
        }
    }

    function closeModal() {
        document.getElementById('bookModal').style.display = 'none';
        const isbnInput = document.getElementById('bookISBN');
        isbnInput.readOnly = true;
        isbnInput.classList.remove('isbn-generated');
        
        const titleInput = document.getElementById('bookTitle');
        titleInput.removeEventListener('input', checkDuplicateTitle);
    }

    function openStockModal(bookId, bookTitle, currentStock) {
        // 检查权限
        if (!hasPermission('manage_inventory') && !isSuperAdmin) {
            showNotification('You do not have permission to update stock', 'error');
            return;
        }
        
        document.getElementById('stockBookId').value = bookId;
        document.getElementById('stockBookTitle').value = bookTitle;
        document.getElementById('newStockQuantity').value = currentStock;
        document.getElementById('stockModal').style.display = 'flex';
    }

    function closeStockModal() {
        document.getElementById('stockModal').style.display = 'none';
    }

    // ============ DUPLICATE TITLE CHECK FUNCTION ============
    
    async function checkDuplicateTitle() {
        const title = document.getElementById('bookTitle').value.trim();
        const bookId = document.getElementById('bookId').value;
        
        if (!title || title.length < 2) {
            document.getElementById('duplicateTitleWarning').style.display = 'none';
            return;
        }
        
        try {
            const response = await fetch(`${API_ENDPOINTS.checkDuplicate}?title=${encodeURIComponent(title)}&book_id=${bookId || ''}`);
            const data = await response.json();
            
            const warningDiv = document.getElementById('duplicateTitleWarning');
            const warningText = document.getElementById('duplicateWarningText');
            const saveButton = document.getElementById('saveBookBtn');
            
            if (data.success && data.duplicate) {
                if (data.book_id == bookId) {
                    warningDiv.style.display = 'none';
                    saveButton.disabled = false;
                    saveButton.innerHTML = '💾 Save Book';
                } else {
                    warningDiv.style.display = 'block';
                    warningText.textContent = `A book titled "${title}" already exists in the database.`;
                    saveButton.disabled = true;
                    saveButton.innerHTML = '⚠️ Duplicate Title';
                }
            } else {
                warningDiv.style.display = 'none';
                saveButton.disabled = false;
                saveButton.innerHTML = '💾 Save Book';
            }
        } catch (error) {
            console.error('Error checking duplicate title:', error);
            const warningDiv = document.getElementById('duplicateTitleWarning');
            warningDiv.style.display = 'block';
            document.getElementById('duplicateWarningText').textContent = 
                'Unable to verify title uniqueness. Please check manually.';
        }
    }

    // ============ SAVE BOOK FUNCTION ============
    
    async function saveBook() {
        // 检查权限
        if (!hasPermission('manage_books') && !isSuperAdmin) {
            showNotification('You do not have permission to save books', 'error');
            return;
        }
        
        const bookId = document.getElementById('bookId').value;
        const isEditing = !!bookId;
        
        const bookData = {
            title: document.getElementById('bookTitle').value.trim(),
            author: document.getElementById('bookAuthor').value.trim(),
            category_id: document.getElementById('bookCategory').value,
            price: document.getElementById('bookPrice').value,
            stock_quantity: document.getElementById('bookStock').value,
            isbn: document.getElementById('bookISBN').value.trim(),
            description: document.getElementById('bookDescription').value.trim(),
            publisher: document.getElementById('bookPublisher').value.trim(),
            publish_date: document.getElementById('bookPublishDate').value
        };

        if (!isEditing && (!bookData.isbn || bookData.isbn === '')) {
            const newISBN = generateRandomISBN();
            bookData.isbn = newISBN;
            document.getElementById('bookISBN').value = newISBN;
        }
        
        if (bookData.isbn && !isValidISBN(bookData.isbn)) {
            showNotification('Invalid ISBN format. Please use format: 978-XXXXXXXXXXX (e.g., 978-1491950357)', 'error');
            return;
        }

        if (isEditing) {
            bookData.id = bookId;
        }

        const requiredFields = ['title', 'author', 'category_id', 'price', 'stock_quantity'];
        const missingFields = requiredFields.filter(field => !bookData[field]);
        
        if (missingFields.length > 0) {
            showNotification(`Please fill in all required fields: ${missingFields.join(', ')}`, 'error');
            return;
        }

        if (!isEditing) {
            try {
                const checkResponse = await fetch(`${API_ENDPOINTS.checkDuplicate}?title=${encodeURIComponent(bookData.title)}`);
                const checkData = await checkResponse.json();
                
                if (checkData.success && checkData.duplicate) {
                    showNotification(`Cannot save: A book titled "${bookData.title}" already exists!`, 'error');
                    return;
                }
            } catch (error) {
                console.warn('Could not verify title uniqueness:', error);
            }
        }

        const saveButton = document.querySelector('#bookModal .modal-footer .btn-primary');
        const originalText = saveButton.textContent;
        saveButton.innerHTML = '<span class="spinner"></span> Saving...';
        saveButton.disabled = true;

        try {
            const response = await fetch(API_ENDPOINTS.saveBook, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(bookData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification(result.message, 'success');
                
                closeModal();
                
                setTimeout(() => {
                    if (document.getElementById('book-inventory').classList.contains('active')) {
                        loadBookInventoryData();
                    }
                    loadDashboardData();
                }, 500);
            } else {
                if (result.error && result.error.toLowerCase().includes('duplicate') || 
                    result.error.toLowerCase().includes('already exists')) {
                    showNotification(`Cannot save: A book with this title already exists!`, 'error');
                } else {
                    showNotification(`Error: ${result.error}`, 'error');
                }
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Error saving book. Please try again.', 'error');
        } finally {
            saveButton.innerHTML = originalText;
            saveButton.disabled = false;
        }
    }

    async function loadCategoriesForModal() {
        try {
            const response = await fetch(API_ENDPOINTS.categories);
            const data = await response.json();
            
            const categorySelect = document.getElementById('bookCategory');
            categorySelect.innerHTML = '<option value="">Select Category</option>';
            
            if (data.success) {
                const categories = data.categories || data.data || [];
                categories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.category_id;
                    option.textContent = category.category_name;
                    categorySelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading categories:', error);
        }
    }

    async function updateStock() {
        // 检查权限
        if (!hasPermission('manage_inventory') && !isSuperAdmin) {
            showNotification('You do not have permission to update stock', 'error');
            return;
        }
        
        const stockData = {
            book_id: document.getElementById('stockBookId').value,
            stock_quantity: document.getElementById('newStockQuantity').value
        };

        try {
            const response = await fetch(API_ENDPOINTS.updateStock, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(stockData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Stock updated successfully!', 'success');
                closeStockModal();
                loadSectionData('book-inventory');
                loadDashboardData();
            } else {
                showNotification('Error updating stock: ' + result.error, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Error updating stock. Please try again.', 'error');
        }
    }

    async function deleteBook(bookId) {
        // 检查权限
        if (!hasPermission('manage_books') && !isSuperAdmin) {
            showNotification('You do not have permission to delete books', 'error');
            return;
        }
        
        if (confirm('Are you sure you want to delete this book?')) {
            try {
                const response = await fetch(API_ENDPOINTS.deleteBook, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: bookId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Book deleted successfully!', 'success');
                    loadSectionData('book-inventory');
                    loadDashboardData();
                } else {
                    showNotification('Error deleting book: ' + result.error, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error deleting book. Please try again.', 'error');
            }
        }
    }

    // ============ SALES OVERVIEW FUNCTIONS ============

    async function loadSalesOverview() {
        // 检查权限
        if (!hasPermission('view_analytics') && !isSuperAdmin) {
            return;
        }
        
        try {
            const response = await fetch(API_ENDPOINTS.salesOverview);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                displaySalesOverview(data);
            } else {
                console.error('Error loading sales overview:', data.error);
                showNotification('Error loading sales overview', 'error');
            }
        } catch (error) {
            console.error('Error loading sales overview:', error);
            showNotification('Error loading sales overview', 'error');
        }
    }

    function displaySalesOverview(data) {
        const chartContainer = document.getElementById('salesOverviewChart');
        
        if (!chartContainer) return;
        
        if (!data || !data.sales_data || data.sales_data.length === 0) {
            chartContainer.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;">No sales data available</div>';
            return;
        }
        
        // 简单的图表显示
        const salesData = data.sales_data;
        const totalRevenue = salesData.reduce((sum, item) => sum + (item.revenue || 0), 0);
        const totalOrders = salesData.reduce((sum, item) => sum + (item.order_count || 0), 0);
        
        const chartHTML = `
            <div style="padding: 20px; height: 100%;">
                <div style="display: flex; justify-content: space-around; margin-bottom: 30px;">
                    <div style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: bold; color: var(--success);">RM ${totalRevenue.toFixed(2)}</div>
                        <div style="color: #666; font-size: 0.9rem;">Total Revenue</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: bold; color: var(--accent);">${totalOrders}</div>
                        <div style="color: #666; font-size: 0.9rem;">Total Orders</div>
                    </div>
                </div>
                
                <div style="height: 200px; display: flex; align-items: flex-end; gap: 10px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                    ${salesData.map((item, index) => {
                        const date = new Date(item.date || item.day);
                        const dateStr = date.getDate() + '/' + (date.getMonth() + 1);
                        const maxRevenue = Math.max(...salesData.map(s => s.revenue || 0));
                        const height = maxRevenue > 0 ? ((item.revenue || 0) / maxRevenue * 100) : 0;
                        
                        return `
                            <div style="flex: 1; text-align: center;">
                                <div style="background: linear-gradient(to top, var(--success), #27ae60); 
                                          height: ${height}%; 
                                          border-radius: 4px 4px 0 0;
                                          position: relative;"
                                          title="${dateStr}: RM ${(item.revenue || 0).toFixed(2)}">
                                    <div style="position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); 
                                              font-size: 0.7rem; color: #666; white-space: nowrap;">
                                        RM ${(item.revenue || 0).toFixed(0)}
                                    </div>
                                </div>
                                <div style="font-size: 0.7rem; margin-top: 5px; color: #666;">${dateStr}</div>
                            </div>
                        `;
                    }).join('')}
                </div>
                
                <div style="margin-top: 15px; font-size: 0.8rem; color: #666;">
                    <div>Period: ${salesData.length} days</div>
                    <div>Average Daily: RM ${(totalRevenue / salesData.length).toFixed(2)}</div>
                </div>
            </div>
        `;
        
        chartContainer.innerHTML = chartHTML;
    }
function updateSalesOverviewChart(data) {
    const chartContainer = document.getElementById('salesOverviewChart');
    if (!chartContainer) {
        console.warn('Sales overview chart container not found');
        return;
    }
    
    // 使用最近销售数据
    if (data.recent_sales && data.recent_sales.length > 0) {
        const salesData = data.recent_sales;
        
        // 创建小时图表
        let chartHTML = `
            <div style="padding: 20px;">
                <h4 style="margin-bottom: 15px; color: var(--primary);">Recent Sales (24 Hours)</h4>
                
                <div style="display: flex; justify-content: space-around; margin-bottom: 20px;">
                    <div style="text-align: center;">
                        <div style="font-size: 1.8rem; font-weight: bold; color: var(--success);">
                            ${data.today?.orders || 0}
                        </div>
                        <div style="color: #666; font-size: 0.9rem;">Today's Orders</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 1.8rem; font-weight: bold; color: var(--success);">
                            ${data.today?.revenue_formatted || 'RM 0.00'}
                        </div>
                        <div style="color: #666; font-size: 0.9rem;">Today's Revenue</div>
                    </div>
                </div>
                
                <div style="height: 150px; display: flex; align-items: flex-end; gap: 5px; margin-bottom: 20px;">
        `;
        
        // 为每个小时创建柱状图
        const maxRevenue = Math.max(...salesData.map(h => h.hourly_revenue), 1);
        
        for (let hour = 0; hour < 24; hour++) {
            const hourData = salesData.find(h => h.hour === hour);
            const revenue = hourData?.hourly_revenue || 0;
            const height = Math.max((revenue / maxRevenue) * 100, 3); // 最小高度3%
            
            chartHTML += `
                <div style="flex: 1; position: relative;">
                    <div style="
                        width: 100%;
                        height: ${height}%;
                        background: linear-gradient(to top, var(--success), #27ae60);
                        border-radius: 3px 3px 0 0;
                        cursor: pointer;
                        transition: all 0.3s;
                    " 
                    onmouseover="this.style.background='linear-gradient(to top, #2ecc71, #27ae60)'"
                    onmouseout="this.style.background='linear-gradient(to top, var(--success), #27ae60)'"
                    title="${hour}:00 - RM ${revenue.toFixed(2)}">
                    </div>
                    <div style="
                        font-size: 0.7rem;
                        color: #666;
                        text-align: center;
                        margin-top: 5px;
                        transform: rotate(-45deg);
                        transform-origin: left top;
                        white-space: nowrap;
                    ">
                        ${hour}
                    </div>
                </div>
            `;
        }
        
        chartHTML += `
                </div>
                
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #666; padding-top: 10px; border-top: 1px solid #eee;">
                    <div>Total: RM ${salesData.reduce((sum, h) => sum + h.hourly_revenue, 0).toFixed(2)}</div>
                    <div>Orders: ${salesData.reduce((sum, h) => sum + h.hourly_orders, 0)}</div>
                </div>
            </div>
        `;
        
        chartContainer.innerHTML = chartHTML;
    } else {
        // 没有数据时显示简单信息
        chartContainer.innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <div style="font-size: 1.5rem; color: var(--success); margin-bottom: 10px;">
                    ${data.today?.revenue_formatted || 'RM 0.00'}
                </div>
                <div style="color: #666; margin-bottom: 10px;">Today's Revenue</div>
                <div style="color: #999; font-size: 0.9rem;">
                    <div>Orders: ${data.today?.orders || 0}</div>
                    <div>Customers: ${data.today?.customers || 0}</div>
                </div>
            </div>
        `;
    }
}

    // ============ SALES ANALYTICS FUNCTIONS ============
    
    // 修复方案：更新loadSalesAnalytics函数
// ============ 修复的销售分析函数 ============
// ============ SALES REPORT FUNCTIONS ============

async function loadSalesAnalytics(period = '30days') {
    // 检查权限
    if (!hasPermission('view_analytics') && !isSuperAdmin) {
        showAccessDenied('analytics');
        return;
    }
    
    try {
        // 重置UI
        document.getElementById('customerInfo').style.display = 'none';
        document.getElementById('customerOrdersTableContainer').style.display = 'none';
        
        // 添加加载状态
        document.getElementById('analyticsSummary').innerHTML = '<div class="loading">Loading...</div>';
        document.getElementById('salesTrendChart').innerHTML = '<div class="loading">Loading chart...</div>';
        document.getElementById('topBooksChart').innerHTML = '<div class="loading">Loading top books...</div>';
        document.getElementById('categoryRevenueChart').innerHTML = '<div class="loading">Loading category data...</div>';
        
        // 添加时间周期参数
        const params = new URLSearchParams({ period: period });
        
        // 如果是自定义日期范围
        if (period === 'custom') {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            if (startDate && endDate) {
                params.append('start_date', startDate);
                params.append('end_date', endDate);
            } else {
                showNotification('Please select custom date range', 'warning');
                return;
            }
        }
        
        // 获取客户ID
        const customerId = document.getElementById('customerFilter').value;
        if (customerId && customerId !== 'all') {
            params.append('customer_id', customerId);
            
            // 同时加载客户详细信息
            setTimeout(() => {
                loadCustomerSalesReport(customerId, period);
            }, 500);
        }
        
        console.log('Loading sales analytics with params:', params.toString());
        
        const response = await fetch(`${API_ENDPOINTS.salesAnalytics}?${params}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            console.log('Sales analytics data loaded successfully:', data);
            displaySalesAnalytics(data);
        } else {
            console.error('Error loading sales report:', data.error);
            showAnalyticsError(data.error || 'Failed to load sales report');
        }
    } catch (error) {
        console.error('Error loading sales report:', error);
        showAnalyticsError('Network error: ' + error.message);
    }
}

function displaySalesAnalytics(data) {
    console.log('Displaying sales analytics:', data);
    
    // 更新销售摘要
    updateAnalyticsSummary(data.summary);
    
    // 更新销售趋势图表
    if (data.sales_trend && data.sales_trend.length > 0) {
        updateSalesTrendChart(data.sales_trend);
    } else if (data.daily_sales && data.daily_sales.length > 0) {
        // 转换格式
        const trendData = data.daily_sales.map(day => ({
            date: day.date,
            day_name: day.day_name,
            total_revenue: day.daily_total,
            order_count: day.daily_orders,
            customer_count: day.daily_customers,
            total_quantity: 0
        }));
        updateSalesTrendChart(trendData);
    } else {
        document.getElementById('salesTrendChart').innerHTML = 
            '<div style="text-align: center; padding: 40px; color: #666;">No trend data available</div>';
    }
    
    // 更新畅销书籍图表
    if (data.top_books && data.top_books.length > 0) {
        updateTopBooksChart(data.top_books);
    } else {
        document.getElementById('topBooksChart').innerHTML = 
            '<div style="text-align: center; padding: 40px; color: #666;">No top books data available</div>';
    }
    
    // 更新类别收入图表
    if (data.category_revenue && data.category_revenue.length > 0) {
        updateCategoryRevenueChart(data.category_revenue);
    } else {
        document.getElementById('categoryRevenueChart').innerHTML = 
            '<div style="text-align: center; padding: 40px; color: #666;">No category data available</div>';
    }
    
    // 显示客户信息（如果有）
    if (data.customer_info && Object.keys(data.customer_info).length > 0) {
        displayCustomerInfo(data);
    }
}

function updateAnalyticsSummary(summary) {
    const summaryContainer = document.getElementById('analyticsSummary');
    
    if (!summaryContainer) {
        console.error('Summary container not found');
        return;
    }
    
    // 确保数据存在
    const totalOrders = summary.total_orders || 0;
    const totalRevenue = summary.total_revenue || 0;
    const totalCustomers = summary.total_customers || 0;
    const avgOrderValue = summary.avg_order_value || 
        (totalOrders > 0 ? totalRevenue / totalOrders : 0);
    
    const summaryHTML = `
        <div class="stat-card" style="border-top-color: #3498db;">
            <div class="stat-number">${totalOrders}</div>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card" style="border-top-color: #2ecc71;">
            <div class="stat-number">RM ${parseFloat(totalRevenue).toFixed(2)}</div>
            <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card" style="border-top-color: #9b59b6;">
            <div class="stat-number">${totalCustomers}</div>
            <div class="stat-label">Customers</div>
        </div>
        <div class="stat-card" style="border-top-color: #f39c12;">
            <div class="stat-number">RM ${parseFloat(avgOrderValue).toFixed(2)}</div>
            <div class="stat-label">Avg Order Value</div>
        </div>
    `;
    
    summaryContainer.innerHTML = summaryHTML;
}

function updateSalesTrendChart(trendData) {
    const chartContainer = document.getElementById('salesTrendChart');
    
    if (!chartContainer || !trendData || trendData.length === 0) {
        chartContainer.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;">No trend data available</div>';
        return;
    }
    
    // 排序数据
    const sortedData = [...trendData].sort((a, b) => new Date(a.date) - new Date(b.date));
    
    // 计算最大值用于比例
    const maxRevenue = Math.max(...sortedData.map(d => d.total_revenue));
    const maxOrders = Math.max(...sortedData.map(d => d.order_count));
    
    const chartHTML = `
        <div class="chart-header">
            <div class="chart-title">Sales Trend (${sortedData.length} days)</div>
            <div class="chart-controls">
                <div style="display: flex; align-items: center; gap: 10px; font-size: 12px;">
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <div style="width: 12px; height: 12px; background: #2ecc71; border-radius: 2px;"></div>
                        <span>Revenue</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <div style="width: 12px; height: 12px; background: #3498db; border-radius: 2px;"></div>
                        <span>Orders</span>
                    </div>
                </div>
            </div>
        </div>
        <div style="height: 250px; display: flex; align-items: flex-end; gap: 6px; padding: 20px 10px; border-bottom: 1px solid #eee;">
            ${sortedData.map((day, index) => {
                const date = new Date(day.date);
                const dateStr = date.getDate().toString().padStart(2, '0') + '/' + (date.getMonth() + 1);
                const revenueHeight = maxRevenue > 0 ? (day.total_revenue / maxRevenue * 100) : 0;
                const ordersHeight = maxOrders > 0 ? (day.order_count / maxOrders * 100) : 0;
                
                return `
                    <div style="flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; position: relative;">
                        <!-- Revenue bar -->
                        <div style="width: 70%; background: linear-gradient(to top, #2ecc71, #27ae60); 
                                   height: ${revenueHeight}%; border-radius: 3px 3px 0 0; position: absolute; bottom: 0; left: 15%;"
                                   title="${dateStr}: RM ${day.total_revenue.toFixed(2)} revenue, ${day.order_count} orders">
                            <div style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); 
                                      font-size: 11px; color: #666; white-space: nowrap;">
                                RM ${day.total_revenue > 1000 ? (day.total_revenue / 1000).toFixed(1) + 'k' : day.total_revenue.toFixed(0)}
                            </div>
                        </div>
                        <!-- Orders bar -->
                        <div style="width: 70%; background: linear-gradient(to top, #3498db, #2980b9); 
                                   height: ${ordersHeight}%; border-radius: 3px 3px 0 0; position: absolute; bottom: 0; right: 15%; opacity: 0.8;"
                                   title="${dateStr}: ${day.order_count} orders">
                        </div>
                        <div style="font-size: 11px; margin-top: 120px; color: #666; transform: rotate(-45deg); transform-origin: left top; white-space: nowrap;">
                            ${dateStr}
                        </div>
                    </div>
                `;
            }).join('')}
        </div>
        <div style="padding: 10px; font-size: 12px; color: #666;">
            <div>Total Revenue: RM ${sortedData.reduce((sum, day) => sum + day.total_revenue, 0).toFixed(2)}</div>
            <div>Total Orders: ${sortedData.reduce((sum, day) => sum + day.order_count, 0)}</div>
            <div>Average Daily: RM ${(sortedData.reduce((sum, day) => sum + day.total_revenue, 0) / sortedData.length).toFixed(2)}</div>
        </div>
    `;
    
    chartContainer.innerHTML = chartHTML;
}

function updateTopBooksChart(topBooks) {
    const chartContainer = document.getElementById('topBooksChart');
    
    if (!chartContainer || !topBooks || topBooks.length === 0) {
        chartContainer.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;">No top books data available</div>';
        return;
    }
    
    // 计算最大值用于比例
    const maxRevenue = Math.max(...topBooks.map(b => b.total_revenue));
    
    const chartHTML = `
        <div class="chart-header">
            <div class="chart-title">Top Selling Books</div>
            <div class="chart-controls">
                <span style="font-size: 12px; color: #666;">${topBooks.length} books</span>
            </div>
        </div>
        <div style="overflow-y: auto; max-height: 350px;">
            <table class="top-books-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Book</th>
                        <th style="width: 100px;">Revenue</th>
                        <th style="width: 80px;">Sold</th>
                    </tr>
                </thead>
                <tbody>
                    ${topBooks.map((book, index) => {
                        const barWidth = maxRevenue > 0 ? (book.total_revenue / maxRevenue * 100) : 0;
                        
                        return `
                            <tr>
                                <td style="font-weight: bold; color: #666;">${index + 1}</td>
                                <td>
                                    <div class="book-info">
                                        ${book.cover_image ? 
                                            `<img src="${book.cover_image}" alt="${book.title}" class="book-cover" onerror="this.src='https://via.placeholder.com/40x60/cccccc/666666?text=No+Cover'">` : 
                                            `<div class="book-cover" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999;">No Cover</div>`
                                        }
                                        <div>
                                            <div class="book-title">${book.title}</div>
                                            <div class="book-author">by ${book.author}</div>
                                            <div style="font-size: 11px; color: #666; margin-top: 2px;">${book.category}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: bold; color: #2ecc71;">RM ${book.total_revenue.toFixed(2)}</div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: ${barWidth}%; background: #2ecc71;"></div>
                                    </div>
                                    <div style="font-size: 11px; color: #666; margin-top: 2px;">${book.order_count} orders</div>
                                </td>
                                <td>
                                    <div style="text-align: center;">
                                        <div style="font-weight: bold; color: #3498db; font-size: 16px;">${book.total_quantity}</div>
                                        <div style="font-size: 11px; color: #666;">units</div>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    chartContainer.innerHTML = chartHTML;
}

function updateCategoryRevenueChart(categoryRevenue) {
    const chartContainer = document.getElementById('categoryRevenueChart');
    
    if (!chartContainer || !categoryRevenue || categoryRevenue.length === 0) {
        chartContainer.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;">No category data available</div>';
        return;
    }
    
    // 颜色数组
    const colors = ['#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6', '#1abc9c', '#34495e', '#16a085', '#8e44ad', '#27ae60'];
    
    // 计算总收入和最大值
    const totalRevenue = categoryRevenue.reduce((sum, cat) => sum + cat.total_revenue, 0);
    const maxRevenue = Math.max(...categoryRevenue.map(c => c.total_revenue));
    
    const chartHTML = `
        <div class="chart-header">
            <div class="chart-title">Revenue by Category</div>
            <div class="chart-controls">
                <span style="font-size: 12px; color: #666;">${categoryRevenue.length} categories</span>
            </div>
        </div>
        <div style="display: flex; height: 250px; gap: 20px; padding: 10px 0;">
            <!-- Bar chart -->
            <div style="flex: 2; display: flex; align-items: flex-end; gap: 10px;">
                ${categoryRevenue.map((category, index) => {
                    const height = maxRevenue > 0 ? (category.total_revenue / maxRevenue * 100) : 0;
                    const percentage = totalRevenue > 0 ? (category.total_revenue / totalRevenue * 100) : 0;
                    const color = colors[index % colors.length];
                    
                    return `
                        <div style="flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%;">
                            <div style="width: 80%; background: linear-gradient(to top, ${color}, ${color}80); 
                                       height: ${height}%; border-radius: 4px 4px 0 0; position: relative;"
                                       title="${category.category_name}: RM ${category.total_revenue.toFixed(2)} (${percentage.toFixed(1)}%)">
                                <div style="position: absolute; top: -25px; left: 50%; transform: translateX(-50%); 
                                          font-size: 11px; color: #666; white-space: nowrap; font-weight: bold;">
                                    ${percentage.toFixed(1)}%
                                </div>
                            </div>
                            <div style="font-size: 11px; margin-top: 10px; color: #666; text-align: center; 
                                       height: 40px; overflow: hidden; line-height: 1.2; width: 100%;">
                                ${category.category_name}
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
            
            <!-- Summary -->
            <div style="flex: 1; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6;">
                <h4 style="color: #2c3e50; margin-bottom: 15px; font-size: 16px;">Summary</h4>
                <div style="font-size: 24px; font-weight: bold; color: #2ecc71; margin-bottom: 10px;">
                    RM ${totalRevenue.toFixed(2)}
                </div>
                <div style="font-size: 14px; color: #666; margin-bottom: 15px;">
                    Total Revenue
                </div>
                <div style="margin-top: 15px;">
                    <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Top Categories:</div>
                    ${categoryRevenue.slice(0, 3).map((category, index) => `
                        <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #eee;">
                            <span style="font-size: 12px;">${category.category_name}</span>
                            <span style="font-size: 12px; font-weight: bold; color: #3498db;">RM ${category.total_revenue.toFixed(2)}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
    
    chartContainer.innerHTML = chartHTML;
}

function displayCustomerInfo(data) {
    const customerInfoDiv = document.getElementById('customerInfo');
    const customer = data.customer_info || {};
    const summary = data.summary || {};
    
    if (!customer || Object.keys(customer).length === 0) {
        customerInfoDiv.style.display = 'none';
        return;
    }
    
    // 格式化地址
    const addressParts = [];
    if (customer.address) addressParts.push(customer.address);
    if (customer.city) addressParts.push(customer.city);
    if (customer.state) addressParts.push(customer.state);
    if (customer.zip_code) addressParts.push(customer.zip_code);
    const formattedAddress = addressParts.join(', ');
    
    customerInfoDiv.innerHTML = `
        <div class="customer-info-card">
            <div class="customer-info-header">
                <div>
                    <h3 style="color: #2c3e50; margin: 0;">
                        ${customer.full_name || customer.username}
                        <small style="color: #666; font-weight: normal;">
                            ${customer.username ? `(${customer.username})` : ''}
                        </small>
                    </h3>
                    <div style="font-size: 14px; color: #666; margin-top: 5px;">
                        Customer ID: ${customer.customer_id}
                    </div>
                </div>
                <button class="btn btn-sm" onclick="clearCustomerFilter()" style="background: #f8f9fa; border: 1px solid #dee2e6;">
                    <i class="fas fa-times"></i> Clear Filter
                </button>
            </div>
            
            <div class="customer-details-grid">
                ${customer.email ? `
                    <div class="customer-detail-item">
                        <strong>Email:</strong><br>
                        <span style="color: #666;">${customer.email}</span>
                    </div>
                ` : ''}
                
                ${customer.phone ? `
                    <div class="customer-detail-item">
                        <strong>Phone:</strong><br>
                        <span style="color: #666;">${customer.phone}</span>
                    </div>
                ` : ''}
                
                ${formattedAddress ? `
                    <div class="customer-detail-item">
                        <strong>Address:</strong><br>
                        <span style="color: #666;">${formattedAddress}</span>
                    </div>
                ` : ''}
                
                ${summary.total_orders ? `
                    <div class="customer-detail-item">
                        <strong>Total Orders:</strong><br>
                        <span style="color: #2ecc71; font-weight: bold;">${summary.total_orders}</span>
                    </div>
                ` : ''}
                
                ${summary.total_spent ? `
                    <div class="customer-detail-item">
                        <strong>Total Spent:</strong><br>
                        <span style="color: #2ecc71; font-weight: bold;">RM ${parseFloat(summary.total_spent).toFixed(2)}</span>
                    </div>
                ` : ''}
                
                ${summary.avg_order_value ? `
                    <div class="customer-detail-item">
                        <strong>Avg Order Value:</strong><br>
                        <span style="color: #666;">RM ${parseFloat(summary.avg_order_value).toFixed(2)}</span>
                    </div>
                ` : ''}
                
                ${summary.customer_since && summary.customer_since !== 'No orders yet' ? `
                    <div class="customer-detail-item">
                        <strong>Customer Since:</strong><br>
                        <span style="color: #666;">${summary.customer_since}</span>
                    </div>
                ` : ''}
                
                ${customer.gender ? `
                    <div class="customer-detail-item">
                        <strong>Gender:</strong><br>
                        <span style="color: #666;">${customer.gender}</span>
                    </div>
                ` : ''}
                
                ${customer.created_at ? `
                    <div class="customer-detail-item">
                        <strong>Registered:</strong><br>
                        <span style="color: #666;">${new Date(customer.created_at).toLocaleDateString()}</span>
                    </div>
                ` : ''}
            </div>
            
            ${data.behavior_analysis?.favorite_categories?.length > 0 ? `
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                    <h5 style="color: #2c3e50; margin-bottom: 10px;">Favorite Categories</h5>
                    <div class="category-tags">
                        ${data.behavior_analysis.favorite_categories.map(category => `
                            <span class="category-tag">
                                ${category.category_name}: ${category.total_quantity} books
                            </span>
                        `).join('')}
                    </div>
                </div>
            ` : ''}
        </div>
    `;
    
    customerInfoDiv.style.display = 'block';
}

function clearCustomerFilter() {
    document.getElementById('customerFilter').value = 'all';
    document.getElementById('customerInfo').style.display = 'none';
    document.getElementById('customerOrdersTableContainer').style.display = 'none';
    loadSalesAnalytics();
}

async function loadCustomerSalesReport(customerId, period) {
    try {
        // 加载客户详细信息
        const customerInfoResponse = await fetch(`get_customer_sales.php?customer_id=${customerId}`);
        const customerInfoData = await customerInfoResponse.json();
        
        if (customerInfoData.success) {
            // 显示客户订单
            if (customerInfoData.recent_orders && customerInfoData.recent_orders.length > 0) {
                displayCustomerOrders(customerInfoData.recent_orders);
            }
        }
    } catch (error) {
        console.error('Error loading customer sales report:', error);
    }
}

function displayCustomerOrders(orders) {
    const tableContainer = document.getElementById('customerOrdersTableContainer');
    const tableBody = document.getElementById('customerOrdersTable');
    
    if (!orders || orders.length === 0) {
        tableContainer.style.display = 'none';
        return;
    }
    
    tableBody.innerHTML = orders.map(order => {
        const statusInfo = ORDER_STATUS_MAP[order.status] || { text: order.status, class: 'status-confirmed' };
        
        return `
            <tr>
                <td><strong>#${order.order_id}</strong></td>
                <td>${order.order_date_formatted || new Date(order.order_date).toLocaleDateString()}</td>
                <td>
                    ${order.items_count || 0} items
                    ${order.total_quantity ? `<br><small style="color: #666;">${order.total_quantity} total</small>` : ''}
                </td>
                <td><strong>RM ${parseFloat(order.total_amount || 0).toFixed(2)}</strong></td>
                <td>
                    <span class="order-status ${statusInfo.class}">
                        ${statusInfo.text}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="viewOrderDetails(${order.order_id})">
                        <i class="fas fa-eye"></i> View
                    </button>
                </td>
            </tr>
        `;
    }).join('');
    
    tableContainer.style.display = 'block';
}

function showAnalyticsError(error) {
    const containers = ['analyticsSummary', 'salesTrendChart', 'topBooksChart', 'categoryRevenueChart'];
    containers.forEach(containerId => {
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div style="color: #e74c3c; font-size: 24px; margin-bottom: 10px;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div style="color: #666; margin-bottom: 10px;">${error}</div>
                    <button class="btn btn-primary" onclick="loadSalesAnalytics()">
                        <i class="fas fa-redo"></i> Try Again
                    </button>
                </div>
            `;
        }
    });
}

function showAccessDenied(section) {
    const sectionElement = document.getElementById(section);
    if (sectionElement) {
        sectionElement.innerHTML = `
            <div class="access-denied">
                <div style="font-size: 48px; color: #e74c3c; margin-bottom: 20px;">
                    <i class="fas fa-lock"></i>
                </div>
                <h3>Access Denied</h3>
                <p>You don't have permission to view this section.</p>
                <p>Please contact your super administrator for access.</p>
            </div>
        `;
    }
}

// 在DOM加载完成后初始化
document.addEventListener('DOMContentLoaded', async function() {
    console.log('Admin panel initialized');
    
    // Check current admin role
    await checkAdminRole();
    
    // 初始化权限系统
    await initializePermissionSystem();
    
    // Load initial data
    loadDashboardData();
    
    // 加载客户下拉列表
    await loadCustomerDropdown();
    
    // 初始化销售报告（如果当前在分析页面）
    const activeSection = document.querySelector('.content-section.active');
    if (activeSection && activeSection.id === 'analytics') {
        loadSalesAnalytics();
    }
    
    // Update clock
    updateClock();
    setInterval(updateClock, 60000);
    
    // Set up navigation
    setupNavigation();
    
    // Set up permissions listeners
    setupPermissionsListeners();
});

// 确保导航切换时加载正确的数据
function setupNavigation() {
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const sectionId = this.getAttribute('data-section');
            
            // 如果点击的是管理员管理，但不是超级管理员，阻止访问
            if (sectionId === 'admin-management' && !isSuperAdmin) {
                showNotification('Access denied. Super Admin only.', 'error');
                return;
            }
            
            // 更新导航状态
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            
            // 显示对应部分
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(sectionId).classList.add('active');
            
            // 更新标题
            const sectionTitles = {
                'dashboard': 'Dashboard',
                'book-inventory': 'Book & Inventory Management',
                'orders': 'Order Management',
                'categories': 'Category Management',
                'analytics': 'Sales Report Section',
                'admin-management': 'Admin Management'
            };
            
            const titleElement = document.getElementById('sectionTitle');
            if (titleElement) {
                titleElement.textContent = sectionTitles[sectionId] || sectionId;
            }
            
            // 加载对应部分的数据
            loadSectionData(sectionId);
            
            // 应用权限控制
            applySectionPermissions(sectionId);
        });
    });
}

function loadSectionData(sectionId) {
    console.log('Loading section data for:', sectionId);
    
    switch(sectionId) {
        case 'dashboard':
            loadDashboardData();
            loadSalesOverview();
            break;
        case 'book-inventory':
            loadBookInventoryData();
            break;
        case 'orders':
            loadOrdersData();
            break;
        case 'analytics':
            loadSalesAnalytics();
            break;
        case 'categories':
            loadCategoriesData();
            break;
        case 'admin-management':
            if (isSuperAdmin) {
                loadAdminManagementData();
            } else {
                showNotification('Access denied. Super Admin only.', 'error');
                document.querySelector('.nav-link[data-section="dashboard"]').click();
            }
            break;
        default:
            console.warn('Unknown section:', sectionId);
    }
}
// 辅助函数：获取状态CSS类
function getStatusClass(status) {
    const statusMap = {
        'pending': 'status-pending',
        'confirmed': 'status-confirmed',
        'processing': 'status-processing',
        'shipped': 'status-shipped',
        'delivered': 'status-delivered',
        'cancelled': 'status-cancelled'
    };
    
    return statusMap[status.toLowerCase()] || 'status-confirmed';
}

// 辅助函数：获取价值评级CSS类
function getValueRatingClass(value) {
    if (value >= 4.5) return 'value-vip';
    if (value >= 4.0) return 'value-high';
    if (value >= 3.0) return 'value-medium';
    if (value >= 2.0) return 'value-low';
    return 'value-new';
}

// 辅助函数：生成星级评分
function generateStarRating(rating) {
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;
    let stars = '';
    
    for (let i = 0; i < fullStars; i++) {
        stars += '★';
    }
    
    if (hasHalfStar) {
        stars += '☆';
    }
    
    return stars;
}

// CSS样式（可以添加到样式表中）
const style = document.createElement('style');
style.textContent = `
    .customer-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 8px;
        margin-top: 10px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 5px;
        border: 1px solid #dee2e6;
    }
    
    .customer-detail-item {
        padding: 4px 0;
        border-bottom: 1px solid #eee;
    }
    
    .customer-detail-item:last-child {
        border-bottom: none;
    }
    
    .customer-stats-container {
        margin-top: 20px;
    }
    
    .customer-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-top: 10px;
    }
    
    .stat-card {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .stat-label {
        font-size: 12px;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }
    
    .stat-value {
        font-size: 18px;
        font-weight: bold;
        color: #212529;
    }
    
    .status-breakdown {
        margin-top: 20px;
    }
    
    .status-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 5px;
    }
    
    .status-tag {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .status-delivered { background: #d4edda; color: #155724; }
    .status-shipped { background: #cce5ff; color: #004085; }
    .status-confirmed { background: #fff3cd; color: #856404; }
    .status-cancelled { background: #f8d7da; color: #721c24; }
    
    .customer-value-container {
        margin-top: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 5px;
        border: 1px solid #dee2e6;
    }
    
    .value-assessment {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }
    
    .value-item {
        padding: 5px 0;
    }
    
    .value-rating {
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: bold;
        margin-left: 5px;
    }
    
    .value-vip { background: #d4edda; color: #155724; }
    .value-high { background: #cce5ff; color: #004085; }
    .value-medium { background: #fff3cd; color: #856404; }
    .value-low { background: #f8d7da; color: #721c24; }
    .value-new { background: #e2e3e5; color: #383d41; }
    
    .rating-stars {
        margin-left: 5px;
        color: #ffc107;
    }
    
    .recommendation-text {
        color: #17a2b8;
        font-style: italic;
        margin-left: 5px;
    }
    
    .favorites-container {
        margin-top: 20px;
    }
    
    .category-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 5px;
    }
    
    .category-tag {
        background: #e9ecef;
        color: #495057;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
    }
`;
document.head.appendChild(style);

    function displaySalesAnalytics(data) {
        updateAnalyticsSummary(data.summary);
        updateSalesTrendChart(data.sales_trend);
        updateTopBooksChart(data.top_books);
        updateCategoryRevenueChart(data.category_revenue);
    }

    function updateAnalyticsSummary(summary) {
        const summaryContainer = document.getElementById('analyticsSummary');
        
        if (!summaryContainer) return;
        
        const summaryHTML = `
            <div class="stat-card" style="border-top-color: var(--success);">
                <div class="stat-number">${summary.total_orders || 0}</div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card" style="border-top-color: var(--accent);">
                <div class="stat-number">RM ${(summary.total_revenue || 0).toFixed(2)}</div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card" style="border-top-color: var(--info);">
                <div class="stat-number">${summary.total_customers || 0}</div>
                <div class="stat-label">Customers</div>
            </div>
            <div class="stat-card" style="border-top-color: var(--warning);">
                <div class="stat-number">RM ${(summary.avg_order_value || 0).toFixed(2)}</div>
                <div class="stat-label">Avg Order Value</div>
            </div>
        `;
        
        summaryContainer.innerHTML = summaryHTML;
    }

    function updateSalesTrendChart(salesTrend) {
        const chartContainer = document.getElementById('salesTrendChart');
        
        if (!chartContainer) return;
        
        if (!salesTrend || salesTrend.length === 0) {
            chartContainer.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;">No trend data available</div>';
            return;
        }
        
        const trendData = [...salesTrend].reverse();
        const maxRevenue = Math.max(...trendData.map(s => s.total_revenue));
        const maxOrders = Math.max(...trendData.map(s => s.order_count));
        
        const chartHTML = `
            <div style="padding: 20px; height: 100%;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <div style="font-size: 0.9rem; color: #666;">
                        ${trendData.length} days of data
                    </div>
                    <div style="display: flex; gap: 10px; font-size: 0.8rem;">
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <div style="width: 12px; height: 12px; background: var(--success); border-radius: 2px;"></div>
                            <span>Revenue</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <div style="width: 12px; height: 12px; background: var(--accent); border-radius: 2px; opacity: 0.7;"></div>
                            <span>Orders</span>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; align-items: flex-end; height: 200px; gap: 5px; border-bottom: 1px solid #e9ecef; padding-bottom: 10px;">
                    ${trendData.map(day => {
                        const date = new Date(day.date);
                        const dateStr = `${date.getDate()}/${date.getMonth() + 1}`;
                        const revenueHeight = maxRevenue > 0 ? (day.total_revenue / maxRevenue * 100) : 0;
                        const ordersHeight = maxOrders > 0 ? (day.order_count / maxOrders * 100) : 0;
                        
                        return `
                            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; position: relative;">
                                <!-- Revenue bar -->
                                <div style="width: 70%; background: linear-gradient(to top, var(--success), #27ae60); 
                                           height: ${revenueHeight}%; border-radius: 3px 3px 0 0; position: absolute; bottom: 0; left: 15%;"
                                           title="${dateStr}: RM ${day.total_revenue.toFixed(2)} revenue">
                                </div>
                                <!-- Orders bar -->
                                <div style="width: 70%; background: linear-gradient(to top, var(--accent), #3498db); 
                                           height: ${ordersHeight}%; border-radius: 3px 3px 0 0; position: absolute; bottom: 0; right: 15%; opacity: 0.7;"
                                           title="${dateStr}: ${day.order_count} orders">
                                </div>
                                <div style="font-size: 0.7rem; margin-top: 110px; color: #666; transform: rotate(-45deg); transform-origin: left top;">
                                    ${dateStr}
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-top: 15px; font-size: 0.8rem; color: #666;">
                    <div>Total: RM ${trendData.reduce((sum, day) => sum + day.total_revenue, 0).toFixed(2)}</div>
                    <div>Avg Daily: RM ${(trendData.reduce((sum, day) => sum + day.total_revenue, 0) / trendData.length).toFixed(2)}</div>
                </div>
            </div>
        `;
        
        chartContainer.innerHTML = chartHTML;
    }

    function updateTopBooksChart(topBooks) {
        const chartContainer = document.getElementById('topBooksChart');
        
        if (!chartContainer) return;
        
        if (!topBooks || topBooks.length === 0) {
            chartContainer.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;">No top books data available</div>';
            return;
        }
        
        const maxRevenue = Math.max(...topBooks.map(b => b.total_revenue));
        
        const chartHTML = `
            <div style="padding: 20px; height: 100%; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa; border-bottom: 2px solid #ddd;">
                            <th style="padding: 10px; text-align: left; font-weight: bold; color: var(--primary);">#</th>
                            <th style="padding: 10px; text-align: left; font-weight: bold; color: var(--primary);">Book</th>
                            <th style="padding: 10px; text-align: left; font-weight: bold; color: var(--primary);">Revenue</th>
                            <th style="padding: 10px; text-align: left; font-weight: bold; color: var(--primary);">Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${topBooks.map((book, index) => {
                            const barWidth = maxRevenue > 0 ? (book.total_revenue / maxRevenue * 100) : 0;
                            
                            return `
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 10px; font-weight: bold; color: var(--primary); vertical-align: top;">
                                        ${index + 1}
                                    </td>
                                    <td style="padding: 10px; vertical-align: top;">
                                        <div style="font-weight: 500; margin-bottom: 4px;">${book.title}</div>
                                        <div style="font-size: 0.8rem; color: #666;">
                                            by ${book.author}
                                        </div>
                                        <div style="font-size: 0.7rem; color: #999;">
                                            ${book.category}
                                        </div>
                                    </td>
                                    <td style="padding: 10px; vertical-align: top; width: 200px;">
                                        <div style="font-weight: bold; color: var(--success); margin-bottom: 4px;">
                                            RM ${book.total_revenue.toFixed(2)}
                                        </div>
                                        <div style="background: #e9ecef; border-radius: 3px; height: 8px; overflow: hidden;">
                                            <div style="background: linear-gradient(90deg, var(--success), #27ae60); 
                                                     width: ${barWidth}%; height: 100%; border-radius: 3px;">
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 10px; vertical-align: top; text-align: center;">
                                        <div style="font-weight: bold; color: var(--info);">
                                            ${book.total_quantity}
                                        </div>
                                        <div style="font-size: 0.8rem; color: #666;">
                                            ${book.order_count} orders
                                        </div>
                                        <div class="${getStockStatusClass(book.stock_quantity)}" style="margin-top: 4px; display: inline-block;">
                                            ${book.stock_quantity} in stock
                                        </div>
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
        
        chartContainer.innerHTML = chartHTML;
    }

    function updateCategoryRevenueChart(categoryRevenue) {
        const chartContainer = document.getElementById('categoryRevenueChart');
        
        if (!chartContainer) return;
        
        if (!categoryRevenue || categoryRevenue.length === 0) {
            chartContainer.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;">No category data available</div>';
            return;
        }
        
        const colors = ['#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6', '#1abc9c', '#d35400', '#34495e', '#16a085', '#8e44ad'];
        const maxRevenue = Math.max(...categoryRevenue.map(c => c.total_revenue));
        const totalRevenue = categoryRevenue.reduce((sum, cat) => sum + cat.total_revenue, 0);
        
        const chartHTML = `
            <div style="padding: 20px; height: 100%;">
                <div style="display: flex; height: 200px; margin-bottom: 20px;">
                    <!-- Bar chart -->
                    <div style="flex: 2; display: flex; align-items: flex-end; gap: 10px; padding-right: 20px;">
                        ${categoryRevenue.map((category, index) => {
                            const height = maxRevenue > 0 ? (category.total_revenue / maxRevenue * 100) : 0;
                            const color = colors[index % colors.length];
                            const percentage = totalRevenue > 0 ? (category.total_revenue / totalRevenue * 100) : 0;
                            
                            return `
                                <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                                    <div style="width: 80%; background: linear-gradient(to top, ${color}, ${color}80); 
                                               height: ${height}%; border-radius: 5px 5px 0 0; position: relative;"
                                               title="${category.category_name}: RM ${category.total_revenue.toFixed(2)} (${percentage.toFixed(1)}%)">
                                        <div style="position: absolute; top: -25px; left: 50%; transform: translateX(-50%); 
                                                   font-size: 0.7rem; color: #666; white-space: nowrap;">
                                            ${percentage.toFixed(1)}%
                                        </div>
                                    </div>
                                    <div style="font-size: 0.7rem; margin-top: 5px; color: #666; text-align: center; 
                                               height: 40px; overflow: hidden; line-height: 1.2;">
                                        ${category.category_name}
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                    
                    <!-- Summary -->
                    <div style="flex: 1; background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="color: var(--primary); margin-bottom: 15px;">Summary</h4>
                        <div style="font-size: 1.5rem; font-weight: bold; color: var(--success); margin-bottom: 10px;">
                            RM ${totalRevenue.toFixed(2)}
                        </div>
                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 15px;">
                            Total Revenue
                        </div>
                        <div style="font-size: 0.8rem; color: #666;">
                            ${categoryRevenue.length} categories
                        </div>
                    </div>
                </div>
                
                <!-- Category details -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    ${categoryRevenue.slice(0, 4).map((category, index) => `
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; border-left: 4px solid ${colors[index % colors.length]};">
                            <div style="font-weight: bold; font-size: 0.9rem; margin-bottom: 5px;">${category.category_name}</div>
                            <div style="color: var(--success); font-weight: bold;">
                                RM ${category.total_revenue.toFixed(2)}
                            </div>
                            <div style="font-size: 0.8rem; color: #666; margin-top: 2px;">
                                ${category.order_count} orders • ${category.total_quantity} items
                            </div>
                            <div style="font-size: 0.7rem; color: #999; margin-top: 2px;">
                                ${category.customer_count} customers
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
        
        chartContainer.innerHTML = chartHTML;
    }

    function showAnalyticsError(error) {
        const containers = ['salesTrendChart', 'topBooksChart', 'categoryRevenueChart'];
        containers.forEach(containerId => {
            const container = document.getElementById(containerId);
            if (container) {
                container.innerHTML = `<div style="text-align: center; padding: 40px; color: var(--danger);">${error}</div>`;
            }
        });
    }

    // ============ ADMIN MANAGEMENT FUNCTIONS ============
    
    async function loadAdminManagementData() {
        if (!isSuperAdmin) {
            showNotification('Access denied. Super Admin only.', 'error');
            return;
        }
        
        try {
            const response = await fetch(API_ENDPOINTS.getAdmins);
            const data = await response.json();
            
            if (data.success) {
                currentAdmins = data.admins || [];
                updateAdminStats(currentAdmins);
                displayAdmins(currentAdmins);
            } else {
                document.getElementById('adminsTable').innerHTML = '<tr><td colspan="8" class="error">Error loading admin data: ' + (data.error || 'Unknown error') + '</td></tr>';
            }
        } catch (error) {
            console.error('Error loading admin data:', error);
            document.getElementById('adminsTable').innerHTML = '<tr><td colspan="8" class="error">Error loading admin data</td></tr>';
        }
    }

    function updateAdminStats(admins) {
        const totalAdmins = admins.length;
        const superadminCount = admins.filter(admin => admin.role === 'superadmin').length;
        const adminCount = admins.filter(admin => admin.role === 'admin').length;
       
        document.getElementById('totalAdmins').textContent = totalAdmins;
        document.getElementById('superadminCount').textContent = superadminCount;
        document.getElementById('adminCount').textContent = adminCount;
    }

    function displayAdmins(admins) {
        const adminsTable = document.getElementById('adminsTable');
        
        if (admins.length === 0) {
            adminsTable.innerHTML = '<tr><td colspan="7" class="loading">No admins found. Add your first admin!</td></tr>';
            return;
        }

        adminsTable.innerHTML = admins.map(admin => {
            const roleInfo = ADMIN_ROLE_MAP[admin.role] || { text: admin.role, class: 'role-admin' };
            
            let lastLogin = 'Never';
            if (admin.last_login) {
                try {
                    lastLogin = new Date(admin.last_login).toLocaleString();
                } catch (e) {
                    lastLogin = admin.last_login;
                }
            } else if (admin.last_login_time) {
                try {
                    lastLogin = new Date(admin.last_login_time).toLocaleString();
                } catch (e) {
                    lastLogin = admin.last_login_time;
                }
            }
            
            let createdDate = 'N/A';
            if (admin.created_at) {
                try {
                    createdDate = new Date(admin.created_at).toLocaleDateString();
                } catch (e) {
                    createdDate = admin.created_at;
                }
            }
            
            return `
            <tr>
                <td>${admin.auto_id || admin.id || admin.admin_id}</td>
                <td><strong>${admin.username}</strong></td>
                <td>${admin.email || 'N/A'}</td>
                <td>
                    <span class="admin-role ${roleInfo.class}">
                        ${roleInfo.text}
                    </span>
                </td>
                <td>${createdDate}</td>
                <td>${lastLogin}</td>
                <td class="action-buttons">
                    <button class="btn btn-sm btn-primary" onclick="viewAdminDetails(${admin.auto_id || admin.id || admin.admin_id})">
                        👁️ View
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="editAdmin(${admin.auto_id || admin.id || admin.admin_id})">
                        ✏️ Edit
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteAdmin(${admin.auto_id || admin.id || admin.admin_id}, '${admin.username.replace(/'/g, "\\'")}')">
                        🗑️ Delete
                    </button>
                    <button class="btn btn-sm btn-info" onclick="openSendPasswordModal(${admin.auto_id || admin.id || admin.admin_id}, '${admin.username.replace(/'/g, "\\'")}', '${(admin.email || '').replace(/'/g, "\\'")}')">
                        📧 Send Password
                    </button>
                </td>
            </tr>
            `;
        }).join('');
    }
    
    function searchAdmins() {
        if (lastSearchTimeout) {
            clearTimeout(lastSearchTimeout);
        }
        
        lastSearchTimeout = setTimeout(function() {
            const searchTerm = document.getElementById('adminSearch').value.toLowerCase();
            
            if (searchTerm.length < 2) {
                displayAdmins(currentAdmins);
                return;
            }
            
            const filteredAdmins = currentAdmins.filter(admin => 
                admin.username.toLowerCase().includes(searchTerm) ||
                (admin.email && admin.email.toLowerCase().includes(searchTerm))
            );
            
            displayAdmins(filteredAdmins);
        }, 300);
    }

    function filterAdmins() {
        const roleFilter = document.getElementById('adminRoleFilter').value;
        const statusFilter = document.getElementById('adminStatusFilter').value;
        
        let filteredAdmins = currentAdmins;
        
        if (roleFilter !== 'all') {
            filteredAdmins = filteredAdmins.filter(admin => admin.role === roleFilter);
        }
        
        if (statusFilter !== 'all') {
            filteredAdmins = filteredAdmins.filter(admin => admin.status === statusFilter);
        }
        
        displayAdmins(filteredAdmins);
    }

    // ============ ADMIN MODAL FUNCTIONS ============
    
    async function openAddAdminModal() {
        if (!isSuperAdmin) {
            showNotification('Access denied. Super Admin only.', 'error');
            return;
        }
        
        console.log('Opening add admin modal');
        
        document.getElementById('adminModalTitle').textContent = 'Add New Admin';
        document.getElementById('adminForm').reset();
        document.getElementById('adminId').value = '';
        document.getElementById('adminRole').value = 'admin';
        
        const permissionsGrid = document.getElementById('permissionsGrid');
        if (permissionsGrid) {
            permissionsGrid.innerHTML = '<div class="loading">Loading permissions...</div>';
        }
        
        await loadPermissions();
        
        setTimeout(() => {
            const basicPermissions = ['view_dashboard', 'view_books', 'view_customers', 'manage_orders'];
            basicPermissions.forEach(permKey => {
                const checkbox = document.querySelector(`input[value="${permKey}"]`);
                if (checkbox) {
                    checkbox.checked = true;
                    console.log('Auto-checked permission:', permKey);
                } else {
                    console.log('Permission checkbox not found:', permKey);
                }
            });
            
            togglePermissions();
        }, 100);
        
        document.getElementById('adminModal').style.display = 'flex';
        console.log('Admin modal opened');
    }

    // ============ PERMISSIONS SELECT/DESELECT FUNCTIONS ============

    function selectAllPermissions() {
        console.log('Selecting all permissions');
        const checkboxes = document.querySelectorAll('#permissionsGrid input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        updatePermissionsCount();
        showNotification(`All ${checkboxes.length} permissions selected`, 'success');
    }

    function deselectAllPermissions() {
        console.log('Deselecting all permissions');
        const checkboxes = document.querySelectorAll('#permissionsGrid input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        updatePermissionsCount();
        showNotification(`All permissions deselected`, 'warning');
    }

    // ============ PERMISSIONS FUNCTIONS ============

    async function loadPermissions() {
        console.log('Loading permissions...');
        
        try {
            const response = await fetch(API_ENDPOINTS.getPermissions);
            console.log('Permissions response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('Parsed permissions data:', data);
            
            if (data.success) {
                currentPermissions = data.permissions || [];
                console.log('Permissions loaded successfully:', currentPermissions.length, 'permissions');
                displayPermissions(currentPermissions);
            } else {
                console.error('API returned error:', data.error);
                fallbackToDefaultPermissions();
            }
        } catch (error) {
            console.error('Error loading permissions:', error);
            fallbackToDefaultPermissions();
        }
    }

    function fallbackToDefaultPermissions() {
        console.log('Using fallback permissions');
        currentPermissions = [
            { 
                permission_id: 1, 
                permission_key: 'manage_admins', 
                description: 'Can manage other admin users', 
                category: 'Administration' 
            },
            { 
                permission_id: 2, 
                permission_key: 'manage_books', 
                description: 'Manage Books (Add/Edit/Delete)', 
                category: 'Books' 
            },
            { 
                permission_id: 3, 
                permission_key: 'view_books', 
                description: 'Can view books only', 
                category: 'Inventory' 
            },
            { 
                permission_id: 4, 
                permission_key: 'manage_categories', 
                description: 'Manage Book Categories', 
                category: 'Categories' 
            },
            { 
                permission_id: 5, 
                permission_key: 'manage_orders', 
                description: 'View and Update Orders', 
                category: 'Orders' 
            },
            { 
                permission_id: 6, 
                permission_key: 'manage_customers', 
                description: 'Can manage customer accounts', 
                category: 'Customer Service' 
            },
            { 
                permission_id: 7, 
                permission_key: 'view_customers', 
                description: 'Can view customer information only', 
                category: 'Customer Service' 
            },
            { 
                permission_id: 8, 
                permission_key: 'view_reports', 
                description: 'Can view sales and inventory reports', 
                category: 'Reporting' 
            },
            { 
                permission_id: 9, 
                permission_key: 'manage_discounts', 
                description: 'Can create and manage discounts', 
                category: 'Sales' 
            },
            { 
                permission_id: 10, 
                permission_key: 'manage_preorders', 
                description: 'Can manage pre-orders', 
                category: 'Sales' 
            },
            { 
                permission_id: 11, 
                permission_key: 'view_dashboard', 
                description: 'Can view admin dashboard', 
                category: 'General' 
            },
            { 
                permission_id: 12, 
                permission_key: 'manage_settings', 
                description: 'Can manage system settings', 
                category: 'Administration' 
            },
            { 
                permission_id: 18, 
                permission_key: 'manage_inventory', 
                description: 'Update Stock Levels', 
                category: 'Inventory' 
            },
            { 
                permission_id: 19, 
                permission_key: 'view_analytics', 
                description: 'View Sales Reports', 
                category: 'Analytics' 
            }
        ];
        
        displayPermissions(currentPermissions);
    }

    // ============ PERMISSIONS DISPLAY FUNCTION ============

    function displayPermissions(permissions) {
        const permissionsGrid = document.getElementById('permissionsGrid');
        
        if (!permissionsGrid) {
            console.error('Permissions grid element not found!');
            return;
        }
        
        if (!permissions || permissions.length === 0) {
            permissionsGrid.innerHTML = '<div class="loading">No permissions defined</div>';
            return;
        }
        
        console.log('Displaying', permissions.length, 'permissions');
        
        const groupedPermissions = {};
        permissions.forEach(permission => {
            const category = permission.category || 'General';
            if (!groupedPermissions[category]) {
                groupedPermissions[category] = [];
            }
            groupedPermissions[category].push(permission);
        });
        
        const html = Object.keys(groupedPermissions).map(category => `
            <div class="permission-category">
                <h4>${category} (${groupedPermissions[category].length})</h4>
                ${groupedPermissions[category].map(perm => {
                    if (perm.permission_key === 'manage_admins') {
                        return '';
                    }
                    return `
                        <div class="permission-item">
                            <input type="checkbox" 
                                   id="perm_${perm.permission_id || perm.permission_key}" 
                                   name="permissions" 
                                   value="${perm.permission_key}"
                                   ${perm.permission_key === 'view_dashboard' ? 'checked' : ''}>
                            <label for="perm_${perm.permission_id || perm.permission_key}">
                                ${perm.description || perm.permission_key}
                            </label>
                        </div>
                    `;
                }).join('')}
            </div>
        `).join('');
        
        permissionsGrid.innerHTML = html;
        console.log('Permissions displayed in grid');
        
        setTimeout(() => {
            updatePermissionsCount();
        }, 100);
    }

    function updatePermissionsCount() {
        const checkboxes = document.querySelectorAll('#permissionsGrid input[type="checkbox"]');
        const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
        const totalCount = checkboxes.length;
        
        const headerElement = document.querySelector('#permissionsSection h4');
        if (headerElement) {
            headerElement.innerHTML = `🔑 Permissions (${checkedCount}/${totalCount} selected)`;
        }
    }

    function setupPermissionsListeners() {
        const permissionsGrid = document.getElementById('permissionsGrid');
        if (permissionsGrid) {
            permissionsGrid.addEventListener('change', function(e) {
                if (e.target.type === 'checkbox') {
                    updatePermissionsCount();
                }
            });
        }
    }

    function togglePermissions() {
        const role = document.getElementById('adminRole').value;
        const permissionsSection = document.getElementById('permissionsSection');
        
        console.log('Role changed to:', role);
        console.log('Permissions section:', permissionsSection);
        
        if (role === 'superadmin') {
            if (permissionsSection) {
                permissionsSection.style.display = 'none';
                console.log('Hiding permissions section for superadmin');
            }
        } else {
            if (permissionsSection) {
                permissionsSection.style.display = 'block';
                console.log('Showing permissions section for admin');
            }
        }
    }

    function closeAdminModal() {
        document.getElementById('adminModal').style.display = 'none';
    }

    async function saveAdmin() {
        if (!isSuperAdmin) {
            showNotification('Access denied. Super Admin only.', 'error');
            return;
        }
        
        const adminData = {
            username: document.getElementById('adminUsername').value.trim(),
            email: document.getElementById('adminEmail').value.trim(),
            role: document.getElementById('adminRole').value,
        };
        
        const adminId = document.getElementById('adminId').value;
        const isEditing = !!adminId;
        
        const apiEndpoint = isEditing ? API_ENDPOINTS.updateAdmin : API_ENDPOINTS.saveAdmin;
        
        if (isEditing) {
            adminData.admin_id = adminId;
        }
        
        if (adminData.role === 'admin') {
            const selectedPermissions = [];
            document.querySelectorAll('#permissionsGrid input[type="checkbox"]:checked').forEach(checkbox => {
                selectedPermissions.push({
                    permission_key: checkbox.value
                });
            });
            
            if (selectedPermissions.length === 0) {
                showNotification('Please select at least one permission for the admin', 'warning');
                return;
            }
            
            adminData.permissions = selectedPermissions;
        }

        if (!adminData.username) {
            showNotification('Please enter a username', 'warning');
            return;
        }

        if (isEditing && currentAdminInfo && currentAdminInfo.admin_id == adminId && adminData.username !== currentAdminInfo.username) {
            if (!confirm('You are changing your own username. You may need to login again. Continue?')) {
                return;
            }
        }

        const saveButton = document.querySelector('#adminModal .modal-footer .btn-primary');
        const originalText = saveButton.textContent;
        saveButton.innerHTML = '<span class="spinner"></span> Saving...';
        saveButton.disabled = true;

        try {
            const response = await fetch(apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(adminData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (isEditing) {
                    showNotification(`Admin "${adminData.username}" updated successfully!`, 'success');
                } else {
                    const passwordInfo = result.generated_password ? `
                        <div style="background: #e8f5e8; border: 1px solid #27ae60; border-radius: 6px; padding: 15px; margin-top: 10px;">
                            <strong>📝 IMPORTANT: Save this password!</strong><br>
                            Username: <strong>${adminData.username}</strong><br>
                            Password: <strong style="color: #e74c3c;">${result.generated_password}</strong><br>
                            <small style="color: #666;">Share this password securely with the new admin.</small>
                        </div>
                    ` : '';
                    
                    showNotification(`Admin created successfully! ${passwordInfo}`, 'success', 10000);
                    
                    if (adminData.email) {
                        setTimeout(() => {
                            openSendPasswordModal(result.admin_id, adminData.username, adminData.email);
                        }, 1000);
                    }
                }
                
                closeAdminModal();
                loadAdminManagementData();
                
            } else {
                if (result.error && result.error.toLowerCase().includes('already exists') || 
                    result.error.toLowerCase().includes('username already')) {
                    showNotification(`Cannot save: ${result.error}`, 'error');
                } else {
                    showNotification(isEditing ? 'Error updating admin: ' : 'Error creating admin: ' + result.error, 'error');
                }
            }
        } catch (error) {
            console.error('Error saving admin:', error);
            showNotification('Error saving admin. Please try again.', 'error');
        } finally {
            saveButton.innerHTML = originalText;
            saveButton.disabled = false;
        }
    }

    function closeAdminDetailsModal() {
        document.getElementById('adminDetailsModal').style.display = 'none';
    }

    function closeSendPasswordModal() {
        document.getElementById('sendPasswordModal').style.display = 'none';
    }

    // ============ ADMIN DETAILS FUNCTIONS ============

    async function viewAdminDetails(adminId) {
        if (!isSuperAdmin) {
            showNotification('Access denied. Super Admin only.', 'error');
            return;
        }
        
        try {
            const response = await fetch(`${API_ENDPOINTS.getAdminDetail}?admin_id=${adminId}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                showAdminDetailsModal(data.admin || data.data);
            } else {
                showNotification('Error loading admin details: ' + (data.error || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error('Error loading admin details:', error);
            showNotification('Error loading admin details. Please try again.', 'error');
        }
    }

    function showAdminDetailsModal(admin) {
        if (!admin) {
            showNotification('No admin data available', 'error');
            return;
        }
        
        const roleInfo = ADMIN_ROLE_MAP[admin.role] || { text: admin.role, class: 'role-admin' };
        
        const formatDate = (dateString) => {
            if (!dateString) return 'N/A';
            try {
                return new Date(dateString).toLocaleString();
            } catch (e) {
                return dateString;
            }
        };
        
        document.getElementById('adminDetailsTitle').textContent = `Admin Details - ${admin.username}`;
        
        document.getElementById('adminDetailsBody').innerHTML = `
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4 style="color: var(--primary); margin-bottom: 10px;">Basic Information</h4>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                        <div style="margin-bottom: 8px;">
                            <strong>Admin ID:</strong> ${admin.auto_id || admin.id || admin.admin_id}
                        </div>
                        <div style="margin-bottom: 8px;">
                            <strong>Username:</strong> ${admin.username}
                        </div>
                        <div style="margin-bottom: 8px;">
                            <strong>Email:</strong> ${admin.email || 'Not set'}
                        </div>
                        <div style="margin-bottom: 8px;">
                            <strong>Role:</strong> 
                            <span class="admin-role ${roleInfo.class}">
                                ${roleInfo.text}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h4 style="color: var(--primary); margin-bottom: 10px;">Account Information</h4>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                        <div style="margin-bottom: 8px;">
                            <strong>Created:</strong> ${formatDate(admin.created_at)}
                        </div>
                        <div style="margin-bottom: 8px;">
                            <strong>Last Updated:</strong> ${formatDate(admin.updated_at) || 'N/A'}
                        </div>
                        <div style="margin-bottom: 8px;">
                            <strong>Last Login:</strong> ${formatDate(admin.last_login) || 'Never'}
                        </div>
                        <div style="margin-bottom: 8px;">
                            <strong>Login Count:</strong> ${admin.login_count || '0'}
                        </div>
                    </div>
                </div>
                
                <div style="grid-column: 1 / -1;">
                    <h4 style="color: var(--primary); margin-bottom: 10px;">Permissions</h4>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                        ${admin.permissions && admin.permissions.length > 0 ? 
                            `<ul style="margin: 0; padding-left: 20px;">
                                ${admin.permissions.map(perm => 
                                    `<li>${perm.description || perm.permission_key}</li>`
                                ).join('')}
                            </ul>` : 
                            '<div style="color: #666; font-style: italic;">No specific permissions assigned</div>'
                        }
                    </div>
                </div>
                
                ${admin.note ? `
                <div style="grid-column: 1 / -1;">
                    <h4 style="color: var(--primary); margin-bottom: 10px;">Notes</h4>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; white-space: pre-line;">
                        ${admin.note}
                    </div>
                </div>
                ` : ''}
            </div>
        `;
        
        document.getElementById('editAdminBtn').setAttribute('onclick', `editAdmin(${admin.auto_id || admin.id || admin.admin_id})`);
        document.getElementById('deleteAdminBtn').setAttribute('onclick', `deleteAdmin(${admin.auto_id || admin.id || admin.admin_id}, '${admin.username.replace(/'/g, "\\'")}')`);
        
        document.getElementById('adminDetailsModal').style.display = 'flex';
    }

    async function editAdmin(adminId) {
        if (!isSuperAdmin) {
            showNotification('Access denied. Super Admin only.', 'error');
            return;
        }
        
        try {
            const response = await fetch(`${API_ENDPOINTS.getAdminDetail}?admin_id=${adminId}`);
            const data = await response.json();
            
            if (data.success) {
                const admin = data.admin || data.data;
                
                document.getElementById('adminModalTitle').textContent = 'Edit Admin';
                document.getElementById('adminId').value = admin.auto_id || admin.id || admin.admin_id;
                document.getElementById('adminUsername').value = admin.username;
                document.getElementById('adminEmail').value = admin.email || '';
                document.getElementById('adminRole').value = admin.role;
               
                await loadPermissions();
                
                if (admin.permissions) {
                    setTimeout(() => {
                        document.querySelectorAll('#permissionsGrid input[type="checkbox"]').forEach(checkbox => {
                            const permKey = checkbox.value;
                            const hasPermission = admin.permissions.some(perm => 
                                perm.permission_key === permKey || 
                                (typeof perm === 'object' && perm.permission_key === permKey)
                            );
                            checkbox.checked = hasPermission;
                        });
                    }, 100);
                }
                
                togglePermissions();
                document.getElementById('adminModal').style.display = 'flex';
            } else {
                showNotification('Error loading admin data: ' + data.error, 'error');
            }
        } catch (error) {
            console.error('Error loading admin:', error);
            showNotification('Error loading admin data', 'error');
        }
    }

    // ============ ADMIN DELETE FUNCTION ============
    async function deleteAdmin(adminId, adminName, adminRole) {
        console.log('Deleting admin:', { adminId, adminName, adminRole });
        
        if (!currentAdminInfo || currentAdminInfo.role !== 'superadmin') {
            showNotification('Access denied. Super Admin only.', 'error');
            return;
        }
        
        let confirmMessage = `Are you sure you want to delete admin "${adminName}"?\n\nThis action cannot be undone!`;
        
        if (adminRole === 'superadmin') {
            confirmMessage = `⚠️ WARNING: This is a SUPER ADMIN account!\n\nAre you sure you want to delete super admin "${adminName}"?\n\nThis may affect system access!`;
        }
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        const deleteData = {
            admin_id: adminId
        };
        
        try {
            console.log('Sending delete request to:', API_ENDPOINTS.deleteAdmin);
            console.log('Delete data:', deleteData);
            
            const response = await fetch(API_ENDPOINTS.deleteAdmin, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(deleteData)
            });
            
            console.log('Response status:', response.status);
            
            const result = await response.json();
            console.log('Response result:', result);
            
            if (result.success) {
                showNotification(result.message || `Admin "${adminName}" deleted successfully!`, 'success');
                
                if (document.getElementById('adminDetailsModal').style.display === 'flex') {
                    closeAdminDetailsModal();
                }
                
                loadAdminManagementData();
                
            } else {
                if (result.requires_confirmation && adminRole === 'superadmin') {
                    const finalConfirm = confirm(`⚠️ FINAL CONFIRMATION: SUPER ADMIN DELETION!\n\nAdmin "${result.admin_name || adminName}" is a super admin.\n\nClick OK to confirm deletion or Cancel to abort.`);
                    
                    if (finalConfirm) {
                        deleteData.confirm_superadmin = true;
                        
                        const confirmResponse = await fetch(API_ENDPOINTS.deleteAdmin, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(deleteData)
                        });
                        
                        const confirmResult = await confirmResponse.json();
                        
                        if (confirmResult.success) {
                            showNotification(`Super Admin "${adminName}" deleted successfully!`, 'success');
                            closeAdminDetailsModal();
                            loadAdminManagementData();
                        } else {
                            showNotification('Error: ' + (confirmResult.error || 'Unknown error'), 'error');
                        }
                    }
                } else {
                    showNotification('Error: ' + (result.error || 'Unknown error'), 'error');
                }
            }
            
        } catch (error) {
            console.error('Error deleting admin:', error);
            showNotification('Delete failed. Please check your connection.', 'error');
        }
    }

    function closeAdminDetailsModal() {
        document.getElementById('adminDetailsModal').style.display = 'none';
    }
    
    async function sendPasswordEmail() {
        const adminId = document.getElementById('passwordAdminId').value;
        const email = document.getElementById('passwordEmail').value;
        
        if (!email || !validateEmail(email)) {
            showNotification('Please enter a valid email address', 'warning');
            return;
        }
        
        if (!adminId) {
            showNotification('Admin ID is required', 'warning');
            return;
        }
        
        const sendButton = document.querySelector('#sendPasswordModal .btn-primary');
        const originalText = sendButton.textContent;
        sendButton.innerHTML = '<span class="spinner"></span> Sending...';
        sendButton.disabled = true;
        
        try {
            const response = await fetch(API_ENDPOINTS.sendPasswordEmail, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    admin_id: parseInt(adminId),
                    email: email
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                const passwordMessage = `
                    <div style="background: #e8f5e8; border: 1px solid #27ae60; border-radius: 6px; padding: 15px; margin-top: 10px;">
                        <strong>📧 Password Reset Successful!</strong><br>
                        New password: <strong style="color: #e74c3c;">${result.generated_password}</strong><br>
                        <small style="color: #666;">
                            ${result.email_sent_to ? `Sent to: ${result.email_sent_to}` : ''}<br>
                            Share this password securely with the admin.
                        </small>
                    </div>
                `;
                
                showNotification(`Password reset completed! ${passwordMessage}`, 'success', 10000);
                closeSendPasswordModal();
            } else {
                showNotification('Error sending password: ' + result.error, 'error');
            }
        } catch (error) {
            console.error('Error sending password email:', error);
            showNotification('Error sending password email. Please try again.', 'error');
        } finally {
            sendButton.innerHTML = originalText;
            sendButton.disabled = false;
        }
    }

    function openSendPasswordModal(adminId, adminName, adminEmail) {
        document.getElementById('passwordAdminId').value = adminId;
        document.getElementById('passwordAdminName').value = adminName;
        
        const emailInput = document.getElementById('passwordEmail');
        if (adminEmail && adminEmail !== 'Not set' && adminEmail !== 'N/A') {
            emailInput.value = adminEmail;
        } else {
            emailInput.value = '';
            emailInput.placeholder = 'Enter email address for password reset';
        }
        
        document.getElementById('sendPasswordModal').style.display = 'flex';
    }

    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // ============ ISBN FUNCTIONS ============
    
    function generateRandomISBN() {
        const prefix = "978";
        let middleDigits = "";
        for (let i = 0; i < 10; i++) {
            middleDigits += Math.floor(Math.random() * 10);
        }
        return `${prefix}-${middleDigits}`;
    }

    function isValidISBN(isbn) {
        if (!isbn || typeof isbn !== 'string') return false;
        const isbnPattern = /^978-\d{10}$/;
        return isbnPattern.test(isbn);
    }

    function regenerateISBN() {
        const newISBN = generateRandomISBN();
        setGeneratedISBN(newISBN);
        showNotification(`New ISBN generated: ${newISBN}`, 'info');
    }

    function setGeneratedISBN(isbn) {
        let cleanISBN = String(isbn || '').trim();
        
        if (!isValidISBN(cleanISBN)) {
            console.warn('ISBN format invalid, regenerating:', cleanISBN);
            const newISBN = generateRandomISBN();
            setGeneratedISBN(newISBN);
            return;
        }
        
        currentGeneratedISBN = cleanISBN;
        const isbnInput = document.getElementById('bookISBN');
        isbnInput.value = cleanISBN;
        isbnInput.classList.add('isbn-generated');
        
        const statusLabel = document.getElementById('isbnStatusLabel');
        if (statusLabel) {
            statusLabel.textContent = 'Auto-generated';
            statusLabel.style.background = '#3498db';
        }
    }

    function formatISBN(rawISBN) {
        if (isValidISBN(rawISBN)) {
            return rawISBN;
        }
        
        const cleanDigits = rawISBN.replace(/\D/g, '');
        
        if (cleanDigits.length !== 13) {
            return rawISBN;
        }
        
        return `${cleanDigits.substring(0, 3)}-${cleanDigits.substring(3, 13)}`;
    }

    // ============ NOTIFICATION FUNCTIONS ============
    
    function showNotification(message, type = 'info', duration = 3000) {
        const existingNotification = document.querySelector('.custom-notification');
        if (existingNotification) existingNotification.remove();
        
        const notification = document.createElement('div');
        notification.className = `custom-notification ${type}`;
        notification.innerHTML = `
            <span>${type === 'success' ? '✅' : type === 'error' ? '❌' : type === 'warning' ? '⚠️' : 'ℹ️'}</span>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, duration);
    }

    // ============ CATEGORY FUNCTIONS ============
    
    async function loadCategoriesData() {
        try {
            const [categoriesResponse, booksResponse] = await Promise.all([
                fetch(API_ENDPOINTS.categories),
                fetch(API_ENDPOINTS.books)
            ]);
            
            const categoriesData = await categoriesResponse.json();
            const booksData = await booksResponse.json();
            
            if (booksData.success) {
                currentBooks = booksData.books || booksData.data || [];
            }
            
            if (categoriesData.success) {
                const categories = categoriesData.categories || categoriesData.data || [];
                displayCategories(categories);
                updateCategoryManagementSection();
            }
        } catch (error) {
            console.error('Error loading categories:', error);
            const categoriesTable = document.getElementById('categoriesTable');
            categoriesTable.innerHTML = '<tr><td colspan="4" class="error">Error loading categories</td></tr>';
        }
    }

    function displayCategories(categories) {
        const categoriesTable = document.getElementById('categoriesTable');
        
        if (!categories || categories.length === 0) {
            categoriesTable.innerHTML = '<tr><td colspan="4" class="loading">No categories found. Add your first category!</td></tr>';
            return;
        }
        
        categoriesTable.innerHTML = categories.map(category => {
            const bookCount = currentBooks ? 
                currentBooks.filter(book => book.category_id == category.category_id).length : 0;
            
            return `
                <tr>
                    <td>${category.category_id}</td>
                    <td>
                        <strong>${category.category_name}</strong>
                        ${category.description ? `<div style="font-size: 0.9em; color: #666; margin-top: 4px;">${category.description}</div>` : ''}
                    </td>
                    <td>
                        <span class="${bookCount > 0 ? 'in-stock' : 'out-of-stock'}" style="display: inline-block;">
                            ${bookCount} books
                        </span>
                    </td>
                    <td class="action-buttons">
                        <button class="btn btn-sm btn-warning" onclick="openEditCategoryModal(${category.category_id}, '${category.category_name.replace(/'/g, "\\'")}', '${(category.description || '').replace(/'/g, "\\'").replace(/"/g, '&quot;')}')">
                            ✏️ Edit
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteCategory(${category.category_id})" ${bookCount > 0 ? 'disabled title="Cannot delete category with books"' : ''}>
                            🗑️ Delete
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // ============ CATEGORY MODAL FUNCTIONS ============
    
    async function openAddCategoryModal() {
        // 检查权限
        if (!hasPermission('manage_categories') && !isSuperAdmin) {
            showNotification('You do not have permission to add categories', 'error');
            return;
        }
        
        const modalHTML = `
            <div class="modal" id="addCategoryModal" style="display: flex;">
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header">
                        <h3>➕ Add New Category</h3>
                        <button class="close" onclick="closeAddCategoryModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="categoryForm">
                            <div class="form-group">
                                <label for="categoryName">Category Name *</label>
                                <input type="text" id="categoryName" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="categoryDescription">Description</label>
                                <textarea id="categoryDescription" class="form-control" rows="3" placeholder="Optional description..."></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button class="btn" onclick="closeAddCategoryModal()">❌ Cancel</button>
                        <button class="btn btn-primary" onclick="saveCategory()">💾 Save Category</button>
                    </div>
                </div>
            </div>
        `;
        
        const existingModal = document.getElementById('addCategoryModal');
        if (existingModal) existingModal.remove();
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    function closeAddCategoryModal() {
        const modal = document.getElementById('addCategoryModal');
        if (modal) modal.remove();
    }

    async function saveCategory() {
        // 检查权限
        if (!hasPermission('manage_categories') && !isSuperAdmin) {
            showNotification('You do not have permission to save categories', 'error');
            return;
        }
        
        const categoryName = document.getElementById('categoryName').value;
        const categoryDescription = document.getElementById('categoryDescription').value;
        
        if (!categoryName.trim()) {
            showNotification('Please enter a category name', 'warning');
            return;
        }
        
        try {
            const response = await fetch(API_ENDPOINTS.addCategory, {
                method: 'POST',
                body: new URLSearchParams({
                    category_name: categoryName,
                    description: categoryDescription
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Category added successfully!', 'success');
                closeAddCategoryModal();
                await loadCategoriesData();
                loadDashboardData();
                
                if (document.getElementById('book-inventory').classList.contains('active')) {
                    loadCategoryFilter();
                }
            } else {
                showNotification('Error adding category: ' + result.error, 'error');
            }
        } catch (error) {
            console.error('Error adding category:', error);
            showNotification('Error adding category: ' + error.message, 'error');
        }
    }

    async function openEditCategoryModal(categoryId, categoryName, categoryDescription = '') {
        // 检查权限
        if (!hasPermission('manage_categories') && !isSuperAdmin) {
            showNotification('You do not have permission to edit categories', 'error');
            return;
        }
        
        const safeCategoryName = categoryName.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const safeDescription = (categoryDescription || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
        
        const modalHTML = `
            <div class="modal" id="editCategoryModal" style="display: flex;">
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header">
                        <h3>✏️ Edit Category</h3>
                        <button class="close" onclick="closeEditCategoryModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="editCategoryForm">
                            <input type="hidden" id="editCategoryId" value="${categoryId}">
                            <div class="form-group">
                                <label for="editCategoryName">Category Name *</label>
                                <input type="text" id="editCategoryName" class="form-control" value="${safeCategoryName}" required>
                            </div>
                            <div class="form-group">
                                <label for="editCategoryDescription">Description</label>
                                <textarea id="editCategoryDescription" class="form-control" rows="3">${safeDescription}</textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button class="btn" onclick="closeEditCategoryModal()">❌ Cancel</button>
                        <button class="btn btn-primary" onclick="updateCategory()">💾 Update Category</button>
                    </div>
                </div>
            </div>
        `;
        
        const existingModal = document.getElementById('editCategoryModal');
        if (existingModal) existingModal.remove();
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    function closeEditCategoryModal() {
        const modal = document.getElementById('editCategoryModal');
        if (modal) modal.remove();
    }

    async function updateCategory() {
        // 检查权限
        if (!hasPermission('manage_categories') && !isSuperAdmin) {
            showNotification('You do not have permission to update categories', 'error');
            return;
        }
        
        const categoryId = document.getElementById('editCategoryId').value;
        const categoryName = document.getElementById('editCategoryName').value;
        const categoryDescription = document.getElementById('editCategoryDescription').value;
        
        if (!categoryName.trim()) {
            showNotification('Please enter a category name', 'warning');
            return;
        }
        
        try {
            const response = await fetch(API_ENDPOINTS.updateCategory, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    category_id: categoryId,
                    category_name: categoryName,
                    description: categoryDescription
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Category updated successfully!', 'success');
                closeEditCategoryModal();
                await loadCategoriesData();
                loadBookInventoryData();
            } else {
                showNotification('Error updating category: ' + result.error, 'error');
            }
        } catch (error) {
            console.error('Error updating category:', error);
            showNotification('Error updating category', 'error');
        }
    }

    async function deleteCategory(categoryId) {
        // 检查权限
        if (!hasPermission('manage_categories') && !isSuperAdmin) {
            showNotification('You do not have permission to delete categories', 'error');
            return;
        }
        
        const bookCount = currentBooks ? 
            currentBooks.filter(book => book.category_id == categoryId).length : 0;
        
        if (bookCount > 0) {
            if (!confirm(`This category has ${bookCount} book(s).\n\nBooks in this category will be moved to "Uncategorized".\n\nAre you sure you want to delete this category?`)) {
                return;
            }
        } else {
            if (!confirm('Are you sure you want to delete this category?')) {
                return;
            }
        }
        
        try {
            const response = await fetch(API_ENDPOINTS.deleteCategory, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    category_id: categoryId
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Category deleted successfully!', 'success');
                await Promise.all([
                    loadCategoriesData(),
                    loadBookInventoryData()
                ]);
                loadDashboardData();
            } else {
                showNotification('Error deleting category: ' + result.error, 'error');
            }
        } catch (error) {
            console.error('Error deleting category:', error);
            showNotification('Error deleting category', 'error');
        }
    }
// ============ PDF REPORT FUNCTIONS ============

// 改进的generatePDFReport函数
// 在已有的JavaScript中添加PDF生成函数
async function generatePDFReport() {
    const reportType = document.getElementById('pdfReportType').value;
    const period = document.getElementById('analyticsPeriod').value;
    const customerId = document.getElementById('customerFilter').value;
    
    showNotification('🔄 Generating report...', 'info');
    
    try {
        const params = new URLSearchParams({
            report_type: reportType,
            period: period,
            customer_id: customerId,
            format: 'json'
        });
        
        // 如果是自定义日期范围
        if (period === 'custom') {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            if (startDate && endDate) {
                params.append('start_date', startDate);
                params.append('end_date', endDate);
            } else {
                showNotification('Please select custom date range', 'warning');
                return;
            }
        }
        
        const response = await fetch(`generate_report.php?${params}`);
        const data = await response.json();
        
        if (data.success && data.html_content) {
            // 在新窗口中打开HTML报告，供打印/保存为PDF
            const printWindow = window.open('', '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
            printWindow.document.write(data.html_content);
            printWindow.document.close();
            
            // 让用户手动点击打印
            showNotification('✅ Report generated. Click "Print / Save as PDF" button in the new window.', 'success', 5000);
        } else {
            throw new Error(data.error || 'Failed to generate report');
        }
    } catch (error) {
        console.error('Error generating report:', error);
        showNotification('❌ Error: ' + error.message, 'error');
    }
}
// 加载客户下拉列表
// 更新后的loadCustomerDropdown函数
async function loadCustomerDropdown() {
    try {
        const response = await fetch('get_customers_for_report.php');
        const data = await response.json();
        
        if (data.success) {
            const customerSelect = document.getElementById('customerFilter');
            customerSelect.innerHTML = '<option value="all">All Customers</option>';
            
            data.customers.forEach(customer => {
                const option = document.createElement('option');
                option.value = customer.customer_id;
                
                // 构建显示文本
                let displayText = customer.recipient_name || customer.full_name || customer.username;
                
                // 如果有用户名且与名称不同，添加用户名
                if (customer.username && customer.username !== displayText) {
                    displayText += ` (${customer.username})`;
                }
                
                // 如果有email，添加email
                if (customer.email && customer.email.trim()) {
                    displayText += ` - ${customer.email}`;
                }
                
                // 添加订单数量和消费金额
                if (customer.order_count > 0) {
                    displayText += ` - ${customer.order_count} orders`;
                    if (customer.total_spent > 0) {
                        displayText += ` (RM ${parseFloat(customer.total_spent).toFixed(2)})`;
                    }
                }
                
                option.textContent = displayText;
                option.setAttribute('data-email', customer.email || '');
                option.setAttribute('data-name', customer.recipient_name || '');
                option.setAttribute('data-username', customer.username || '');
                
                customerSelect.appendChild(option);
            });
            
            // 触发加载销售报告
            loadCustomerSalesReport();
            
        } else {
            console.error('Failed to load customers:', data.error);
            showNotification('Failed to load customer list: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error loading customers:', error);
        showNotification('Error loading customer list: ' + error.message, 'error');
    }
}

// 更新loadCustomerSalesReport函数
async function loadCustomerSalesReport() {
    const customerId = document.getElementById('customerFilter').value;
    const period = document.getElementById('analyticsPeriod').value;
    
    if (customerId === 'all') {
        loadSalesAnalytics(period);
        // 隐藏客户信息
        document.getElementById('customerInfo').style.display = 'none';
        document.getElementById('customerOrdersTableContainer').style.display = 'none';
        return;
    }
    
    try {
        // 加载客户信息
        const customerInfoResponse = await fetch(`get_customer_sales.php?customer_id=${customerId}`);
        const customerInfoData = await customerInfoResponse.json();
        
        if (customerInfoData.success) {
            displayCustomerInfo(customerInfoData.customer);
            document.getElementById('customerInfo').style.display = 'block';
            
            // 加载客户订单
            if (customerInfoData.orders && customerInfoData.orders.length > 0) {
                displayCustomerOrders(customerInfoData.orders);
                document.getElementById('customerOrdersTableContainer').style.display = 'block';
            } else {
                document.getElementById('customerOrdersTableContainer').style.display = 'none';
            }
        }
        
        // 加载销售报告数据
        const params = new URLSearchParams({
            customer_id: customerId,
            period: period
        });
        
        const response = await fetch(`sales_analytics.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            displaySalesAnalytics(data);
        } else {
            showNotification('Error loading sales report: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error loading customer sales report:', error);
        showNotification('Error loading customer report', 'error');
    }
}

// 新增：显示客户信息
function displayCustomerInfo(customer) {
    if (!customer) return;
    
    const customerInfoDiv = document.getElementById('customerInfo');
    customerInfoDiv.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <h3 id="customerName" style="color: var(--primary); margin-bottom: 10px;">
                    ${customer.recipient_name || customer.full_name || customer.username}
                    ${customer.username ? `<small style="color: #666; font-weight: normal;">(${customer.username})</small>` : ''}
                </h3>
                <div id="customerDetails" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    ${customer.email ? `<div><strong>Email:</strong> ${customer.email}</div>` : ''}
                    ${customer.phone ? `<div><strong>Phone:</strong> ${customer.phone}</div>` : ''}
                    ${customer.formatted_address ? `<div><strong>Address:</strong> ${customer.formatted_address}</div>` : ''}
                    ${customer.order_count !== undefined ? `<div><strong>Total Orders:</strong> ${customer.order_count}</div>` : ''}
                    ${customer.total_spent !== undefined ? `<div><strong>Total Spent:</strong> RM ${parseFloat(customer.total_spent).toFixed(2)}</div>` : ''}
                    ${customer.avg_order_value !== undefined ? `<div><strong>Avg Order Value:</strong> RM ${parseFloat(customer.avg_order_value).toFixed(2)}</div>` : ''}
                </div>
            </div>
            <button class="btn btn-sm" onclick="closeCustomerInfo()" style="margin-top: -5px;">
                <i>✕</i> Close
            </button>
        </div>
    `;
}

// 新增：关闭客户信息
function closeCustomerInfo() {
    document.getElementById('customerInfo').style.display = 'none';
    document.getElementById('customerOrdersTableContainer').style.display = 'none';
    document.getElementById('customerFilter').value = 'all';
    loadSalesAnalytics();
}

// 新增：显示客户订单
function displayCustomerOrders(orders) {
    const tableBody = document.getElementById('customerOrdersTable');
    
    if (!orders || orders.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="6" class="loading">No orders found for this customer</td></tr>';
        return;
    }
    
    tableBody.innerHTML = orders.map(order => {
        const statusInfo = ORDER_STATUS_MAP[order.status] || { text: order.status, class: 'status-confirmed' };
        
        return `
            <tr>
                <td><strong>#${order.order_id}</strong></td>
                <td>${order.order_date ? new Date(order.order_date).toLocaleDateString() : 'N/A'}</td>
                <td>
                    ${order.items_count || order.total_items || 0} items
                    ${order.total_quantity ? `<br><small style="color: #666;">${order.total_quantity} total</small>` : ''}
                </td>
                <td><strong>RM ${parseFloat(order.total_amount || 0).toFixed(2)}</strong></td>
                <td>
                    <span class="order-status ${statusInfo.class}">
                        ${statusInfo.text}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="viewOrderDetails(${order.order_id})">
                        👁️ View
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// 新增：导出客户订单
async function exportCustomerOrders() {
    const customerId = document.getElementById('customerFilter').value;
    if (customerId === 'all') {
        showNotification('Please select a specific customer first', 'warning');
        return;
    }
    
    try {
        const response = await fetch(`export_customer_orders.php?customer_id=${customerId}`);
        const data = await response.json();
        
        if (data.success) {
            // 在新窗口打开CSV或显示下载链接
            if (data.csv_url) {
                window.open(data.csv_url, '_blank');
                showNotification('Export successful!', 'success');
            } else if (data.csv_content) {
                // 创建下载链接
                const blob = new Blob([data.csv_content], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `customer_${customerId}_orders.csv`;
                a.click();
                window.URL.revokeObjectURL(url);
                showNotification('Export downloaded!', 'success');
            }
        } else {
            showNotification('Export failed: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error exporting orders:', error);
        showNotification('Export failed', 'error');
    }
}
    // Close modal when clicking outside
    window.onclick = function(event) {
        const bookModal = document.getElementById('bookModal');
        const stockModal = document.getElementById('stockModal');
        const orderDetailsModal = document.getElementById('orderDetailsModal');
        const adminModal = document.getElementById('adminModal');
        const adminDetailsModal = document.getElementById('adminDetailsModal');
        const sendPasswordModal = document.getElementById('sendPasswordModal');
        
        if (event.target === bookModal) closeModal();
        if (event.target === stockModal) closeStockModal();
        if (event.target === orderDetailsModal) closeOrderDetailsModal();
        if (event.target === adminModal) closeAdminModal();
        if (event.target === adminDetailsModal) closeAdminDetailsModal();
        if (event.target === sendPasswordModal) closeSendPasswordModal();
    }