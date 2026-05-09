const checklistEl = document.getElementById('checklist');
const statusEl = document.getElementById('sync-status');
const template = document.getElementById('item-template');
const titleEl = document.getElementById('checklist-title');
const subtitleEl = document.getElementById('checklist-subtitle');
const typeButtons = [...document.querySelectorAll('.choice-btn')];

const API_URL = 'api/checklist.php';
const LIST_TYPE_KEY = 'brvlg_list_type';

const presetItems = {
  daily: [
    ['Ladder (6ft+)', 'Large Tool'], ['Manifold Gauge Set', 'Large Tool'], ['Recovery Machine', 'Large Tool'],
    ['Vacuum Pump', 'Large Tool'], ['Refrigerant Cylinder', 'Large Part'], ['Weighing Scale', 'Large Tool'],
    ['Main Toolbox', 'Large Tool'], ['Core Drill / Hammer Drill', 'Large Tool']
  ],
  weekly: [
    ['Ladder (6ft+)', 'Large Tool'], ['Manifold Gauge Set', 'Large Tool'], ['Recovery Machine', 'Large Tool'],
    ['Vacuum Pump', 'Large Tool'], ['Refrigerant Cylinder', 'Large Part'], ['Weighing Scale', 'Large Tool'], ['Main Toolbox', 'Large Tool'],
    ['Needle-nose Pliers', 'Hand Tool'], ['Slip-joint Pliers', 'Hand Tool'], ['Channel-lock Pliers', 'Hand Tool'],
    ['Phillips Screwdrivers (set)', 'Hand Tool'], ['Flat Screwdrivers (set)', 'Hand Tool'], ['Hex Key Set', 'Hand Tool'],
    ['Adjustable Wrench', 'Hand Tool'], ['Pipe Wrench', 'Hand Tool'], ['Wire Strippers', 'Electrical'], ['Multimeter', 'Electrical'],
    ['Electrical Tape', 'Electrical'], ['Welding Rods/Sticks', 'Consumable'], ['Brazing Torch', 'Consumable'],
    ['AC Coupling Nuts', 'Fittings'], ['Assorted Screws', 'Fastener'], ['Assorted Bolts/Nuts/Washers', 'Fastener'],
    ['Insulation Tape', 'Consumable'], ['Zip Ties', 'Consumable'], ['Leak Detector Spray', 'Consumable']
  ]
};

function setStatus(msg, kind = 'warn') {
  statusEl.textContent = msg;
  statusEl.className = `sync status-${kind}`;
}

async function request(url, options = {}) {
  const res = await fetch(url, { headers: { 'Content-Type': 'application/json' }, ...options });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.status === 204 ? null : res.json();
}

async function getItems(type) {
  const data = await request(`${API_URL}?type=${type}`);
  return data.items || [];
}

async function ensurePreset(type) {
  const items = await getItems(type);
  if (items.length > 0) return items;
  for (const [name, category] of presetItems[type]) {
    await request(`${API_URL}?type=${type}`, { method: 'POST', body: JSON.stringify({ name, category, list_type: type }) });
  }
  return getItems(type);
}

function render(items) {
  checklistEl.innerHTML = '';
  for (const item of items) {
    const row = template.content.firstElementChild.cloneNode(true);
    row.dataset.id = item.id;
    row.querySelector('.item-name').textContent = item.name;
    row.querySelector('.item-category').textContent = item.category || 'General';

    const check = row.querySelector('.item-check');
    check.checked = Boolean(item.checked);
    check.addEventListener('change', async () => {
      setStatus('Saving change…');
      try {
        await request(`${API_URL}?id=${item.id}`, { method: 'PATCH', body: JSON.stringify({ checked: check.checked ? 1 : 0 }) });
        setStatus('Synced', 'ok');
      } catch (err) {
        check.checked = !check.checked;
        setStatus(`Sync error: ${err.message}`, 'err');
      }
    });

    row.querySelector('.delete-btn').addEventListener('click', async () => {
      setStatus('Deleting item…');
      try {
        await request(`${API_URL}?id=${item.id}`, { method: 'DELETE' });
        row.remove();
        setStatus('Synced', 'ok');
      } catch (err) {
        setStatus(`Delete failed: ${err.message}`, 'err');
      }
    });

    checklistEl.appendChild(row);
  }
}

async function load(type) {
  setStatus('Loading checklist…');
  try {
    localStorage.setItem(LIST_TYPE_KEY, type);
    typeButtons.forEach((b) => b.classList.toggle('active', b.dataset.type === type));
    titleEl.textContent = type === 'daily' ? 'Daily Checklist — Essential Large Tools/Parts' : 'Weekly Checklist — Full Inventory';
    subtitleEl.textContent = type === 'daily' ? 'Use this for normal daily departure checks.' : 'Use this once per week for complete truck stock verification.';
    const items = await ensurePreset(type);
    render(items);
    setStatus('Synced', 'ok');
  } catch (err) {
    setStatus(`Connection error: ${err.message}`, 'err');
  }
}

typeButtons.forEach((button) => button.addEventListener('click', () => load(button.dataset.type)));

const initialType = localStorage.getItem(LIST_TYPE_KEY);
if (initialType === 'daily' || initialType === 'weekly') {
  load(initialType);
}
