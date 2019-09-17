import $ from 'jquery';
import { b64_decode_unicode, b64_encode_unicode, FlexPages } from './finder';
import { isEnabled, getCookie, setCookie } from 'tiny-cookie';

const container = document.querySelector('#pages-content-wrapper');

export const getInitialRoute = () => {
    if (!isEnabled) {
        return '';
    }

    const parsed = JSON.parse(b64_decode_unicode(getCookie('grav-admin-flexpages') || 'e30='));
    return parsed.route || '';
};

export const setInitialRoute = ({ route = '', filters = {}, options = { expires: '1Y' } } = {}) => {
    if (!isEnabled) {
        return '';
    }

    return setCookie('grav-admin-flexpages', b64_encode_unicode(JSON.stringify({ route, filters })), options);
};

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
                route: b64_encode_unicode(getInitialRoute()),
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
