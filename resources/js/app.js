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

// Só as linhas/células desta tabela, nunca as de tabelas encaixadas dentro dela.
const ownRows = (table) => Array.from(table.rows);
const ownHeaderCells = (table) => (table.tHead ? Array.from(table.tHead.rows[0]?.cells ?? []) : []);
const isNestedTable = (table) => Boolean(table.parentElement?.closest('table'));

const applyTableLabels = (table) => {
    const headerCells = ownHeaderCells(table);
    const labels = headerCells.map(columnLabel);

    Array.from(table.tBodies).flatMap((tbody) => Array.from(tbody.rows)).forEach((row) => {
        Array.from(row.cells).forEach((cell, index) => {
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

        ownRows(table).forEach((row) => {
            const cell = row.cells[index];

            // Linhas de detalhe com uma única célula (colspan) ficam sempre visíveis.
            if (cell && row.cells.length > 1) {
                cell.hidden = !visible;
            }
        });
    });
};

const setupColumnPicker = (table, index) => {
    const headerCells = ownHeaderCells(table);

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
    const tables = Array.from(document.querySelectorAll('.overflow-x-auto table, .overflow-hidden > table'))
        .filter((table) => !isNestedTable(table));

    tables.forEach((table, index) => {
        applyTableLabels(table);
        setupColumnPicker(table, index);
    });
});
