import '../utils/indeterminate';
import { FlexPagesInstance, getInitialRoute } from '../columns';
import getFilters from '../utils/get-filters';

document.addEventListener('input', (event) => {
    if ((event.detail || event).target.tagName === 'INPUT' && (event.detail || event).target.closest('#pages-filters')) {
        event.preventDefault();

        const activeItem = (FlexPagesInstance.finder.findLastActive() || { item: [{ _item: null }] }).item[0]._item;

        FlexPagesInstance.dataLoad(activeItem, (data) => FlexPagesInstance.finder.goTo(data, getInitialRoute()), getFilters());
    }
});
