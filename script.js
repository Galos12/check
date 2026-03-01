const itemsContainer = document.getElementById('items-container');
const addItemButton = document.getElementById('add-item-btn');
const addFullToolListButton = document.getElementById('add-full-tool-list-btn');
const newItemName = document.getElementById('new_item_name');
const newItemType = document.getElementById('new_item_type');
const checklistDateDisplay = document.getElementById('checklist_date_display');
const checklistDateInput = document.getElementById('checklist_date');
const checklistTypeInput = document.getElementById('checklist_type');

const toolsList = document.getElementById('tools-list');
const partsList = document.getElementById('parts-list');
const weeklyToolsData = addFullToolListButton?.dataset.weeklyTools;
const weeklyTools = weeklyToolsData ? JSON.parse(weeklyToolsData) : [];

const normalizeValue = (value) => value.trim().toLowerCase();

const existingToolNames = () => {
    const labels = toolsList.querySelectorAll('label');
    return new Set(Array.from(labels).map((label) => normalizeValue(label.textContent)));
};

const createItemCard = ({ name, type, has_counter: hasCounter, removable = true, weekly = false }, index) => {
    const row = document.createElement('tr');
    row.dataset.removable = removable ? 'true' : 'false';
    if (weekly) {
        row.dataset.weekly = 'true';
    }

    const checkbox = document.createElement('input');
    checkbox.className = 'form-check-input';
    checkbox.type = 'checkbox';
    checkbox.id = `item-${index}`;
    checkbox.name = `items[${index}][checked]`;
    checkbox.value = '1';

    const nameInput = document.createElement('input');
    nameInput.type = 'hidden';
    nameInput.name = `items[${index}][name]`;
    nameInput.value = name;

    const typeInput = document.createElement('input');
    typeInput.type = 'hidden';
    typeInput.name = `items[${index}][type]`;
    typeInput.value = type;

    const label = document.createElement('label');
    label.className = 'fw-semibold';
    label.setAttribute('for', `item-${index}`);
    label.textContent = name;

    const removeButton = document.createElement('button');
    removeButton.className = 'btn btn-sm btn-link text-danger remove-item-btn';
    removeButton.type = 'button';
    removeButton.textContent = 'Quitar';

    const checkboxCell = document.createElement('td');
    checkboxCell.className = 'text-center';
    checkboxCell.appendChild(checkbox);

    const labelCell = document.createElement('td');
    labelCell.append(label, nameInput, typeInput);

    const removeCell = document.createElement('td');
    removeCell.className = 'text-end';
    if (removable) {
        removeCell.appendChild(removeButton);
    }

    if (hasCounter) {
        const quantityInput = document.createElement('input');
        quantityInput.className = 'form-control form-control-sm quantity-input';
        quantityInput.type = 'number';
        quantityInput.min = '0';
        quantityInput.name = `items[${index}][quantity]`;
        quantityInput.placeholder = '0';
        removeCell.prepend(quantityInput);
    }

    row.append(checkboxCell, labelCell, removeCell);

    return row;
};

const getNextIndex = () => {
    const inputs = itemsContainer.querySelectorAll('input[type="hidden"][name^="items"]');
    if (!inputs.length) {
        return 0;
    }

    const indices = Array.from(inputs).map((input) => {
        const match = input.name.match(/items\[(\d+)\]/);
        return match ? Number.parseInt(match[1], 10) : 0;
    });

    return Math.max(...indices) + 1;
};

const resetNewItemFields = () => {
    newItemName.value = '';
    newItemType.value = 'Tool';
    newItemName.focus();
};

const handleRemoveItem = (event) => {
    if (!event.target.classList.contains('remove-item-btn')) {
        return;
    }

    const row = event.target.closest('[data-removable="true"]');
    if (row && row.dataset.removable === 'true') {
        row.remove();
    }
};

itemsContainer?.addEventListener('click', handleRemoveItem);

addItemButton?.addEventListener('click', () => {
    const name = newItemName.value.trim();
    if (!name) {
        newItemName.focus();
        return;
    }

    const type = newItemType.value;
    const index = getNextIndex();
    const row = createItemCard({ name, type }, index);
    const list = type === 'Part' ? partsList : toolsList;
    list.appendChild(row);
    resetNewItemFields();
});

addFullToolListButton?.addEventListener('click', () => {
    const existing = existingToolNames();
    const weeklyRows = toolsList.querySelectorAll('tr[data-weekly="true"]');
    if (weeklyRows.length > 0) {
        weeklyRows.forEach((row) => row.remove());
        checklistTypeInput.value = 'Daily';
        addFullToolListButton.classList.remove('btn-primary', 'is-active');
        addFullToolListButton.classList.add('btn-outline-primary');
        addFullToolListButton.textContent = 'Agregar lista semanal';
        return;
    }

    let currentIndex = getNextIndex();
    weeklyTools.forEach((tool) => {
        if (existing.has(normalizeValue(tool.name))) {
            return;
        }
        const row = createItemCard({
            name: tool.name,
            type: 'Tool',
            has_counter: tool.has_counter,
            removable: false,
            weekly: true,
        }, currentIndex);
        toolsList.appendChild(row);
        currentIndex += 1;
    });

    checklistTypeInput.value = 'Weekly';
    addFullToolListButton.classList.remove('btn-outline-primary');
    addFullToolListButton.classList.add('btn-primary', 'is-active');
    addFullToolListButton.textContent = 'Lista semanal agregada';
});

const formatDate = (date) => {
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
};

const toIsoDate = (displayDate) => {
    const [day, month, year] = displayDate.split('/');
    if (!day || !month || !year) {
        return '';
    }
    return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
};

const setTodayDate = () => {
    if (checklistDateDisplay.value) {
        checklistDateInput.value = toIsoDate(checklistDateDisplay.value);
        return;
    }
    const today = new Date();
    const displayValue = formatDate(today);
    checklistDateDisplay.value = displayValue;
    checklistDateInput.value = toIsoDate(displayValue);
};

const syncDateInput = () => {
    const displayValue = checklistDateDisplay.value.trim();
    if (!displayValue) {
        checklistDateInput.value = '';
        return;
    }

    checklistDateInput.value = toIsoDate(displayValue);
};

setTodayDate();
checklistDateDisplay?.addEventListener('blur', syncDateInput);
checklistDateDisplay?.addEventListener('input', syncDateInput);

if (window.jQuery) {
    $(function () {
        const $technician = $('#technician_name');
        const $van = $('#van_name');
        const $date = $('#checklist_date_display');
        const $toolsEmpty = $('#tools-empty');
        const $partsEmpty = $('#parts-empty');

        const toggleTables = () => {
            const hasSelection = $technician.val() && $van.val();
            if (hasSelection) {
                $toolsEmpty.hide();
                $partsEmpty.hide();
            } else {
                $toolsEmpty.show();
                $partsEmpty.show();
            }
        };

        const updateQuery = () => {
            const technician = $technician.val();
            const van = $van.val();
            const date = $date.val();
            if (!technician || !van) {
                toggleTables();
                return;
            }
            const url = new URL(window.location.href);
            url.searchParams.set('technician', technician);
            url.searchParams.set('van', van);
            if (date) {
                url.searchParams.set('date', date);
            }
            window.location.replace(url.toString());
        };

        $technician.on('change', updateQuery);
        $van.on('change', updateQuery);
        $date.on('change', updateQuery);
        toggleTables();
    });
}
