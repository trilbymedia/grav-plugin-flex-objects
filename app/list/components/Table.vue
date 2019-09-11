<template>
    <div>
        <vuetable ref="vuetable"
                  :css="css.table"
                  :fields="store.fields || []"
                  :searchFields="store.searchFields || []"
                  :sortOrder="store.sortOrder"
                  :multi-sort="true"

                  :api-mode="true"
                  :api-url="store.api"
                  :per-page="store.perPage || perPage"
                  :append-params="extraParams"
                  pagination-path="links.pagination"
                  :show-sort-icons="true"
                  @vuetable:pagination-data="onPaginationData"
                  @vuetable:loading="onVuetableLoading"
                  @vuetable:load-success="onVueTableLoadSuccess"
        ></vuetable>

        <div class="flex-list-pagination">
            <vuetable-pagination-info ref="paginationInfo"
                                      :info-template="store.paginationInfo"
                                      :info-no-data-template="store.emptyResult"
                                      :css="css.paginationInfo"
            ></vuetable-pagination-info>
            <vuetable-pagination ref="pagination"
                                 :css="css.pagination"
                                 @vuetable-pagination:change-page="onChangePage"
            ></vuetable-pagination>
        </div>
    </div>
</template>

<script>
    import Vue from 'vue';
    import Vuetable from 'vuetable-2/src/components/Vuetable.vue';
    import VuetablePagination from "vuetable-2/src/components/VuetablePagination.vue";
    import VuetablePaginationInfo from 'vuetable-2/src/components/VuetablePaginationInfo.vue';
    import VuetableCssConfig from "../VuetableCssConfig.js";

    import _ from 'lodash';

    export default {
        props: ['store', 'value'],
        components: {Vuetable, VuetablePagination, VuetablePaginationInfo},
        data: () => ({
            css: VuetableCssConfig,
            perPage: 10,
            data: [],
            extraParams: {}
        }),
        created() {
            this.data = Object.values(this.store.data);
        },
        mounted() {
            this.$refs.vuetable.setData(this.store.data);
            this.$events.$on('filter-set', event => this.onFilterSet(event));
            this.$events.$on('filter-reset', event => this.onFilterReset());
        },
        methods: {
            onPaginationData(paginationData) {
                this.$refs.pagination.setPaginationData(paginationData);
                this.$refs.paginationInfo.setPaginationData(paginationData);
            },
            onFilterSet (filterText) {
                _.set(this.extraParams, 'filter', filterText);
                Vue.nextTick(() => this.$refs.vuetable.refresh());
            },
            onFilterReset () {
                _.unset(this.extraParams, 'filter');
                Vue.nextTick(() => this.$refs.vuetable.refresh());
            },
            onChangePage(page) {
                this.$refs.vuetable.changePage(page);
            },
            onVuetableLoading() {
                this.$emit('input', true);
            },
            onVueTableLoadSuccess() {
                this.$emit('input', false);
            }
        }
    }
</script>
