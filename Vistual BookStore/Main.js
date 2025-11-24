// API endpoints
const API_ENDPOINTS = {
  checkLogin: 'check_login.php',
  books: 'get_book.php'
};

// 检查登录状态并更新UI
function checkLoginStatus() {
  fetch(API_ENDPOINTS.checkLogin, {
    credentials: 'include'
  })
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    })
    .then(data => {
      console.log('Login status data:', data); // 调试信息
      updateUserInterface(data);
      updateServicesBasedOnRole(data);
      updateAdminEntry(data); // 更新管理员入口显示
    })
    .catch(error => {
      console.error('Error checking login status:', error);
      // 如果check_login.php不存在，使用URL参数回退
      fallbackToURLParams();
    });
}

// 更新管理员入口显示
function updateAdminEntry(userData) {
  const adminEntry = document.getElementById('adminEntry');
  
  if (!adminEntry) return;
  
  console.log('Checking admin entry for role:', userData.role);
  
  if (userData.logged_in && userData.role === 'admin') {
    adminEntry.classList.remove('hidden');
    console.log('Admin entry displayed');
  } else {
    adminEntry.classList.add('hidden');
  }
}

// 管理员入口点击跟踪
function trackAdminEntry() {
  console.log('=== ADMIN PANEL ENTRY CLICKED ===');
  console.log('Admin user accessing admin panel');
  console.log('Redirecting to: admin_panel.html');
  console.log('================================');
  
  // 可以添加额外的跟踪逻辑，比如发送到分析服务
}

// 根据用户角色更新服务选项
// 根据登录状态更新服务显示
function updateServicesForLoggedInUser(userData) {
    const servicesContainer = document.getElementById('servicesContainer');
    
    if (userData.user_type === 'admin') {
        servicesContainer.innerHTML = `
            <div class="service-card" onclick="location.href='admin_panel.html'">
                <div class="service-icon">⚙️</div>
                <h3>Admin Panel</h3>
                <p>Manage books, inventory, orders, and system settings with full administrative privileges</p>
                <button class="service-btn">Access Admin Panel</button>
            </div>
            <div class="service-card" onclick="location.href='order_history.html'">
                <div class="service-icon">📦</div>
                <h3>Order History</h3>
                <p>View your order history and track your purchases with detailed information</p>
                <button class="service-btn">View Orders</button>
            </div>
            <div class="service-card" onclick="location.href='Searching.html'">
                <div class="service-icon">🔍</div>
                <h3>Search Books</h3>
                <p>Explore our vast collection and find your next favorite book</p>
                <button class="service-btn">Search Books</button>
            </div>
        `;
    } else if (userData.logged_in) {
        servicesContainer.innerHTML = `
            <div class="service-card" onclick="location.href='order_history.html'">
                <div class="service-icon">📦</div>
                <h3>My Orders</h3>
                <p>View your complete order history, track shipments, and manage your purchases</p>
                <button class="service-btn">View Order History</button>
            </div>
            <div class="service-card" onclick="location.href='Searching.html'">
                <div class="service-icon">🔍</div>
                <h3>Search Books</h3>
                <p>Discover new books from our extensive collection across all genres</p>
                <button class="service-btn">Search Books</button>
            </div>
            <div class="service-card" onclick="location.href='user_profile.html'">
                <div class="service-icon">👤</div>
                <h3>My Profile</h3>
                <p>Manage your account settings, personal information, and preferences</p>
                <button class="service-btn">View Profile</button>
            </div>
        `;
    } else {
        servicesContainer.innerHTML = `
            <div class="service-card" onclick="location.href='Searching.html'">
                <div class="service-icon">🔍</div>
                <h3>Search Books</h3>
                <p>Browse our extensive collection and find your next favorite read</p>
                <button class="service-btn">Search Books</button>
            </div>
            <div class="service-card" onclick="location.href='../Login/Login.html'">
                <div class="service-icon">🔐</div>
                <h3>Login</h3>
                <p>Sign in to access personalized features, order history, and exclusive deals</p>
                <button class="service-btn">Login Now</button>
            </div>
            <div class="service-card" onclick="location.href='user_profile.html'">
                <div class="service-icon">👤</div>
                <h3>My Account</h3>
                <p>Create an account or manage your profile to enjoy personalized services</p>
                <button class="service-btn">View Account</button>
            </div>
        `;
    }
}
// 更新用户界面
function updateUserInterface(userData) {
  const userInfo = document.getElementById('userInfo');
  const welcomeMessage = document.getElementById('welcomeMessage');
  
  if (!userInfo) return;

  if (userData.logged_in) {
    // 获取用户头像数据
    const userProfile = getUserAvatarData(userData.username);
    
    console.log('Updating user interface for:', userData.username, 'Role:', userData.role); // 调试信息
    
    // 根据用户角色决定个人资料链接
    let profileLink = 'user_profile.html';
    let displayText = userData.username;
    
    if (userData.role === 'admin') {
      profileLink = 'admin_panel.html'; // 管理员去管理员面板
      displayText = userData.username + ' (Admin)';
      console.log('Admin user detected, setting profile link to:', profileLink); // 调试信息
    }
    
    // 用户已登录，显示用户欢迎信息
    userInfo.innerHTML = `
      <div class="user-welcome">
        <span>Welcome, ${displayText}!</span>
        <div class="user-actions">
          <a href="${profileLink}">
            <button class="profile-button">👤 Profile</button>
          </a>
          ${userData.role !== 'admin' ? `
            <a href="order_history.html">
              <button class="orders-button">📦 Orders</button>
            </a>
          ` : ''}
          <a href="logout.php">
            <button class="logout-button">🚪 Logout</button>
          </a>
        </div>
      </div>
    `;
    
    // 显示欢迎消息
    if (welcomeMessage) {
      const roleText = userData.role === 'admin' ? ' (Administrator)' : '';
      welcomeMessage.innerHTML = `
        <h3>Welcome back, ${userData.username}${roleText}! 🎉</h3>
        <p>Ready to continue your reading journey? ${userData.role === 'admin' ? 'Access the admin panel to manage the store.' : 'Check out your order history or explore new books!'}</p>
      `;
      welcomeMessage.classList.remove('hidden');
      
      setTimeout(() => {
        welcomeMessage.classList.add('hidden');
      }, 5000);
    }
  } else {
    // 用户未登录，显示登录按钮
    userInfo.innerHTML = `
      <a href="../Login/Login.html">
        <button class="login-button">🔐 Login</button>
      </a>
    `;
    
    if (welcomeMessage) {
      welcomeMessage.classList.add('hidden');
    }
  }
}

