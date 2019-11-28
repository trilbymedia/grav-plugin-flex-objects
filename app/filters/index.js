import '../utils/indeterminate';
import { ReLoad } from '../columns';

document.addEventListener('click', (event) => {
    const filterType = event.target && event.target.dataset.filters;

    if (filterType === 'reset') {
        const filters = event.target.closest('#pages-filters');
        (filters.querySelectorAll('input[type="text"]') || []).forEach((input) => {
            input.value = '';
        });

        (filters.querySelectorAll('input[type="checkbox"]') || []).forEach((input) => {
            const wrapper = input.closest('.checkboxes');
            if (wrapper) {
                wrapper.classList.remove('status-checked', 'status-unchecked', 'status-indeterminate');
                wrapper.dataset._checkStatus = '0';
                wrapper.classList.add('status-unchecked');
            }

            input.indeterminate = false;
            input.checked = false;
            input.value = '';
        });

        return false;
    }

    if (filterType === 'apply') {
        ReLoad();
        return false;
    }
});

/*
document.addEventListener('input', (event) => {
    if ((event.detail || event).target.tagName === 'INPUT' && (event.detail || event).target.closest('#pages-filters')) {
        event.preventDefault();

        // const activeItem = (FlexPagesInstance.finder.findLastActive() || { item: [{ _item: null }] }).item[0]._item;

        ReLoad();
        /!* FlexPagesInstance.dataLoad(null, (data) => FlexPagesInstance.finder.goTo(data, getInitialRoute()), getFilters());*!/
    }
});
*/
