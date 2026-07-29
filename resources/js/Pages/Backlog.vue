<script setup>
import { Head, Link as InertiaLink, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, reactive } from 'vue';
import Dashboard from '../Layouts/Dashboard.vue';

const props = defineProps({
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    models: { type: Array, required: true },
    gateways: { type: Array, required: true },
    callers: { type: Array, required: true },
    entries: { type: Array, required: true },
    oldestQueued: { type: String, default: null },
    total: { type: Number, required: true },
    pagination: { type: Object, required: true },
    summary: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const columns = [
    { key: 'id', label: 'Entry' },
    { key: 'model', label: 'Model' },
    { key: 'gateway', label: 'Gateway' },
    { key: 'caller', label: 'Caller' },
    { key: 'status', label: 'Status' },
    { key: 'attempts', label: 'Attempts', align: 'right' },
    { key: 'waited', label: 'Waited', align: 'right' },
    { key: 'duration', label: 'Duration', align: 'right' },
    { key: 'created', label: 'Created', align: 'right' },
];

const form = reactive({
    q: props.filters.search,
    model: props.filters.model,
    gateway: props.filters.gateway,
    caller: props.filters.caller,
});

/*
 | `cursor` is dropped on every filter change: the listing is cursor paginated,
 | and a cursor taken from one result set silently skips rows in another.
 */
function search() {
    router.get(props.urls.backlog, { ...form, status: props.filters.status }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

/*
 | Replaces the old <meta http-equiv="refresh">. A partial reload keeps scroll
 | position, filter focus and the typed query, and asks only for what changes.
 */
let timer = null;

onMounted(() => {
    timer = setInterval(() => {
        router.reload({ only: ['entries', 'statuses', 'total', 'oldestQueued', 'summary'] });
    }, 15000);
});

onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <Head title="Backlog" />

    <Dashboard>
        <header class="flex flex-wrap items-end justify-between gap-3">
            <PageHeading>
                LLM Gateway
                <template #description>
                    {{ summary.workers }} {{ summary.workers === 1 ? 'worker' : 'workers' }}
                    &rarr; {{ summary.endpoint ?? 'no default gateway' }}
                </template>
            </PageHeading>
        </header>

        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
            <InertiaLink
                v-for="status in statuses"
                :key="status.value"
                :href="status.href"
                preserve-scroll
            >
                <Card
                    hoverable
                    :class="status.active ? 'ring-2 ring-accent' : ''"
                >
                    <Metric
                        :label="status.label"
                        :value="status.count"
                    />
                </Card>
            </InertiaLink>
        </div>

        <Alert
            v-if="oldestQueued"
            variant="warning"
            class="mt-3"
        >
            Oldest queued entry is waiting since {{ oldestQueued }}.
        </Alert>

        <Card class="mt-6">
            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="search"
            >
                <div class="min-w-64 flex-1 sm:max-w-[50%]">
                    <Field
                        label="Entry ID"
                        name="q"
                    >
                        <Input
                            v-model="form.q"
                            name="q"
                            type="search"
                            placeholder="resp_…"
                        />
                    </Field>
                </div>

                <div class="min-w-40 flex-1">
                    <Field
                        label="Model"
                        name="model"
                    >
                        <Select
                            v-model="form.model"
                            name="model"
                            :options="models"
                            placeholder="All models"
                        />
                    </Field>
                </div>

                <div class="min-w-40 flex-1">
                    <Field
                        label="Gateway"
                        name="gateway"
                    >
                        <Select
                            v-model="form.gateway"
                            name="gateway"
                            :options="gateways"
                            placeholder="All gateways"
                        />
                    </Field>
                </div>

                <div class="min-w-40 flex-1">
                    <Field
                        label="Caller"
                        name="caller"
                    >
                        <Select
                            v-model="form.caller"
                            name="caller"
                            :options="callers"
                            placeholder="All callers"
                        />
                    </Field>
                </div>

                <div class="ml-auto flex items-center gap-2">
                    <Button type="submit">Search</Button>

                    <Link
                        v-if="!filters.areEmpty"
                        :as="InertiaLink"
                        :href="urls.backlog"
                        tone="muted"
                    >
                        Reset
                    </Link>
                </div>
            </form>
        </Card>

        <Card
            class="mt-4"
            :padded="false"
        >
            <DataTable
                :columns="columns"
                :rows="entries"
                row-key="id"
            >
                <template #cell-id="{ row }">
                    <IdCell :id="row.id" />
                </template>

                <template #cell-status="{ row }">
                    <StatusBadge
                        :variant="row.status.tone"
                        :label="row.status.label"
                    />
                </template>

                <template #cell-caller="{ row }">
                    <span
                        :title="row.caller.hash ?? 'queued without a bearer token'"
                        :class="row.caller.named ? '' : 'font-mono text-xs text-dim'"
                    >
                        {{ row.caller.label }}
                    </span>
                </template>

                <!-- The rounded span with the measurement it came from, so a
                     "25m" is never the only number on the page. -->
                <template #cell-waited="{ row }">
                    <span :title="row.waitedExact ?? undefined">{{ row.waited }}</span>
                </template>

                <template #cell-duration="{ row }">
                    <span :title="row.durationExact ?? undefined">{{ row.duration }}</span>
                </template>

                <template #cell-created="{ row }">
                    <span :title="row.created.exact">{{ row.created.short }}</span>
                </template>

                <template #row-actions="{ row }">
                    <Button
                        :as="InertiaLink"
                        :href="row.href"
                        variant="ghost"
                        size="sm"
                    >
                        Details
                    </Button>
                </template>

                <template #empty>
                    <EmptyState
                        icon="search"
                        title="No entries"
                        description="No entries match this filter."
                    />
                </template>

                <template #footer>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-muted">
                        <span>
                            Showing {{ entries.length }} of {{ total }}
                            {{ total === 1 ? 'entry' : 'entries' }}
                        </span>

                        <Pagination
                            v-if="pagination.prev || pagination.next"
                            :prev-href="pagination.prev"
                            :next-href="pagination.next"
                            :on-navigate="(href) => router.visit(href, { preserveScroll: true })"
                        />

                        <span>Auto-refreshes every 15&thinsp;s</span>
                    </div>
                </template>
            </DataTable>
        </Card>
    </Dashboard>
</template>
