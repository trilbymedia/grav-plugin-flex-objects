<template>
    <div @click="onContainerClicked">
        <vuetable ref="vuetable"
                  :css="css.table"
                  :fields="store.fields || []"
                  :searchFields="store.searchFields || []"
                  :sortOrder="store.sortOrder"
                  :multi-sort="true"
                  :detail-row-component="detailEnabled ? 'flex-detail-row' : ''"
                  :track-by="trackBy"

                  :api-mode="true"
                  :api-url="store.api"
                  :per-page="perPage"
                  :append-params="extraParams"
                  pagination-path="links.pagination"
                  :show-sort-icons="true"
                  @vuetable:cell-clicked="onCellClicked"
                  @vuetable:pagination-data="onPaginationData"
                  @vuetable:loading="onVuetableLoading"
                  @vuetable:load-success="onVueTableLoadSuccess"
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
    import Vue from 'vue';
    import Vuetable from 'vuetable-2/src/components/Vuetable.vue';
    import VuetablePagination from "vuetable-2/src/components/VuetablePagination.vue";
    import VuetablePaginationInfo from 'vuetable-2/src/components/VuetablePaginationInfo.vue';
    import VuetableCssConfig from "../VuetableCssConfig.js";
    import FlexDetailRow from './DetailRow.vue';

    import set from 'lodash/set';
    import unset from 'lodash/unset';

    Vue.component('flex-detail-row', FlexDetailRow);

    export default {
        props: ['store', 'value'],
        components: {
            Vuetable,
            VuetablePagination,
            VuetablePaginationInfo
        },
        data: () => ({
            css: VuetableCssConfig,
            perPage: 10,
            data: [],
            extraParams: {}
        }),
        computed: {
            detailEnabled() {
                return !!(this.store.detail && this.store.detail.enabled);
            },
            trackBy() {
                return this.store.trackBy || 'id';
            }
        },
        created() {
            this.perPage = this.store.perPage;
            this.data = Object.values(this.store.data);
        },
        mounted() {
            this.$refs.vuetable.setData(this.store.data);
            this.$events.$on('filter-set', event => this.onFilterSet(event));
            this.$events.$on('filter-reset', event => this.onFilterReset());
            this.$events.$on('filter-perPage', event => this.onFilterPerPage(event));
        },
        methods: {
            onPaginationData(paginationData) {
                this.$refs.pagination.setPaginationData(paginationData);
                this.$refs.paginationInfo.setPaginationData(paginationData);
            },
            onFilterSet (filterText) {
                set(this.extraParams, 'filter', filterText);
                Vue.nextTick(() => this.$refs.vuetable.refresh());
            },
            onFilterReset () {
                unset(this.extraParams, 'filter');
                Vue.nextTick(() => this.$refs.vuetable.refresh());
            },
            onFilterPerPage (limit) {
                // console.log('onFilterPerPage', limit, this.store.data);
                this.perPage = limit || this.$refs.paginationInfo.tablePagination.total;
                // this.$refs.vuetable.perPage = limit;
                Vue.nextTick(() => this.$refs.vuetable.refresh());
            },
            onCellClicked(dataItem, field, event) {
                if (!this.detailEnabled || !field || field.name !== 'detail_toggle') {
                    return;
                }

                const toggle = event && event.target && event.target.closest
                    ? event.target.closest('.flex-detail-toggle')
                    : null;
                if (!toggle) {
                    return;
                }

                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                this.$refs.vuetable.toggleDetailRow(dataItem.id);
            },
            onContainerClicked(event) {
                if (!this.detailEnabled || !event || !event.target || !event.target.closest) {
                    return;
                }

                const closeButton = event.target.closest('.flex-detail-close');
                if (!closeButton) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                const detailId = closeButton.getAttribute('data-detail-id');
                if (detailId) {
                    this.$refs.vuetable.hideDetailRow(detailId);
                }
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

<style>
    .flex-detail-column,
    .flex-detail-cell {
        width: 1%;
        white-space: nowrap;
    }

    .flex-detail-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        padding: 0;
        border: 0;
        background: transparent;
        color: #7f8c9b;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.15s ease, color 0.15s ease;
    }

    .flex-detail-toggle:hover,
    .flex-detail-toggle:focus {
        background: #eef5fb;
        color: #2f8fdd;
        outline: none;
    }

    .flex-detail-toggle i {
        font-size: 18px;
        line-height: 1;
    }

    .flex-detail {
        padding: 8px 0 2px;
    }

    .flex-detail-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 8px;
        color: inherit;
        font-size: inherit;
        font-weight: 400;
        line-height: inherit;
        text-align: left;
    }

    .flex-detail-title-main {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .flex-detail-close {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        padding: 0;
        border: 0;
        background: #f6f6f6;
        color: #7f8c9b;
        font-size: 24px;
        line-height: 1;
        cursor: pointer;
        border-radius: 4px;
    }

    .flex-detail-close i {
        font-size: 16px;
        line-height: 1;
    }

    .flex-detail-close:hover,
    .flex-detail-close:focus {
        background: #ececec;
        color: #4c6278;
        outline: none;
    }

</style>
