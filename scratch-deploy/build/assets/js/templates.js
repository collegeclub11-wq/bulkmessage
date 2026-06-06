// frontend/assets/js/templates.js

let loadedTemplates = [];

async function loadTemplates() {
  try {
    const res = await fetch(`${API_BASE}/templates`, {
      headers: getHeaders()
    });
    const data = await res.json();
    
    if (res.ok && data.templates) {
      loadedTemplates = data.templates;
      const tbody = document.getElementById('templates-tbody');
      tbody.innerHTML = '';
      
      data.templates.forEach(t => {
        const tr = document.createElement('tr');
        
        let mediaText = '';
        if (t.image_url) {
          mediaText = `<br><span style="font-size: 11px; color: var(--primary-accent);"><a href="${escapeHtml(t.image_url)}" target="_blank" style="color: var(--primary-accent); text-decoration: underline;">View Attached Image</a></span>`;
        }

        tr.innerHTML = `
          <td><strong>${escapeHtml(t.name)}</strong>${mediaText}</td>
          <td><span class="badge" style="background: rgba(255,255,255,0.05);">${escapeHtml(t.category)}</span></td>
          <td><code style="font-size: 13px; color: var(--text-muted);">${escapeHtml(t.message)}</code></td>
          <td><span class="badge success">ACTIVE</span></td>
          <td>
            <button class="secondary" style="padding: 4px 8px; font-size: 12px; margin-right: 6px;" onclick="editTemplate(${t.id})">Edit</button>
            <button style="padding: 4px 8px; font-size: 12px; background: #ea4335;" onclick="deleteTemplate(${t.id})">Delete</button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }
  } catch (err) {
    console.error('Failed to load templates:', err);
  }
}

function editTemplate(id) {
  const template = loadedTemplates.find(t => t.id === id);
  if (!template) return;

  document.getElementById('edit-template-id').value = template.id;
  document.getElementById('edit-template-name-input').value = template.name;
  document.getElementById('edit-template-category-input').value = template.category || '';
  document.getElementById('edit-template-message-input').value = template.message;
  document.getElementById('edit-template-image-input').value = template.image_url || '';
  document.getElementById('edit-template-image-file').value = ''; // clear file input
  
  openModal('edit-template');
}

async function deleteTemplate(id) {
  if (!confirm('Are you sure you want to delete this template?')) return;
  try {
    const res = await fetch(`${API_BASE}/templates?id=${id}`, {
      method: 'DELETE',
      headers: getHeaders()
    });
    if (res.ok) {
      loadTemplates();
    } else {
      const data = await res.json();
      alert(data.error || 'Failed to delete template');
    }
  } catch (err) {
    console.error('Failed to delete template:', err);
  }
}
