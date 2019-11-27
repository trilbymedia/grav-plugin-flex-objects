import '../utils/indeterminate';
import { FlexPagesInstance, getInitialRoute, ReLoad } from '../columns';
import getFilters from '../utils/get-filters';

document.addEventListener('click', (event) => {
    if (event.target && event.target.dataset.filters === 'apply') {
        ReLoad();
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
