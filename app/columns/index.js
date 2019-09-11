import $ from 'jquery';
import { FlexPages } from './finder';

const container = document.querySelector('#pages-content-wrapper');

if (container) {
    const loader = container.querySelector('.grav-loading');
    const content = container.querySelector('#pages-columns');
    const gravConfig = typeof global.GravConfig !== 'undefined' ? global.GravConfig : global.GravAdmin.config;

    if (loader && content) {

        loader.style.display = 'block';
        content.innerHTML = '';

        $.ajax({
            url: `${gravConfig.current_url}`,
            method: 'post',
            data: Object.assign({}, {
                route: '',
                initial: true,
                action: 'listLevel'
            }),
            success(response) {
                loader.style.display = 'none';

                if (response.status === 'error') {
                    content.innerHTML = response.message;
                    return true;
                }

                return new FlexPages(content, response.data);
            }
        });
    }
}
