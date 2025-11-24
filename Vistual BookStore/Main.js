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
      console.log('Login status data:', data);
      updateUserInterface(data);
      updateServicesBasedOnRole(data);
      updateQuickActions(data);
    })
    .catch(error => {
      console.error('Error checking login status:', error);
      // 如果check_login.php不存在，使用URL参数回退
      fallbackToURLParams();
    });
}

// 根据用户角色更新服务选项
function updateServicesBasedOnRole(userData) {
  const servicesContainer = document.getElementById('servicesContainer');
  
  if (!servicesContainer) return;
  
  console.log('Updating services for role:', userData.role);
  
  if (!userData.logged_in) {
    // 未登录用户看到的服务选项
    servicesContainer.innerHTML = `
      <div class="feature-card" onclick="location.href='Searching.html'">
        <div class="feature-icon">🔍</div>
        <h3>Smart Search</h3>
        <p>Find your perfect book with our intelligent search and filtering system. Discover hidden gems and popular titles.</p>
        <button class="feature-btn">Explore Books</button>
      </div>
      <div class="feature-card" onclick="location.href='../Login/Login.html'">
        <div class="feature-icon">🌟</div>
        <h3>Join Community</h3>
        <p>Create an account to save favorites, get personalized recommendations, and join book discussions.</p>
        <button class="feature-btn">Sign Up Free</button>
      </div>
      <div class="feature-card" onclick="location.href='user_profile.html'">
        <div class="feature-icon">📚</div>
        <h3>Personal Library</h3>
        <p>Build your digital bookshelf, track reading progress, and discover your reading patterns.</p>
        <button class="feature-btn">Start Reading</button>
      </div>
    `;
  } else if (userData.role === 'admin') {
    // 管理员看到的服务选项
    servicesContainer.innerHTML = `
      <div class="feature-card" onclick="location.href='admin_panel.html'">
        <div class="feature-icon">⚙️</div>
        <h3>Admin Dashboard</h3>
        <p>Manage store operations, inventory analytics, user accounts, and system settings with full control.</p>
        <button class="feature-btn">Access Dashboard</button>
      </div>
      <div class="feature-card" onclick="location.href='stock_management.html'">
        <div class="feature-icon">📊</div>
        <h3>Inventory Control</h3>
        <p>Monitor stock levels, sales analytics, product performance, and generate detailed reports.</p>
        <button class="feature-btn">Manage Inventory</button>
      </div>
      <div class="feature-card" onclick="location.href='AddBook.html'">
        <div class="feature-icon">➕</div>
        <h3>Add New Titles</h3>
        <p>Expand our collection by adding new books, managing existing titles, and updating book information.</p>
        <button class="feature-btn">Add Books</button>
      </div>
    `;
  } else {
    // 顾客看到的服务选项
    servicesContainer.innerHTML = `
      <div class="feature-card" onclick="location.href='Searching.html'">
        <div class="feature-icon">🔍</div>
        <h3>Advanced Search</h3>
        <p>Discover new books with our powerful search, filtering, and personalized recommendation engine.</p>
        <button class="feature-btn">Find Books</button>
      </div>
      <div class="feature-card" onclick="location.href='order_history.html'">
        <div class="feature-icon">📦</div>
        <h3>Order Management</h3>
        <p>Track your orders, view order history, and manage your purchases all in one place.</p>
        <button class="feature-btn">View Orders</button>
      </div>
      <div class="feature-card" onclick="location.href='user_profile.html'">
        <div class="feature-icon">👤</div>
        <h3>My Profile</h3>
        <p>Manage your account settings, reading preferences, personal information, and privacy settings.</p>
        <button class="feature-btn">View Profile</button>
      </div>
    `;
  }
}

// 更新快速操作区域
function updateQuickActions(userData) {
  // 快速操作区域对所有用户都可见，但内容可能根据登录状态变化
  if (userData.logged_in && userData.role === 'admin') {
    // 管理员看到的快速操作
    document.querySelector('.action-card:nth-child(2) h3').textContent = 'Manage Orders';
    document.querySelector('.action-card:nth-child(2) p').textContent = 'View and manage all orders';
  }
  // 普通用户和未登录用户保持默认的快速操作
}

