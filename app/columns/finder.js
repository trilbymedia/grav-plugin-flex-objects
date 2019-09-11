import $ from 'jquery';
import Finder from '../utils/finder';

let XHRUUID = 0;
export const Instances = {};

export class FlexPages {
    constructor(container, data) {
        this.container = $(container);
        this.data = data;

        const dataLoad = this.dataLoad;

        this.finder = new Finder(
            this.container,
            (parent, callback) => {
                return dataLoad.call(this, parent, callback);
            },
            {
                labelKey: 'title',
                defaultPath: '',
                itemTrigger: '[data-flexpages-expand]',
                createItemContent: function(item) {
                    return FlexPages.createItemContent(this.config, item);
                }
            }
        );

        /*
        this.finder.$emitter.on('leaf-selected', (item) => {
            console.log('selected', item);
            this.finder.emit('create-column', () => this.createSimpleColumn(item));
        });

        this.finder.$emitter.on('item-selected', (selected) => {
            console.log('selected', selected);
            // for future use only - create column-card creation for file with details like in macOS finder
            // this.finder.$emitter('create-column', () => this.createSimpleColumn(selected));
        }); */

        this.finder.$emitter.on('column-created', () => {
            this.container[0].scrollLeft = this.container[0].scrollWidth - this.container[0].clientWidth;
        });
    }

    static createItemContent(config, item) {
        /* const frag = document.createDocumentFragment();

        const label = $(`<span title="${item[config.labelKey]}" />`);
        const iconPrepend = $('<i />');
        const iconAppend = $('<i />');
        const prependClasses = ['fa'];
        const appendClasses = ['fa'];

        // prepend icon
        if (item.children || item.type === 'dir') {
            prependClasses.push('fa-folder');
        } else if (item.type === 'root') {
            prependClasses.push('fa-sitemap');
        } else if (item.type === 'file') {
            prependClasses.push('fa-file-o');
        }

        iconPrepend.addClass(prependClasses.join(' '));

        // text label
        label.text(item[config.labelKey]).prepend(iconPrepend);
        label.appendTo(frag);

        // append icon
        if (item.children || item.type === 'dir') {
            appendClasses.push('fa-caret-right');
        }

        iconAppend.addClass(appendClasses.join(' '));
        iconAppend.appendTo(frag);*/

        const frag = document.createDocumentFragment();
        const icon = $(`<span class="fjs-icon ${item.icon} badge-${item.extras && item.extras.published ? 'published' : 'unpublished'}" />`);

        if (item.extras && item.extras.langs && item.extras.langs.length) {
            const lang = $(`<span class="badge-lang">${item.extras.langs[0]}</span>`);
            lang.appendTo(icon);
        }

        const info = $(`<span class="fjs-info"><b>${item.title}</b> <em>${item.route}</em></span>`);
        const actions = $('<span class="fjs-actions" />');

        if (item.child_count) {
            const count = $(`<span class="child-count">${item.child_count}</span>`);
            count.appendTo(actions);
        }

        if (item.extras) {
            const dotdotdot = $('<i class="fa fa-ellipsis-v fjs-action-toggle" data-flexpages-dotx3></i>');
            dotdotdot.appendTo(actions);
        }

        if (item.child_count) {
            const arrow = $('<i class="fa fa-chevron-right fjs-children" data-flexpages-expand></i>');
            arrow.appendTo(actions);
        }

        icon.appendTo(frag);
        info.appendTo(frag);
        actions.appendTo(frag);

        return frag;
    }

    static createLoadingColumn() {
        return $(`
            <div class="fjs-col leaf-col" style="overflow: hidden;">
                <div class="leaf-row">
                    <div class="grav-loading"><div class="grav-loader">Loading...</div></div>
                </div>
            </div>
        `);
    }

    static createErrorColumn(error) {
        return $(`
            <div class="fjs-col leaf-col" style="overflow: hidden;">
                <div class="leaf-row error">
                    <i class="fa fa-fw fa-warning"></i>
                    <span>${error}</span>
                </div>
            </div>
        `);
    }

    createSimpleColumn(item) {}

    dataLoad(parent, callback) {
        if (!parent) {
            return callback(this.data);
        }

        if (!parent.child_count) {
            return false;
        }

        const UUID = ++XHRUUID;
        this.startLoader();

        const gravConfig = typeof global.GravConfig !== 'undefined' ? global.GravConfig : global.GravAdmin.config;
        $.ajax({
            url: `${gravConfig.current_url}`,
            method: 'post',
            data: Object.assign({}, {
                route: b64_encode_unicode(parent.route),
                action: 'listLevel',
                id: 'flex-pages'
            }),
            success: (response) => {
                this.stopLoader();

                if (response.status === 'error') {
                    this.finder.$emitter.emit('create-column', FlexPages.createErrorColumn(response.message)[0]);
                    return false;
                }
                // stale request
                if (UUID !== XHRUUID) {
                    return false;
                }

                return callback(response.data);
            }
        });
    }

    startLoader() {
        this.loadingIndicator = FlexPages.createLoadingColumn();
        this.finder.$emitter.emit('create-column', this.loadingIndicator[0]);

        return this.loadingIndicator;
    }

    stopLoader() {
        return this.loadingIndicator && this.loadingIndicator.remove();
    }
}

export const b64_encode_unicode = (str) => {
    return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g,
        function toSolidBytes(match, p1) {
            return String.fromCharCode('0x' + p1);
        }));
};

export const b64_decode_unicode = (str) => {
    return decodeURIComponent(atob(str).split('').map(function(c) {
        return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
    }).join(''));
};
