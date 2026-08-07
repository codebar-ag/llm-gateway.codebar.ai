<script setup>
import { Head, Link as InertiaLink, router, usePoll } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Dashboard from '../layouts/Dashboard.vue';

const props = defineProps({
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    kinds: { type: Array, required: true },
    models: { type: Array, required: true },
    gateways: { type: Array, required: true },
    callers: { type: Array, required: true },
    entries: { type: Array, required: true },
    total: { type: Number, required: true },
    pagination: { type: Object, required: true },
    urls: { type: Object, required: true },
});

/*
 | Nine columns did not fit, so the listing keeps what someone scans for and
 | leaves the rest to the entry page: status first, then the target as one
 | column (gateway over model), then who asked and when. Attempts, waited and
 | duration live on the entry itself.
 */
const columns = [
    { key: 'id', label: 'Entry' },
    { key: 'status', label: 'Status' },
    { key: 'gateway', label: 'Gateway / model' },
    { key: 'caller', label: 'Caller' },
    { key: 'created', label: 'Created', align: 'right' },
];

/*
 | Status is one of the five dropdowns now, not a row of tiles above the table.
 | The tiles counted the whole backlog rather than the filtered listing, which
 | made them a dashboard someone had to scroll past to reach the logs — so they
 | are on the dashboard, where the rest of the numbers live.
 */
const form = reactive({
    q: props.filters.search,
    status: props.filters.status,
    kind: props.filters.kind,
    model: props.filters.model,
    gateway: props.filters.gateway,
    caller: props.filters.caller,
});

/*
 | `cursor` is dropped on every filter change: the listing is cursor paginated,
 | and a cursor taken from one result set silently skips rows in another.
 */
function search() {
    router.get(props.urls.logs, { ...form }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

/*
 | Replaces the old <meta http-equiv="refresh">. A partial reload keeps scroll
 | position, filter focus and the typed query, and asks only for what changes.
 |
 | `usePoll` rather than a hand-rolled setInterval: it registers its own
 | visibilitychange listener, so a backlog left open in a background tab drops
 | to one request in ten instead of hitting the controller four times a minute
 | forever, and it will not let a slow reload stack on top of the previous one.
 | It wires its own mount/unmount, so there is no timer to clear here.
 |
 | `pagination` has to be in the list: prev/next are cursors taken from the
 | result set they were rendered with, and leaving them behind while `entries`
 | moves on points Next at a boundary that no longer exists.
 */
usePoll(15000, {
    only: ['entries', 'total', 'pagination'],
});
</script>

<template>
    <Head title="Logs" />

    <Dashboard>
        <header class="flex flex-wrap items-end justify-between gap-3">
            <PageHeading>
                Logs
                <template #description>
                    Every request the gateway has accepted, newest first.
                </template>
            </PageHeading>
        </header>

        <Card class="mt-6">
            <!--
             | Two rows, not one: looking an entry up by its id and narrowing the
             | listing down are different errands. Someone arriving from a
             | caller's own logs has the id in the clipboard and nothing else to
             | say, and on one row that box was the same size as four dropdowns
             | it has nothing to do with.
             -->
            <form
                class="space-y-4"
                @submit.prevent="search"
            >
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-64 flex-1">
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

                    <!-- The form's only submit, so it sends the filters below
                         too — wherever the cursor happens to be when Enter is
                         pressed. -->
                    <Button
                        type="submit"
                        class="w-full sm:w-auto"
                    >
                        Search
                    </Button>
                </div>

                <Divider label="Filters" />

                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-40 flex-1">
                        <Field
                            label="Status"
                            name="status"
                        >
                            <Select
                                v-model="form.status"
                                name="status"
                                :options="statuses"
                                placeholder="All statuses"
                            />
                        </Field>
                    </div>

                    <div class="min-w-40 flex-1">
                        <Field
                            label="Endpoint"
                            name="kind"
                        >
                            <Select
                                v-model="form.kind"
                                name="kind"
                                :options="kinds"
                                placeholder="All endpoints"
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

                    <!-- Clears the search box as well as these dropdowns, so it
                         belongs to the form rather than to this row. -->
                    <Link
                        v-if="!filters.areEmpty"
                        :as="InertiaLink"
                        :href="urls.logs"
                        tone="muted"
                        class="w-full py-2 text-center sm:w-auto sm:py-0 sm:text-left"
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

                <!-- `in progress` is two words and wrapped mid-badge at this
                     column width, which read as a broken pill. -->
                <template #cell-status="{ row }">
                    <StatusBadge
                        class="whitespace-nowrap"
                        :variant="row.status.tone"
                        :label="row.status.label"
                    />
                </template>

                <template #cell-gateway="{ row }">
                    <div class="leading-tight">
                        <div>{{ row.gateway }}</div>
                        <!-- The endpoint only appears when it is not a chat
                             completion — see the row's `kind`. -->
                        <div class="text-xs text-dim">
                            {{ row.model }}<template v-if="row.kind"> &middot; {{ row.kind }}</template>
                        </div>
                    </div>
                </template>

                <template #cell-caller="{ row }">
                    <span
                        :title="row.caller.hash ?? 'queued without a bearer token'"
                        :class="row.caller.named ? '' : 'font-mono text-xs text-dim'"
                    >
                        {{ row.caller.label }}
                    </span>
                </template>

                <!-- Both lines carry the measurement they were rounded from, so
                     a "25m" is never the only number on the page. -->
                <template #cell-created="{ row }">
                    <div class="leading-tight" :title="row.created.exact">
                        <!-- `tabular-nums` so the seconds column lines up down
                             the page and a row is found by scanning rather
                             than by reading. -->
                        <div class="whitespace-nowrap tabular-nums">{{ row.created.stamp }}</div>
                        <div class="text-xs text-dim">{{ row.created.short }}</div>
                    </div>
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

                        <!-- "while visible" is not a hedge: a backgrounded tab
                             is deliberately throttled, so the interval only
                             holds for the tab someone is actually watching. -->
                        <span>Auto-refreshes every 15&thinsp;s while visible</span>
                    </div>
                </template>
            </DataTable>
        </Card>
    </Dashboard>
</template>
