import './bootstrap';

const storageKeyForTable = (table, index) => {
    const explicitKey = table.dataset.columnKey;

    if (explicitKey) {
        return `table-columns:${explicitKey}`;
    }

    const path = window.location.pathname.replace(/\/\d+(?=\/|$)/g, '/:id');

    return `table-columns:${path}:${index}`;
};

const columnLabel = (cell, index) => {
    const text = cell.textContent.replace(/\s+/g, ' ').trim();

    return text || `Coluna ${index + 1}`;
};

const applyTableLabels = (table) => {
    const headerCells = Array.from(table.querySelectorAll('thead th'));
    const labels = headerCells.map(columnLabel);

    table.querySelectorAll('tbody tr').forEach((row) => {
        Array.from(row.children).forEach((cell, index) => {
            if (labels[index]) {
                cell.dataset.columnLabel = labels[index];
            }
        });
    });
};

const setColumnVisibility = (table, checkboxes) => {
    const visibleColumns = checkboxes
        .map((checkbox) => checkbox.checked)
        .filter(Boolean).length;

    checkboxes.forEach((checkbox, index) => {
        const visible = checkbox.checked || visibleColumns === 0;

        table.querySelectorAll('tr').forEach((row) => {
            const cell = row.children[index];

            if (cell) {
                cell.hidden = !visible;
            }
        });
    });
};

const setupColumnPicker = (table, index) => {
    const headerCells = Array.from(table.querySelectorAll('thead th'));

    if (headerCells.length < 3 || table.dataset.columnsReady === 'true') {
        return;
    }

    table.dataset.columnsReady = 'true';

    const key = storageKeyForTable(table, index);
    let saved = null;

    try {
        saved = JSON.parse(localStorage.getItem(key) || 'null');
    } catch {
        localStorage.removeItem(key);
    }

    const wrapper = table.closest('.overflow-x-auto, .overflow-hidden');

    if (!wrapper) {
        return;
    }

    const controls = document.createElement('details');
    controls.className = 'column-picker print:hidden';

    const summary = document.createElement('summary');
    summary.textContent = 'Colunas';
    summary.className = 'column-picker-summary';
    controls.append(summary);

    const panel = document.createElement('div');
    panel.className = 'column-picker-panel';
    controls.append(panel);

    const checkboxes = headerCells.map((cell, cellIndex) => {
        const label = document.createElement('label');
        label.className = 'column-picker-option';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = Array.isArray(saved) ? saved[cellIndex] !== false : true;

        const text = document.createElement('span');
        text.textContent = columnLabel(cell, cellIndex);

        label.append(checkbox, text);
        panel.append(label);

        checkbox.addEventListener('change', () => {
            const state = checkboxes.map((item) => item.checked);
            localStorage.setItem(key, JSON.stringify(state));
            setColumnVisibility(table, checkboxes);
        });

        return checkbox;
    });

    const actions = document.createElement('div');
    actions.className = 'column-picker-actions';

    const showAll = document.createElement('button');
    showAll.type = 'button';
    showAll.textContent = 'Mostrar todas';
    showAll.addEventListener('click', () => {
        checkboxes.forEach((checkbox) => {
            checkbox.checked = true;
        });

        localStorage.removeItem(key);
        setColumnVisibility(table, checkboxes);
    });

    actions.append(showAll);
    panel.append(actions);

    wrapper.before(controls);
    setColumnVisibility(table, checkboxes);
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.overflow-x-auto table, .overflow-hidden > table').forEach((table, index) => {
        applyTableLabels(table);
        setupColumnPicker(table, index);
    });
});
