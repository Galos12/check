const checklistEl = document.getElementById('checklist');
const statusEl = document.getElementById('sync-status');
const template = document.getElementById('item-template');

const API_URL = 'api/checklist.php';

function setStatus(msg, kind = 'warn') {
  statusEl.textContent = msg;
  statusEl.className = '';
  statusEl.classList.add(`status-${kind}`);
}

async function request(url, options = {}) {
  const res = await fetch(url, {
    headers: { 'Content-Type': 'application/json' },
    ...options
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.status === 204 ? null : res.json();
}

function render(items) {
  checklistEl.innerHTML = '';
  for (const item of items) {
    const node = template.content.firstElementChild.cloneNode(true);
    node.dataset.id = item.id;
    node.querySelector('.item-name').textContent = item.name;

    const check = node.querySelector('.item-check');
    check.checked = Boolean(item.checked);
    check.addEventListener('change', async () => {
      setStatus('Syncing update…');
      try {
        await request(`${API_URL}?id=${item.id}`, {
          method: 'PATCH',
          body: JSON.stringify({ checked: check.checked ? 1 : 0 })
        });
        setStatus('Synced', 'ok');
      } catch (err) {
        check.checked = !check.checked;
        setStatus(`Sync error: ${err.message}`, 'err');
      }
    });

    node.querySelector('.delete-btn').addEventListener('click', async () => {
      setStatus('Deleting item…');
      try {
        await request(`${API_URL}?id=${item.id}`, { method: 'DELETE' });
        node.remove();
        setStatus('Synced', 'ok');
      } catch (err) {
        setStatus(`Delete failed: ${err.message}`, 'err');
      }
    });

    checklistEl.appendChild(node);
  }
}

async function loadChecklist() {
  setStatus('Loading checklist…');
  try {
    const data = await request(API_URL);
    render(data.items || []);
    setStatus('Synced', 'ok');
  } catch (err) {
    setStatus(`Connection error: ${err.message}`, 'err');
  }
}

loadChecklist();