// 更新用户界面 - 移除了订单按钮
function updateUserInterface(userData) {
  const userInfo = document.getElementById('userInfo');
  
  if (!userInfo) return;

  if (userData.logged_in) {
    console.log('Updating user interface for:', userData.username, 'Role:', userData.role);
    
    // 根据用户角色决定个人资料链接
    let profileLink = 'user_profile.html';
    let displayText = userData.username;
    
    if (userData.role === 'admin') {
      profileLink = 'admin_panel.html';
      displayText = userData.username + ' (Admin)';
    }
    
    // 用户已登录，显示用户欢迎信息 - 移除了订单按钮
    userInfo.innerHTML = `
      <div class="user-welcome">
        <span>Welcome, ${displayText}</span>
        <div class="user-actions">
          <a href="${profileLink}">
            <button class="btn-profile">👤 Profile</button>
          </a>
          <a href="logout.php">
            <button class="btn-logout">🚪 Logout</button>
          </a>
        </div>
      </div>
    `;
    
    // 显示欢迎消息
    const welcomeMessage = document.getElementById('welcomeMessage');
    if (welcomeMessage) {
      const roleText = userData.role === 'admin' ? ' (Administrator)' : '';
      welcomeMessage.innerHTML = `
        <h3>Welcome back, ${userData.username}${roleText}! 🎉</h3>
        <p>Ready to continue your reading journey? ${userData.role === 'admin' ? 'Access the admin panel to manage the store.' : 'Explore our latest book collections!'}</p>
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
        <button class="btn-login">🔐 Login</button>
      </a>
    `;
    
    const welcomeMessage = document.getElementById('welcomeMessage');
    if (welcomeMessage) {
      welcomeMessage.classList.add('hidden');
    }
  }
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
    updateQuickActions({ logged_in: true, role: role });
  } else {
    updateServicesBasedOnRole({ logged_in: false });
    updateQuickActions({ logged_in: false });
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

// 随机打乱数组函数
function shuffleArray(array) {
  const newArray = [...array];
  for (let i = newArray.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [newArray[i], newArray[j]] = [newArray[j], newArray[i]];
  }
  return newArray;
}

// 生成书籍封面缩写
function getBookCoverAbbreviation(title) {
  if (!title) return 'BK';
  
  const words = title.split(' ').filter(word => word.length > 0);
  if (words.length >= 2) {
    return (words[0][0] + words[1][0]).toUpperCase();
  } else if (words.length === 1 && words[0].length >= 2) {
    return words[0].substring(0, 2).toUpperCase();
  } else {
    return 'BK';
  }
}

// 加载随机畅销书籍 - 修改为显示8本
function loadRandomBestsellers() {
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
        // 随机打乱书籍数组并取前8本
        const shuffledBooks = shuffleArray(data.books);
        const randomBestsellers = shuffledBooks.slice(0, 8);
        
        booksGrid.innerHTML = randomBestsellers.map(book => `
          <div class="book-card" onclick="location.href='book_details.html?id=${book.id}'">
            <div class="book-cover">${getBookCoverAbbreviation(book.title)}</div>
            <h4 class="book-title">${book.title || 'Unknown Title'}</h4>
            <p class="book-author">${book.author || 'Unknown Author'}</p>
            <div class="book-price">RM ${parseFloat(book.price || 0).toFixed(2)}</div>
          </div>
        `).join('');
        
        console.log('Loaded 8 random bestsellers:', randomBestsellers);
      } else {
        // 如果没有从数据库获取到数据，显示默认书籍（8本）
        showDefaultBooks();
      }
    })
    .catch(error => {
      console.error('Error loading random bestsellers:', error);
      // 如果API调用失败，显示默认书籍（8本）
      showDefaultBooks();
    });
}

// 显示默认书籍（备用方案）- 修改为显示8本
function showDefaultBooks() {
  const booksGrid = document.getElementById('booksGrid');
  const defaultBooks = [
    { title: 'Atomic Habits', author: 'James Clear', price: 18.99 },
    { title: 'Dune', author: 'Frank Herbert', price: 16.99 },
    { title: 'The Hobbit', author: 'J.R.R. Tolkien', price: 14.99 },
    { title: 'Harry Potter and the Philosopher\'s Stone', author: 'J.K. Rowling', price: 12.99 },
    { title: 'The Midnight Library', author: 'Matt Haig', price: 15.99 },
    { title: 'Project Hail Mary', author: 'Andy Weir', price: 17.99 },
    { title: 'The Silent Patient', author: 'Alex Michaelides', price: 13.99 },
    { title: 'Where the Crawdads Sing', author: 'Delia Owens', price: 16.49 }
  ];
  
  booksGrid.innerHTML = defaultBooks.map(book => `
    <div class="book-card">
      <div class="book-cover">${getBookCoverAbbreviation(book.title)}</div>
      <h4 class="book-title">${book.title}</h4>
      <p class="book-author">${book.author}</p>
      <div class="book-price">RM ${book.price.toFixed(2)}</div>
    </div>
  `).join('');
  
  console.log('Showing 8 default books');
}
// 页面加载时执行
document.addEventListener('DOMContentLoaded', function() {
  console.log('Virtual BookStore - Redesigned Main Page Loaded');
  checkLoginStatus();
  checkURLParams();
  loadRandomBestsellers();
});