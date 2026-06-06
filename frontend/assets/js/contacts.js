// frontend/assets/js/contacts.js

async function loadContacts() {
  try {
    const res = await fetch(`${API_BASE}/contacts`, {
      headers: getHeaders()
    });
    const data = await res.json();
    
    if (res.ok && data.contacts) {
      const tbody = document.getElementById('contacts-tbody');
      tbody.innerHTML = '';
      
      data.contacts.forEach(c => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><strong>${escapeHtml(c.name || 'Unnamed')}</strong></td>
          <td>${escapeHtml(c.phone_number)}</td>
          <td>${escapeHtml(c.email || '-')}</td>
          <td><small style="color: var(--primary-accent);">${escapeHtml(JSON.stringify(c.custom_fields || {}))}</small></td>
          <td><span class="badge" style="background: rgba(139, 92, 246, 0.15); color: var(--secondary-accent);">${escapeHtml(c.group_name || 'Unassigned')}</span></td>
        `;
        tbody.appendChild(tr);
      });
    }
  } catch (err) {
    console.error('Failed to load contacts list:', err);
  }
}
