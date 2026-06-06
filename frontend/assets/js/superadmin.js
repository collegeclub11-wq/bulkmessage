// frontend/assets/js/superadmin.js

let loadedSATenants = [];
let loadedSAKeys = [];

async function loadSuperAdminData() {
  await loadSuperAdminTenants();
  await loadSuperAdminKeys();
}

async function loadSuperAdminTenants() {
  try {
    const res = await fetch(`${API_BASE}/superadmin/tenants`, { headers: getHeaders() });
    const data = await res.json();
    if (res.ok && data.tenants) {
      loadedSATenants = data.tenants;
      const tbody = document.getElementById('superadmin-tenants-tbody');
      tbody.innerHTML = '';

      data.tenants.forEach(t => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><strong>${escapeHtml(t.company_name)}</strong></td>
          <td><code>${escapeHtml(t.tenant_key)}</code></td>
          <td>${escapeHtml(t.email)}</td>
          <td><span class="badge" style="background: rgba(255,255,255,0.05);">${escapeHtml(t.subscription_plan.toUpperCase())}</span></td>
          <td>
            <select onchange="updateTenantStatus(${t.id}, this.value)" style="padding: 4px 8px; font-size: 12px; background: #161b22; border-radius: 4px; border: 1px solid var(--panel-border); color: #fff;">
              <option value="active" ${t.status === 'active' ? 'selected' : ''}>Active</option>
              <option value="suspended" ${t.status === 'suspended' ? 'selected' : ''}>Suspended</option>
              <option value="expired" ${t.status === 'expired' ? 'selected' : ''}>Expired</option>
            </select>
          </td>
          <td>${t.rate_limit_per_minute} / ${t.rate_limit_per_hour} / ${t.rate_limit_per_day}</td>
          <td>${t.total_messages_sent || 0} / ${t.max_messages_limit || 0} <a href="#" style="color: var(--primary-accent); margin-left: 8px; font-size: 11px; text-decoration: none;" onclick="editTenantMessageLimit(${t.id}); return false;">✏️ Edit</a></td>
          <td>
            <button class="secondary" style="padding: 4px 8px; font-size: 11px;" onclick="editTenantLimits(${t.id})">Edit Limits</button>
            <button class="secondary" style="padding: 4px 8px; font-size: 11px; margin-left: 4px; background: rgba(255,255,255,0.05);" onclick="resetTenantPassword(${t.id})">Reset Password</button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }
  } catch (err) {
    console.error('Failed to load tenants:', err);
  }
}

async function loadSuperAdminKeys() {
  try {
    const res = await fetch(`${API_BASE}/superadmin/keys`, { headers: getHeaders() });
    const data = await res.json();
    if (res.ok && data.keys) {
      loadedSAKeys = data.keys;
      const tbody = document.getElementById('superadmin-keys-tbody');
      tbody.innerHTML = '';

      data.keys.forEach(k => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><strong>${escapeHtml(k.company_name)}</strong></td>
          <td><code>${escapeHtml(k.api_key)}</code></td>
          <td><span class="badge ${k.is_active ? 'success' : 'warning'}">${k.is_active ? 'ACTIVE' : 'REVOKED'}</span></td>
          <td><code style="font-size: 11px;">${escapeHtml(JSON.stringify(k.permissions))}</code></td>
          <td>${new Date(k.created_at).toLocaleString()}</td>
          <td>
            <button style="padding: 4px 8px; font-size: 11px; background: #ea4335;" onclick="revokeApiKey(${k.id})">Revoke Key</button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }
  } catch (err) {
    console.error('Failed to load API keys:', err);
  }
}

function openCreateTenantModal() {
  document.getElementById('form-create-tenant').reset();
  openModal('create-tenant');
}

async function submitCreateTenant(e) {
  e.preventDefault();
  const tenant_key = document.getElementById('tenant-key-input').value;
  const company_name = document.getElementById('tenant-company-input').value;
  const email = document.getElementById('tenant-email-input').value;
  const password = document.getElementById('tenant-password-input').value;
  const phone = document.getElementById('tenant-phone-input').value;
  const subscription_plan = document.getElementById('tenant-plan-input').value;
  const rate_limit_per_minute = document.getElementById('tenant-limit-min').value;
  const rate_limit_per_hour = document.getElementById('tenant-limit-hour').value;
  const rate_limit_per_day = document.getElementById('tenant-limit-day').value;
  const max_messages_limit = document.getElementById('tenant-max-messages').value;

  const res = await fetch(`${API_BASE}/superadmin/tenants`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({
      tenant_key,
      company_name,
      email,
      password,
      phone,
      subscription_plan,
      rate_limit_per_minute,
      rate_limit_per_hour,
      rate_limit_per_day,
      max_messages_limit: parseInt(max_messages_limit)
    })
  });

  if (res.ok) {
    closeModal('create-tenant');
    loadSuperAdminTenants();
  } else {
    const err = await res.json();
    alert(err.error || 'Failed to create tenant');
  }
}

async function updateTenantStatus(id, newStatus) {
  const res = await fetch(`${API_BASE}/superadmin/tenants/status`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({ id, status: newStatus })
  });
  if (!res.ok) {
    const err = await res.json();
    alert(err.error || 'Failed to update tenant status');
    loadSuperAdminTenants();
  }
}

