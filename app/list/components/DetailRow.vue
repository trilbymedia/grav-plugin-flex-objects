<template>
    <div class="flex-detail-row-content" v-if="detailStore">
        <div class="flex-detail">
            <div class="flex-detail-title">
                <div class="flex-detail-title-main">
                    <span>{{ rowData.detail_title }}</span>
                </div>
                <button
                    type="button"
                    class="flex-detail-close"
                    :data-detail-id="rowData.id"
                    :aria-label="rowData.detail_close_label || 'Close'"
                >
                    <i class="fa fa-times" aria-hidden="true"></i>
                </button>
            </div>

            <flex-nested-table :store="detailStore" />
        </div>
    </div>
</template>

<script>
    import FlexNestedTable from './NestedTable.vue';

    export default {
        name: 'FlexDetailRow',
        components: {
            FlexNestedTable
        },
        props: {
            rowData: {
                type: Object,
                required: true
            },
            rowIndex: {
                type: Number,
                default: 0
            }
        },
        computed: {
            detailStore() {
                return this.rowData.detail_store || null;
            }
        }
    }
</script>

<style scoped>
    .flex-detail-row-content {
        padding: 0;
    }
</style>
