import Vue from 'vue';
import App from './App.vue';

const ID = '#flex-objects-list';
const element = document.querySelector(ID);

if (element) {
    const initialStore = element.dataset.initialStore;

    new Vue({ // eslint-disable-line no-new
        el: ID,
        render: h => h(App, {
            props: {initialStore}
        })
    });
}
