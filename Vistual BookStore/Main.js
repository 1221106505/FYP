// 检查登录状态并更新UI
function checkLoginStatus() {
  fetch('check_login.php')
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

/// 更新管理员入口显示
function updateAdminEntry(userData) {
  const adminEntry = document.getElementById('adminEntry');
  
  if (!adminEntry) return;
  
  console.log('Checking admin entry for role:', userData.role);
  
  if (userData.logged_in && userData.role === 'admin') {
    adminEntry.classList.remove('hidden');
    console.log('Admin entry displayed');
    
    // 强制应用样式
    adminEntry.style.background = 'white';
    adminEntry.style.color = '#333';
    adminEntry.style.padding = '30px';
    adminEntry.style.margin = '40px auto';
    adminEntry.style.maxWidth = '1000px';
    adminEntry.style.borderRadius = '8px';
    adminEntry.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
    adminEntry.style.border = '1px solid #e0e0e0';
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
function updateServicesBasedOnRole(userData) {
  const servicesContainer = document.getElementById('servicesContainer');
  
  if (!servicesContainer) return;
  
  console.log('Updating services for role:', userData.role); // 调试信息
  
  if (!userData.logged_in) {
    // 未登录用户只能看到搜索和关于我们
    servicesContainer.innerHTML = `
      <div class="service-item">
        <a href="Searching.html" class="service-link">
          <div class="service-icon">🔍</div>
          <h3>Search Books</h3>
          <p>Find exactly what you're looking for with our powerful search tool.</p>
        </a>
      </div>
      <div class="service-item">
        <a href="About.html" class="service-link">
          <div class="service-icon">ℹ️</div>
          <h3>About Us</h3>
          <p>Learn more about our mission and the team behind Virtual BookStore.</p>
        </a>
      </div>
    `;
  } else if (userData.role === 'admin') {
    // 管理员看到的服务选项
    servicesContainer.innerHTML = `
      <div class="service-item">
        <a href="admin_panel.html" class="service-link" onclick="trackLinkClick('admin_panel')">
          <div class="service-icon">⚙️</div>
          <h3>Admin Panel</h3>
          <p>Access administrative functions and system management.</p>
        </a>
      </div>
      <div class="service-item">
        <a href="stock_management.html" class="service-link">
          <div class="service-icon">📊</div>
          <h3>Stock Management</h3>
          <p>Manage book inventory and track stock levels.</p>
        </a>
      </div>
      <div class="service-item">
        <a href="AddBook.html" class="service-link">
          <div class="service-icon">➕</div>
          <h3>Add New Book</h3>
          <p>Contribute to our collection by adding new books to the store.</p>
        </a>
      </div>
      <div class="service-item">
        <a href="Searching.html" class="service-link">
          <div class="service-icon">🔍</div>
          <h3>Search Books</h3>
          <p>Find exactly what you're looking for with our powerful search tool.</p>
        </a>
      </div>
    `;
  } else {
    // 顾客看到的服务选项
    servicesContainer.innerHTML = `
      <div class="service-item">
        <a href="customer_view.html" class="service-link">
          <div class="service-icon">🛒</div>
          <h3>Browse Books</h3>
          <p>Explore our extensive collection of books.</p>
        </a>
      </div>
      <div class="service-item">
        <a href="Searching.html" class="service-link">
          <div class="service-icon">🔍</div>
          <h3>Search Books</h3>
          <p>Find exactly what you're looking for.</p>
        </a>
      </div>
      <div class="service-item">
        <a href="user_profile.html" class="service-link">
          <div class="service-icon">👤</div>
          <h3>My Profile</h3>
          <p>Manage your account and preferences.</p>
        </a>
      </div>
      <div class="service-item">
        <a href="About.html" class="service-link">
          <div class="service-icon">ℹ️</div>
          <h3>About Us</h3>
          <p>Learn more about our bookstore.</p>
        </a>
      </div>
    `;
  }
}

// 更新用户界面
function updateUserInterface(userData) {
  const userInfo = document.getElementById('userInfo');
  
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
    
    // 用户已登录，显示头像和用户名
    userInfo.innerHTML = `
      <a href="${profileLink}" id="userProfileLink" onclick="trackLinkClick('${profileLink}')">
        <button class="user-button">
          <div class="main-avatar">
            ${userProfile.avatarType === 'image' && userProfile.avatarImage ? 
              `<img src="${userProfile.avatarImage}" class="main-avatar-img" alt="Avatar">` : 
              `<div class="main-avatar-emoji">${userProfile.avatarValue}</div>`
            }
          </div>
          ${displayText}
        </button>
      </a>
    `;
    
    // 设置头像背景颜色（如果是emoji）
    if (userProfile.avatarType === 'emoji') {
      const avatarElement = userInfo.querySelector('.main-avatar');
      const colors = ['#6a11cb', '#2575fc', '#28a745', '#dc3545', '#fd7e14', '#6f42c1'];
      const color = colors[userProfile.avatarValue.charCodeAt(0) % colors.length];
      avatarElement.style.background = `linear-gradient(135deg, ${color} 0%, ${color}99 100%)`;
    }
  } else {
    // 用户未登录，显示登录按钮
    userInfo.innerHTML = `
      <a href="../Login/Login.html">
        <button class="login-button">Login</button>
      </a>
    `;
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
    const userInfo = document.getElementById('userInfo');
    const adminEntry = document.getElementById('adminEntry');
    
    if (userInfo) {
      const userProfile = getUserAvatarData(username);
      
      let profileLink = 'user_profile.html';
      let displayText = decodeURIComponent(username);
      
      if (role === 'admin') {
        profileLink = 'admin_panel.html';
        displayText = decodeURIComponent(username) + ' (Admin)';
        
        // 显示管理员入口
        if (adminEntry) {
          adminEntry.classList.remove('hidden');
        }
      }
      
      userInfo.innerHTML = `
        <a href="${profileLink}">
          <button class="user-button">
            <div class="main-avatar">
              ${userProfile.avatarType === 'image' && userProfile.avatarImage ? 
                `<img src="${userProfile.avatarImage}" class="main-avatar-img" alt="Avatar">` : 
                `<div class="main-avatar-emoji">${userProfile.avatarValue}</div>`
              }
            </div>
            ${displayText}
          </button>
        </a>
      `;
      
      if (userProfile.avatarType === 'emoji') {
        const avatarElement = userInfo.querySelector('.main-avatar');
        const colors = ['#6a11cb', '#2575fc', '#28a745', '#dc3545', '#fd7e14', '#6f42c1'];
        const color = colors[userProfile.avatarValue.charCodeAt(0) % colors.length];
        avatarElement.style.background = `linear-gradient(135deg, ${color} 0%, ${color}99 100%)`;
      }
    }
    
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
      welcomeMsg.textContent = `Welcome back, ${decodeURIComponent(username)}${roleText}! Login successful.`;
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

// 页面加载时执行
document.addEventListener('DOMContentLoaded', function() {
  console.log('Main page loaded'); // 调试信息
  checkLoginStatus();
  checkURLParams();
  
  // 添加一些交互效果
  addInteractivity();
});

// 添加交互效果
function addInteractivity() {
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