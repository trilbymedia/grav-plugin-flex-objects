<template>
    <div>
        <div class="search-wrapper">
            <input type="text" class="search" :placeholder="store.searchPlaceholder" v-model.trim="searchFor" @input="onInput">
        </div>
        <vuetable ref="vuetable"
                  :css="css.table"
                  :fields="store.fields || []"
                  :searchFields="store.searchFields || []"
                  :sortOrder="store.sortOrder"
                  :api-mode="false"
                  :per-page="store.perPage || perPage"
                  :data-total="dataCount"
                  :data-manager="dataManager"
                  pagination-path="pagination"
                  :show-sort-icons="true"
                  @vuetable:pagination-data="onPaginationData"
        ></vuetable>

        <vuetable-pagination ref="pagination"
                             :css="css.pagination"
                             @vuetable-pagination:change-page="onChangePage"
        ></vuetable-pagination>
    </div>
</template>

<script>
    import Vuetable from 'vuetable-2/src/components/Vuetable.vue';
    import VuetablePagination from "vuetable-2/src/components/VuetablePagination.vue";
    import VuetableCssConfig from "./VuetableCssConfig.js";

    import _ from 'lodash';
    // import FlexTable from './components/FlexTable.vue';

    export default {
        props: ['initialStore'],
        components: {Vuetable, VuetablePagination},
        data: () => ({
            css: VuetableCssConfig,
            perPage: 10,
            dataCount: 0,
            searchFor: '',
            searchPlaceholder: 'Filter...',
            data: []
        }),
        watch: {
            data(newVal, oldVal) {
                this.$refs.vuetable.refresh();
            }
        },
        computed: {
            store() {
                return JSON.parse(this.initialStore || '{}');
            }
        },
        created() {
            this.data = Object.values(this.store.data);
        },
        mounted() {
            this.$refs.vuetable.setData(this.data);
        },
        methods: {
            onInput() {
                this.dataManager(this.$refs.vuetable.sortOrder, 'pagination');
                this.$refs.vuetable.refresh();
            },
            onPaginationData(paginationData) {
                this.$refs.pagination.setPaginationData(paginationData);
            },
            onChangePage(page) {
                this.$refs.vuetable.changePage(page);
            },
            dataManager(sortOrder, pagination) {
                if (this.data.length < 1) return;

                let local = this.data;

                if (this.searchFor) {
                    local = this.filter(local);
                }

                // sortOrder can be empty, so we have to check for that as well
                if (sortOrder.length > 0) {
                    local = _.orderBy(
                        local,
                        sortOrder[0].sortField,
                        sortOrder[0].direction
                    );
                }

                pagination = this.$refs.vuetable.makePagination(local.length);
                return {
                    pagination: pagination,
                    data: _.slice(local, pagination.from - 1, pagination.to)
                };
            },
            filter(data = this.data) {
                // the text should be case insensitive
                const txt = new RegExp(this.searchFor, 'i');

                // search on name, email, and nickname
                return _.filter(data, (item) => {
                    let found = false;

                    this.store.searchFields.forEach((field) => {
                        found |= (item[field] || '').toString().search(txt) >= 0;
                    });

                    return found;
                });
            }
        }
    }
</script>
