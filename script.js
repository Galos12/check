const itemsContainer = document.getElementById('items-container');
const addItemButton = document.getElementById('add-item-btn');
const newItemName = document.getElementById('new_item_name');
const newItemType = document.getElementById('new_item_type');

const createItemCard = ({ name, type }, index) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'col-md-6 col-lg-4 mb-3';
    wrapper.dataset.removable = 'true';

    const card = document.createElement('div');
    card.className = 'form-check checklist-item p-3 border rounded';

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
    label.className = 'form-check-label fw-semibold';
    label.setAttribute('for', `item-${index}`);
    label.textContent = name;

    const badge = document.createElement('span');
    badge.className = 'badge bg-secondary-subtle text-secondary-emphasis ms-2';
    badge.textContent = type;

    const removeButton = document.createElement('button');
    removeButton.className = 'btn btn-sm btn-link text-danger remove-item-btn';
    removeButton.type = 'button';
    removeButton.textContent = 'Remove';

    label.appendChild(badge);
    card.append(checkbox, nameInput, typeInput, label, removeButton);
    wrapper.appendChild(card);

    return wrapper;
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

    const card = event.target.closest('[data-removable="true"]');
    if (card) {
        card.remove();
    }
};

itemsContainer.addEventListener('click', handleRemoveItem);

addItemButton.addEventListener('click', () => {
    const name = newItemName.value.trim();
    if (!name) {
        newItemName.focus();
        return;
    }

    const type = newItemType.value;
    const index = getNextIndex();
    const card = createItemCard({ name, type }, index);
    itemsContainer.appendChild(card);
    resetNewItemFields();
});