// 链接点击跟踪函数
function trackLinkClick(linkName) {
  console.log('=== LINK CLICK DEBUG ===');
  console.log('Link clicked:', linkName);
  console.log('Expected URL:', window.location.origin + window.location.pathname.replace('Main.html', '') + linkName);
  console.log('Current page:', window.location.href);
  console.log('=====================');
}

// 获取用户头像数据
function getUserAvatarData(username) {
  const savedData = localStorage.getItem(`userProfile_${username}`);
  if (savedData) {
    const userData = JSON.parse(savedData);
    return {
      avatarType: userData.avatarType || 'emoji',
      avatarValue: userData.avatarValue || '👤',
      avatarImage: userData.avatarImage || null
    };
  }
  
  // 默认头像数据
  return {
    avatarType: 'emoji',
    avatarValue: '👤',
    avatarImage: null
  };
}

// 回退方案：如果check_login.php不存在，使用URL参数
function fallbackToURLParams() {
  const urlParams = new URLSearchParams(window.location.search);
  const loginSuccess = urlParams.get('login_success');
  const username = urlParams.get('username');
  const role = urlParams.get('role') || 'customer';
  
  if (loginSuccess === '1' && username) {
    updateUserInterface({ 
      logged_in: true, 
      username: decodeURIComponent(username), 
      role: role 
    });
    updateServicesBasedOnRole({ logged_in: true, role: role });
  } else {
    updateServicesBasedOnRole({ logged_in: false });
  }
}

// 检查URL参数显示欢迎消息
function checkURLParams() {
  const urlParams = new URLSearchParams(window.location.search);
  const loginSuccess = urlParams.get('login_success');
  const username = urlParams.get('username');
  const role = urlParams.get('role');
  
  if (loginSuccess === '1' && username) {
    const welcomeMsg = document.getElementById('welcomeMessage');
    if (welcomeMsg) {
      const roleText = role === 'admin' ? ' (Administrator)' : '';
      welcomeMsg.innerHTML = `
        <h3>Welcome back, ${decodeURIComponent(username)}${roleText}! 🎉</h3>
        <p>Login successful! ${role === 'admin' ? 'Access the admin panel to manage the store.' : 'Start exploring our book collection!'}</p>
      `;
      welcomeMsg.classList.remove('hidden');
      
      setTimeout(() => {
        welcomeMsg.classList.add('hidden');
      }, 5000);
    }
    
    // 清除URL参数但不刷新页面
    const newUrl = window.location.pathname;
    window.history.replaceState({}, document.title, newUrl);
  }
}

// 加载畅销书籍
function loadBestsellers() {
  fetch(API_ENDPOINTS.books)
    .then(response => {
      if (!response.ok) {
        throw new Error('Failed to fetch books');
      }
      return response.json();
    })
    .then(data => {
      const booksGrid = document.getElementById('booksGrid');
      
      if (data.success && data.books && data.books.length > 0) {
        // 获取前4本畅销书（按销量排序）
        const bestsellers = data.books
          .sort((a, b) => (b.total_sales || 0) - (a.total_sales || 0))
          .slice(0, 4);
        
        booksGrid.innerHTML = bestsellers.map(book => `
          <div class="book-card" onclick="location.href='book_details.html?id=${book.id}'">
            <div class="book-cover">${book.title.split(' ').map(word => word[0]).join('').toUpperCase().substring(0, 2)}</div>
            <h4>${book.title}</h4>
            <p class="book-author">${book.author}</p>
            <div class="book-price">RM ${parseFloat(book.price).toFixed(2)}</div>
          </div>
        `).join('');
      }
    })
    .catch(error => {
      console.error('Error loading bestsellers:', error);
      // 保持默认的畅销书显示
    });
}

// 页面加载时执行
document.addEventListener('DOMContentLoaded', function() {
  console.log('Virtual BookStore Main Page Loaded');
  checkLoginStatus();
  checkURLParams();
  loadBestsellers();
  
  // 添加交互效果
  addInteractivity();
  
  // 每5分钟刷新用户状态（可选）
  setInterval(checkLoginStatus, 300000);
});

// 添加交互效果
function addInteractivity() {
  // 为所有卡片添加点击效果
  const cards = document.querySelectorAll('.feature-card, .service-card, .book-card');
  cards.forEach(card => {
    card.addEventListener('click', function() {
      this.style.transform = 'scale(0.95)';
      setTimeout(() => {
        this.style.transform = '';
      }, 150);
    });
  });

  // 为服务卡片添加悬停效果
  const serviceItems = document.querySelectorAll('.service-item');
  serviceItems.forEach(item => {
    item.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-5px)';
    });
    item.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
    });
  });

  // 为书籍卡片添加交互效果
  const bookCards = document.querySelectorAll('.book-card');
  bookCards.forEach(card => {
    card.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-3px)';
    });
    card.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
    });
  });
}