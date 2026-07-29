<script setup>
import { Head, Link as InertiaLink, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Dashboard from '../../Layouts/Dashboard.vue';

defineProps({
    gateways: { type: Array, required: true },
    urls: { type: Object, required: true },
});

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'isDefault', label: 'Default' },
    { key: 'isActive', label: 'Active' },
    { key: 'protocol', label: 'Protocol' },
    { key: 'defaultModel', label: 'Default model' },
    { key: 'endpoint', label: 'Endpoint' },
    { key: 'callerBaseUrl', label: 'Caller base URL' },
    { key: 'entries', label: 'Entries', align: 'right' },
];

const retiring = ref(null);

function retire() {
    router.delete(retiring.value.destroyUrl, {
        onFinish: () => (retiring.value = null),
    });
}
</script>

<template>
    <Head title="Gateways" />

    <Dashboard>
        <header class="flex flex-wrap items-end justify-between gap-3">
            <PageHeading>
                Gateways
                <template #description>
                    Where requests are sent. A caller picks one by putting its id in front of
                    <code>/v1</code>; a caller that does not gets the default.
                </template>
            </PageHeading>

            <Button
                :as="InertiaLink"
                :href="urls.create"
            >
                New gateway
            </Button>
        </header>

        <Card
            class="mt-6"
            :padded="false"
        >
            <DataTable
                :columns="columns"
                :rows="gateways"
                row-key="id"
            >
                <template #cell-isDefault="{ row }">
                    <Badge
                        v-if="row.isDefault"
                        variant="success"
                        size="sm"
                    >
                        yes
                    </Badge>
                    <span
                        v-else
                        class="text-dim"
                    >no</span>
                </template>

                <template #cell-isActive="{ row }">
                    <span v-if="row.isActive">yes</span>
                    <Badge
                        v-else
                        variant="danger"
                        size="sm"
                    >
                        no
                    </Badge>
                </template>

                <template #cell-protocol="{ row }">
                    <Badge
                        :variant="row.protocol.tone"
                        size="sm"
                    >
                        {{ row.protocol.label }}
                    </Badge>
                </template>

                <template #cell-defaultModel="{ row }">
                    <span :class="row.defaultModel ? 'font-mono text-xs' : 'text-dim'">
                        {{ row.defaultModel ?? '—' }}
                    </span>
                </template>

                <template #cell-endpoint="{ row }">
                    <span
                        class="block max-w-xs truncate font-mono text-xs text-muted"
                        :title="row.endpoint"
                    >{{ row.endpoint }}</span>
                </template>

                <template #cell-callerBaseUrl="{ row }">
                    <span class="flex items-center gap-2">
                        <code
                            class="font-mono text-xs text-muted"
                            :title="row.callerBaseUrl"
                        >/{{ row.shortId }}…/v1</code>
                        <CopyButton :value="row.callerBaseUrl" />
                    </span>
                </template>

                <template #cell-entries="{ row }">
                    <Link
                        :as="InertiaLink"
                        :href="row.entriesUrl"
                        tone="muted"
                    >
                        {{ row.entries }}
                    </Link>
                </template>

                <template #row-actions="{ row }">
                    <div class="flex items-center justify-end gap-2">
                        <Button
                            v-if="!row.isDefault"
                            variant="ghost"
                            size="sm"
                            @click="router.post(row.defaultUrl)"
                        >
                            Make default
                        </Button>

                        <Button
                            :as="InertiaLink"
                            :href="row.editUrl"
                            variant="ghost"
                            size="sm"
                        >
                            Edit
                        </Button>

                        <Button
                            v-if="!row.isDefault"
                            variant="danger"
                            size="sm"
                            @click="retiring = row"
                        >
                            Retire
                        </Button>
                    </div>
                </template>

                <template #empty>
                    <EmptyState
                        icon="search"
                        title="No gateways yet"
                        description="Nothing can be queued until one exists."
                    />
                </template>
            </DataTable>
        </Card>

        <p class="mt-4 text-xs text-muted">
            Retiring a gateway is a soft delete, so the entries it answered keep their attribution.
            Deactivating one stops it taking new requests without touching what is already queued for it.
        </p>

        <Modal
            :model-value="retiring !== null"
            title="Retire this gateway?"
            :description="retiring
                ? `Queued requests already accepted for “${retiring.name}” still run. The entries it answered keep their attribution.`
                : ''"
            @update:model-value="retiring = null"
        >
            <template #footer>
                <Button
                    variant="ghost"
                    @click="retiring = null"
                >
                    Cancel
                </Button>
                <Button
                    variant="danger"
                    @click="retire"
                >
                    Retire
                </Button>
            </template>
        </Modal>
    </Dashboard>
</template>
