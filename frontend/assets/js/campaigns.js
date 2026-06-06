// frontend/assets/js/campaigns.js

async function loadCampaigns() {
  try {
    const res = await fetch(`${API_BASE}/campaigns`, {
      headers: getHeaders()
    });
    const data = await res.json();
    
    if (res.ok && data.campaigns) {
      const tbody = document.getElementById('campaigns-tbody');
      tbody.innerHTML = '';
      
      data.campaigns.forEach(c => {
        const tr = document.createElement('tr');
        const progressPercent = c.total_contacts > 0 ? Math.round((c.sent_count / c.total_contacts) * 100) : 0;
        
        const remaining = c.pending_count !== undefined ? parseInt(c.pending_count) : Math.max(0, c.total_contacts - (c.sent_count + c.failed_count));
        const estTime = formatEstimatedTime(remaining, c.status);

        tr.innerHTML = `
          <td><strong>${escapeHtml(c.campaign_name)}</strong></td>
          <td>${escapeHtml(c.template_name || 'N/A')}</td>
          <td>${escapeHtml(c.group_name || 'N/A')}</td>
          <td>${c.total_contacts}</td>
          <td>
            <div style="background: rgba(255,255,255,0.1); border-radius: 9999px; width: 100%; height: 8px; overflow: hidden; margin-top: 4px;">
              <div style="background: var(--primary-accent); width: ${progressPercent}%; height: 100%;"></div>
            </div>
            <span style="font-size: 11px; color: var(--text-muted);">${progressPercent}% (${c.sent_count}/${c.total_contacts})</span>
            <br><span style="font-size: 11px; color: var(--primary-accent); font-weight: 500;">Est. Time: ${estTime}</span>
          </td>
          <td><span class="badge ${c.status === 'completed' ? 'success' : 'warning'}">${c.status.toUpperCase()}</span></td>
        `;
        tbody.appendChild(tr);
      });
    }
  } catch (err) {
    console.error('Failed to load campaigns list:', err);
  }
}

function escapeHtml(text) {
  if (!text) return '';
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function formatEstimatedTime(remainingCount, status) {
  if (status === 'completed') return 'Completed';
  if (status === 'paused') return 'Paused';
  if (status === 'failed') return 'Failed';
  if (remainingCount <= 0) return '0 sec';
  
  const avgDelaySec = 5.0; // average dynamic delay
  const totalSec = remainingCount * avgDelaySec;
  
  if (totalSec < 60) {
    return `${Math.round(totalSec)} sec`;
  }
  
  const minutes = Math.floor(totalSec / 60);
  const seconds = Math.round(totalSec % 60);
  
  if (minutes < 60) {
    return `${minutes} min ${seconds} sec`;
  }
  
  const hours = Math.floor(minutes / 60);
  const remainingMins = minutes % 60;
  
  return `${hours} hr ${remainingMins} min`;
}
