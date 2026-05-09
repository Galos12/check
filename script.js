const root = document.documentElement;
const themeToggle = document.getElementById('theme-toggle');
const menuBtn = document.getElementById('menu-btn');
const nav = document.getElementById('main-nav');
const tabs = [...document.querySelectorAll('.tab')];
const panels = [...document.querySelectorAll('.tab-panel')];
const taskForm = document.getElementById('task-form');
const taskInput = document.getElementById('task-input');
const taskList = document.getElementById('task-list');

const estimator = {
  form: document.getElementById('estimator-form'),
  projectType: document.getElementById('project-type'),
  sections: document.getElementById('sections'),
  cms: document.getElementById('cms'),
  priceRange: document.getElementById('price-range'),
  timeline: document.getElementById('timeline')
};

const savedTheme = localStorage.getItem('theme');
if (savedTheme === 'light') {
  root.classList.add('light');
  themeToggle.textContent = '☀️';
}

themeToggle.addEventListener('click', () => {
  root.classList.toggle('light');
  const isLight = root.classList.contains('light');
  themeToggle.textContent = isLight ? '☀️' : '🌙';
  localStorage.setItem('theme', isLight ? 'light' : 'dark');
});

menuBtn.addEventListener('click', () => {
  const open = nav.classList.toggle('open');
  menuBtn.setAttribute('aria-expanded', String(open));
});

tabs.forEach((tab) => {
  tab.addEventListener('click', () => {
    const target = tab.dataset.target;
    tabs.forEach((t) => {
      const active = t === tab;
      t.classList.toggle('is-active', active);
      t.setAttribute('aria-selected', String(active));
    });
    panels.forEach((panel) => panel.classList.toggle('is-active', panel.id === target));
  });
});

function updateEstimate() {
  const type = estimator.projectType.value;
  const sections = Number(estimator.sections.value);
  const cmsFactor = estimator.cms.checked ? 1.3 : 1;

  const baseByType = { landing: 3000, business: 6000, store: 9000 };
  const speedByType = { landing: 2, business: 4, store: 6 };

  const base = baseByType[type] ?? 3000;
  const timelineBase = speedByType[type] ?? 2;
  const sectionCost = Math.max(0, sections - 4) * 260;

  const low = Math.round((base + sectionCost) * cmsFactor);
  const high = Math.round(low * 1.4);
  const lowWeeks = Math.max(2, Math.round((timelineBase + sections / 8) * (estimator.cms.checked ? 1.2 : 1)));
  const highWeeks = lowWeeks + 1;

  estimator.priceRange.textContent = `$${low.toLocaleString()}–$${high.toLocaleString()}`;
  estimator.timeline.textContent = `${lowWeeks}–${highWeeks} weeks`;
}

estimator.form.addEventListener('input', updateEstimate);
updateEstimate();

const TASKS_KEY = 'nebula_tasks';
const tasks = JSON.parse(localStorage.getItem(TASKS_KEY) || '[]');

function renderTasks() {
  taskList.innerHTML = '';
  tasks.forEach((task, index) => {
    const item = document.createElement('li');
    item.className = 'task-item';
    item.innerHTML = `<span>${task}</span><button type="button" aria-label="Delete task">Delete</button>`;
    item.querySelector('button').addEventListener('click', () => {
      tasks.splice(index, 1);
      localStorage.setItem(TASKS_KEY, JSON.stringify(tasks));
      renderTasks();
    });
    taskList.appendChild(item);
  });
}

renderTasks();

taskForm.addEventListener('submit', (event) => {
  event.preventDefault();
  const value = taskInput.value.trim();
  if (!value) return;
  tasks.unshift(value);
  localStorage.setItem(TASKS_KEY, JSON.stringify(tasks));
  taskInput.value = '';
  renderTasks();
});