function editTenantLimits(id) {
  const tenant = loadedSATenants.find(t => t.id === id);
  if (!tenant) return;

  const min = prompt('Enter new rate limit per minute:', tenant.rate_limit_per_minute);
  if (min === null) return;
  const hr = prompt('Enter new rate limit per hour:', tenant.rate_limit_per_hour);
  if (hr === null) return;
  const day = prompt('Enter new rate limit per day:', tenant.rate_limit_per_day);
  if (day === null) return;
  const maxMsg = prompt('Enter new Max Message Limit (total allowed):', tenant.max_messages_limit);
  if (maxMsg === null) return;

  updateTenantLimits(id, min, hr, day, maxMsg);
}

async function updateTenantLimits(id, min, hr, day, maxMsg) {
  const res = await fetch(`${API_BASE}/superadmin/tenants/status`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({
      id,
      rate_limit_per_minute: parseInt(min),
      rate_limit_per_hour: parseInt(hr),
      rate_limit_per_day: parseInt(day),
      max_messages_limit: parseInt(maxMsg)
    })
  });
  if (res.ok) {
    loadSuperAdminTenants();
  } else {
    const err = await res.json();
    alert(err.error || 'Failed to update rate limits');
  }
}

async function openCreateApiKeyModal() {
  const res = await fetch(`${API_BASE}/superadmin/tenants`, { headers: getHeaders() });
  const data = await res.json();
  const select = document.getElementById('apikey-tenant-select');
  select.innerHTML = '';
  
  if (data.tenants) {
    data.tenants.forEach(t => {
      select.innerHTML += `<option value="${t.id}">${escapeHtml(t.company_name)} (${escapeHtml(t.tenant_key)})</option>`;
    });
  }

  document.getElementById('form-create-apikey').reset();
  openModal('create-apikey');
}

async function submitCreateApiKey(e) {
  e.preventDefault();
  const tenant_id = document.getElementById('apikey-tenant-select').value;
  const name = document.getElementById('apikey-name-input').value;

  const res = await fetch(`${API_BASE}/superadmin/keys`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({ tenant_id, name })
  });

  if (res.ok) {
    const creds = await res.json();
    closeModal('create-apikey');
    
    document.getElementById('cred-api-key').value = creds.api_key;
    document.getElementById('cred-api-secret').value = creds.api_secret;
    openModal('show-credentials');
    
    loadSuperAdminKeys();
  } else {
    const err = await res.json();
    alert(err.error || 'Failed to generate API Key');
  }
}

async function revokeApiKey(id) {
  if (!confirm('Are you sure you want to revoke/delete this API Key? Credentials validation will instantly fail.')) return;

  const res = await fetch(`${API_BASE}/superadmin/keys/revoke`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({ id })
  });

  if (res.ok) {
    loadSuperAdminKeys();
  } else {
    const err = await res.json();
    alert(err.error || 'Failed to revoke API Key');
  }
}

async function resetTenantPassword(tenantId) {
  const newPassword = prompt('Enter new password for this tenant\'s administrator:');
  if (!newPassword) return;

  const res = await fetch(`${API_BASE}/superadmin/tenants/reset-password`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({ tenant_id: tenantId, password: newPassword })
  });

  const data = await res.json();
  if (res.ok) {
    alert(data.message || 'Password reset successfully');
  } else {
    alert(data.error || 'Failed to reset password');
  }
}

async function editTenantMessageLimit(id) {
  const tenant = loadedSATenants.find(t => t.id === id);
  if (!tenant) return;

  const maxMsg = prompt('Enter new Max Message Limit (total allowed):', tenant.max_messages_limit);
  if (maxMsg === null || maxMsg === '') return;

  const res = await fetch(`${API_BASE}/superadmin/tenants/status`, {
    method: 'POST',
    headers: getHeaders(),
    body: JSON.stringify({
      id,
      max_messages_limit: parseInt(maxMsg)
    })
  });
  if (res.ok) {
    loadSuperAdminTenants();
  } else {
    const err = await res.json();
    alert(err.error || 'Failed to update message limit');
  }
}
