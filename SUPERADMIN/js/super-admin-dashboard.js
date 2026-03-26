const scorePassword = (value) => {
    let score = 0;

    if (value.length >= 12) score += 1;
    if (/[A-Z]/.test(value)) score += 1;
    if (/[a-z]/.test(value)) score += 1;
    if (/\d/.test(value)) score += 1;
    if (/[^A-Za-z0-9]/.test(value)) score += 1;

    return score;
};

const applyStrengthUI = (input, indicator) => {
    const score = scorePassword(input.value);
    let label = 'Weak';
    let state = 'weak';

    if (score >= 5) {
        label = 'Super Strong';
        state = 'super-strong';
    } else if (score === 4) {
        label = 'Strong';
        state = 'strong';
    } else if (score === 3) {
        label = 'Medium';
        state = 'medium';
    }

    indicator.textContent = `Strength: ${label}`;
    indicator.className = `pass-indicator ${state}`;
    input.classList.remove('weak-border', 'medium-border', 'strong-border');

    if (state === 'weak') {
        input.classList.add('weak-border');
    } else if (state === 'medium') {
        input.classList.add('medium-border');
    } else {
        input.classList.add('strong-border');
    }
};

const setupEditableRows = () => {
    document.querySelectorAll('.user-row').forEach((row) => {
        const editButton = row.querySelector('[data-edit-btn]');
        const saveButton = row.querySelector('[data-save-btn]');
        const cancelButton = row.querySelector('[data-cancel-btn]');
        const inputs = Array.from(row.querySelectorAll('.table-input'));
        const rowId = row.dataset.rowId;
        const saveForm = rowId ? document.getElementById(`save-form-${rowId}`) : null;
        const originals = inputs.map((input) => input.value);

        if (!editButton || !saveButton || !cancelButton || !saveForm) {
            return;
        }

        editButton.addEventListener('click', () => {
            inputs.forEach((input) => input.removeAttribute('readonly'));
            editButton.hidden = true;
            saveButton.hidden = false;
            cancelButton.hidden = false;
        });

        cancelButton.addEventListener('click', () => {
            inputs.forEach((input, index) => {
                input.value = originals[index] ?? '';
                input.setAttribute('readonly', 'readonly');
            });

            editButton.hidden = false;
            saveButton.hidden = true;
            cancelButton.hidden = true;
        });

        saveButton.addEventListener('click', () => {
            const valuesByField = {};

            inputs.forEach((input) => {
                valuesByField[input.dataset.field ?? ''] = input.value;
            });

            saveForm.querySelector('[data-save-field="full_name"]').value = valuesByField.full_name ?? '';
            saveForm.querySelector('[data-save-field="email"]').value = valuesByField.email ?? '';
            saveForm.querySelector('[data-save-field="phone"]').value = valuesByField.phone ?? '';
            saveForm.submit();
        });
    });
};

document.addEventListener('DOMContentLoaded', () => {
    const temporaryPassword = document.getElementById('password');
    const temporaryPasswordIndicator = document.getElementById('tempPassStrength');

    if (temporaryPassword && temporaryPasswordIndicator) {
        temporaryPassword.addEventListener('input', () => {
            applyStrengthUI(temporaryPassword, temporaryPasswordIndicator);
        });
    }

    setupEditableRows();
});
