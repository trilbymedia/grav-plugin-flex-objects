<template>
    <div class="flex-nested-table">
        <vuetable ref="vuetable"
                  :css="css.table"
                  :fields="store.fields || []"
                  :searchFields="store.searchFields || []"
                  :sortOrder="store.sortOrder || []"
                  :multi-sort="true"
                  :track-by="trackBy"
                  :api-mode="true"
                  :api-url="store.api"
                  :per-page="perPage"
                  :append-params="extraParams"
                  pagination-path="links.pagination"
                  :show-sort-icons="true"
                  @vuetable:pagination-data="onPaginationData"
        />

        <div class="flex-list-pagination">
            <vuetable-pagination-info ref="paginationInfo"
                                      :info-template="store.paginationInfo"
                                      :info-no-data-template="store.emptyResult"
                                      :css="css.paginationInfo"
            />
            <vuetable-pagination ref="pagination"
                                 :css="css.pagination"
                                 @vuetable-pagination:change-page="onChangePage"
            />
        </div>
    </div>
</template>

<script>
    import Vuetable from 'vuetable-2/src/components/Vuetable.vue';
    import VuetablePagination from 'vuetable-2/src/components/VuetablePagination.vue';
    import VuetablePaginationInfo from 'vuetable-2/src/components/VuetablePaginationInfo.vue';
    import VuetableCssConfig from '../VuetableCssConfig.js';

    export default {
        name: 'FlexNestedTable',
        components: {
            Vuetable,
            VuetablePagination,
            VuetablePaginationInfo
        },
        props: {
            store: {
                type: Object,
                required: true
            }
        },
        data() {
            return {
                css: VuetableCssConfig,
                perPage: this.store.perPage || 10,
                extraParams: {
                    filters: this.store.filters || {}
                }
            };
        },
        computed: {
            trackBy() {
                return this.store.trackBy || 'id';
            }
        },
        methods: {
            onPaginationData(paginationData) {
                this.$refs.pagination.setPaginationData(paginationData);
                this.$refs.paginationInfo.setPaginationData(paginationData);
            },
            onChangePage(page) {
                this.$refs.vuetable.changePage(page);
            }
        }
    }
</script>

<style>
    .flex-nested-table {
        text-align: left;
    }

    .flex-nested-table .table {
        width: 100%;
        table-layout: auto;
    }

    .flex-nested-table .table th,
    .flex-nested-table .table td {
        white-space: nowrap;
        vertical-align: middle;
        text-align: left;
    }

    .flex-nested-table .table th:last-child,
    .flex-nested-table .table td:last-child {
        text-align: right;
        width: 1%;
    }

    #directory .flex-nested-table .flex-list-pagination {
        margin: 1rem 0 0;
    }

    #directory .flex-nested-table .flex-list-pagination .flex-objects-pagination a {
        padding: 8px 16px;
    }
</style>
