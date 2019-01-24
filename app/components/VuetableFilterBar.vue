<template>
    <div class="search-wrapper">
        <input type="text" class="search" :placeholder="store.searchPlaceholder" v-model.trim="filterText" @input="doFilter">
    </div>
</template>

<script>
    import { debounce } from 'lodash';

    export default {
        props: ['store'],
        data() {
            return {
                filterText: ''
            }
        },
        created() {
            this.doFilter = debounce(() => {
                this.$events.fire('filter-set', this.filterText);
            }, 250, { leading: false });
        },
        methods: {
            resetFilter() {
                this.filterText = '';
                this.$events.fire('filter-reset');
            }
        }
    }
</script>
