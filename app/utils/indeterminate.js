document.addEventListener('click', (event) => {
    const wrapper = event.target.closest('.checkboxes.indeterminate');
    if (wrapper) {
        event.preventDefault();
        const checkbox = wrapper.querySelector('input[type="checkbox"]:not([disabled])');
        const checkStatus = wrapper.dataset._checkStatus;
        wrapper.classList.remove('status-checked', 'status-unchecked', 'status-indeterminate');
        switch (checkStatus) {
            // indeterminate, going checked
            // checked, going indeterminate
            case '1':
                wrapper.dataset._checkStatus = '2';
                checkbox.indeterminate = true;
                checkbox.checked = false;
                wrapper.classList.add('status-indeterminate');

                // el.data('checked', 2);
                // el.prop('indeterminate', false);
                // el.prop('checked', true);
                break;

            // checked, going unchecked
            // indeterminate, going unchecked
            case '2':
                wrapper.dataset._checkStatus = '0';
                checkbox.indeterminate = false;
                checkbox.checked = false;
                wrapper.classList.add('status-unchecked');

                // el.data('checked', 0);
                // el.prop('indeterminate', false);
                // el.prop('checked', false);
                break;

            // unchecked, going indeterminate
            // unchecked, going checked
            case '0':
            default:
                wrapper.dataset._checkStatus = '1';
                checkbox.indeterminate = false;
                checkbox.checked = true;
                wrapper.classList.add('status-checked');

                // el.data('checked', 1);
                // el.prop('indeterminate', true);
                break;
        }
    }
});
