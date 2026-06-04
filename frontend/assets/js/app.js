const API_BASE = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' 
  ? '/bulk/backend-php/public/api' 
  : '/backend-php/public/api';

function getHeaders() {
  const token = localStorage.getItem('auth_token');
  const tenantKey = localStorage.getItem('tenant_key');
  return {
    'Content-Type': 'application/json',
    'Authorization': token ? `Bearer ${token}` : '',
    'X-Tenant-Key': tenantKey || ''
  };
}

function checkAuth() {
  const token = localStorage.getItem('auth_token');
  const path = window.location.pathname;
  
  if (!token && !path.includes('login.html')) {
    window.location.href = 'login.html';
  }
}

function logout() {
  localStorage.removeItem('auth_token');
  localStorage.removeItem('tenant_key');
  window.location.href = 'login.html';
}

document.addEventListener('DOMContentLoaded', () => {
  checkAuth();
  
  const logoutBtn = document.getElementById('logout-btn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', logout);
  }
});
