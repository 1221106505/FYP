// ===== UI Helpers =====
function showError(msg) {
  const box = document.getElementById('errorBox');
  box.textContent = msg;
  box.style.display = 'block';
  clearTimeout(showError._timer);
  showError._timer = setTimeout(() => box.style.display = 'none', 5000);
}

function clearError() {
  const box = document.getElementById('errorBox');
  box.style.display = 'none';
  box.textContent = '';
}

// ===== Show/Hide Forms =====
function showRegister() {
  clearError();
  document.getElementById('loginForm').classList.add('hidden');
  document.getElementById('registerForm').classList.remove('hidden');
}

function showLogin() {
  clearError();
  document.getElementById('registerForm').classList.add('hidden');
  document.getElementById('loginForm').classList.remove('hidden');
}

// ===== Toggle Password =====
function togglePassword(inputId, btn) {
  const input = document.getElementById(inputId);
  input.type = input.type === 'password' ? 'text' : 'password';
  btn.textContent = input.type === 'password' ? '👁️' : '🙈';
}

// ===== Back to Main =====
function backToMain() {
  alert("Returning to Main Page!");
  window.location.href = "Main.html"; // change this to your main page
}

// ===== Login =====
aasync function login() {
  clearError();
  const username = document.getElementById('loginUser').value.trim();
  const password = document.getElementById('loginPass').value;

  if(!username || !password) { showError("Enter username and password"); return; }

  try {
    const res = await fetch('Login.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ username, password })
    });
    const data = await res.json();
    if(data.error) { showError(data.error); return; }
    alert(`Welcome ${data.username}! You are logged in as ${data.identity}`);
  } catch(err) {
    console.error(err);
    showError("Server error");
  }
}

async function register() {
  clearError();
  const username = document.getElementById('regUser').value.trim();
  const password = document.getElementById('regPass').value;
  const identity = document.getElementById('regIdentity').value;

  if(!username || !password) { showError("Fill all fields"); return; }

  try {
    const res = await fetch('Register.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ username, password, identity })
    });
    const data = await res.json();
    if(data.error) { showError(data.error); return; }
    alert(`User "${data.username}" registered successfully!`);
    showLogin();
  } catch(err) {
    console.error(err);
    showError("Server error");
  }
}

