// frontend/assets/js/dashboard.js

async function loadDashboardStats() {
  try {
    const res = await fetch(`${API_BASE}/reports/dashboard`, {
      headers: getHeaders()
    });
    const data = await res.json();
    
    if (res.ok) {
      document.getElementById('stat-campaigns').innerText = data.total_campaigns || 0;
      document.getElementById('stat-contacts').innerText = data.total_contacts_targeted || 0;
      document.getElementById('stat-sent').innerText = data.total_sent || 0;
      document.getElementById('stat-sessions').innerText = data.active_sessions || 0;
      
      const total = parseInt(data.max_messages_limit) || 0;
      const sent = parseInt(data.total_messages_sent) || 0;
      const remaining = Math.max(0, total - sent);
      document.getElementById('stat-remaining').innerText = `${remaining} / ${total}`;
    }
  } catch (err) {
    console.error('Failed to load dashboard metrics:', err);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  loadDashboardStats();
});
