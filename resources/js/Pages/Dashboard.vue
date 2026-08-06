<script setup>
import { Head, Link as InertiaLink, usePoll } from '@inertiajs/vue3';
import { computed } from 'vue';
/*
 | Aliased, because this page and the frame it renders into share a name: the
 | page is the dashboard, and `Layouts/Dashboard.vue` is the nav-and-flash shell
 | every page sits in.
 */
import DashboardLayout from '../Layouts/Dashboard.vue';

const props = defineProps({
    range: { type: Object, required: true },
    statuses: { type: Array, required: true },
    oldestQueued: { type: String, default: null },
    totals: { type: Object, required: true },
    perModel: { type: Array, required: true },
    perCaller: { type: Array, required: true },
    volume: { type: Array, required: true },
    summary: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const modelColumns = [
    { key: 'model', label: 'Model' },
    { key: 'requests', label: 'Requests', align: 'right' },
    { key: 'inputTokens', label: 'Input', align: 'right' },
    { key: 'outputTokens', label: 'Output', align: 'right' },
    { key: 'totalTokens', label: 'Tokens', align: 'right' },
    { key: 'avgDuration', label: 'Avg time', align: 'right' },
];

const callerColumns = [
    { key: 'caller', label: 'Caller' },
    { key: 'requests', label: 'Requests', align: 'right' },
    { key: 'totalTokens', label: 'Tokens', align: 'right' },
];

/*
 | The bars are drawn from the tallest bucket rather than from a fixed ceiling:
 | this gateway's busy day is whatever it happens to be, and a scale picked in
 | advance either flattens every bar or clips the one that matters.
 */
const peak = computed(() => Math.max(1, ...props.volume.map((point) => point.requests)));

function barHeight(point) {
    // A floor of 2%, so a bucket with traffic in it is never an invisible bar
    // that reads the same as an hour with none.
    return `${Math.max(2, Math.round((point.requests / peak.value) * 100))}%`;
}

/** Thousands separators, so a six-figure token count can be read at a glance. */
function count(value) {
    return value === null || value === undefined ? '—' : value.toLocaleString('de-CH');
}

/** Seconds are what the backend measures; minutes are what a slow call reads as. */
function seconds(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    return value < 90 ? `${value}s` : `${Math.round(value / 60)}m`;
}

/*
 | Only the live half refreshes. The usage numbers behind them are a GROUP BY
 | over the window and they do not move meaningfully in fifteen seconds, so
 | asking for them four times a minute would be the page's whole cost.
 */
usePoll(15000, {
    only: ['statuses', 'oldestQueued'],
});
</script>

<template>
    <Head title="Dashboard" />

    <DashboardLayout>
        <header class="flex flex-wrap items-end justify-between gap-3">
            <PageHeading>
                LLM Gateway
                <template #description>
                    {{ summary.workers }} {{ summary.workers === 1 ? 'worker' : 'workers' }}
                    &rarr; {{ summary.endpoint ?? 'no default gateway' }}
                </template>
            </PageHeading>
        </header>

        <!-- Unfiltered by the range, and first on the page: what is in the queue
             right now is the only thing here anyone has to act on. -->
        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
            <InertiaLink
                v-for="status in statuses"
                :key="status.value"
                :href="status.href"
            >
                <Card hoverable>
                    <Metric
                        :label="status.label"
                        :value="count(status.count)"
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

        <div class="mt-8 flex flex-wrap items-end justify-between gap-3">
            <PageHeading>
                Usage
                <template #description>
                    {{ range.label }}
                </template>
            </PageHeading>

            <!-- Links, not a form: the window is in the URL, so the dashboard
                 someone is looking at can be sent on and show the same numbers. -->
            <ButtonGroup>
                <Button
                    v-for="option in range.options"
                    :key="option.value"
                    :as="InertiaLink"
                    :href="option.href"
                    :variant="option.active ? 'primary' : 'secondary'"
                    size="sm"
                >
                    {{ option.label }}
                </Button>
            </ButtonGroup>
        </div>

        <MetricGrid
            class="mt-4"
            :columns="4"
        >
            <Card>
                <Metric
                    label="Requests"
                    :value="count(totals.requests)"
                />
            </Card>
            <Card>
                <Metric
                    label="Failed"
                    :value="totals.failureRate === null ? count(totals.failed) : `${count(totals.failed)} · ${totals.failureRate}%`"
                />
            </Card>
            <Card>
                <Metric
                    label="Tokens"
                    :value="count(totals.totalTokens)"
                />
            </Card>
            <Card>
                <Metric
                    label="In / out"
                    :value="`${count(totals.inputTokens)} / ${count(totals.outputTokens)}`"
                />
            </Card>
            <Card>
                <Metric
                    label="Avg answer"
                    :value="seconds(totals.avgDuration)"
                />
            </Card>
            <Card>
                <Metric
                    label="Slowest answer"
                    :value="seconds(totals.maxDuration)"
                />
            </Card>
            <Card>
                <Metric
                    label="Avg queue wait"
                    :value="seconds(totals.avgWait)"
                />
            </Card>
            <Card>
                <Metric
                    label="Completed"
                    :value="count(totals.completed)"
                />
            </Card>
        </MetricGrid>

        <Card class="mt-6">
            <h2 class="text-sm font-medium">
                Volume
            </h2>
            <p class="mt-1 text-xs text-dim">
                Requests per {{ range.value === '24h' ? 'hour' : 'day' }}, oldest first.
            </p>

            <EmptyState
                v-if="volume.length === 0"
                title="No traffic"
                description="Nothing was queued in this window."
                class="mt-4"
            />

            <!-- Drawn in CSS rather than with a charting library: it is one
                 series of one number, and the design system's <Chart> wants
                 apexcharts, which this app does not ship. -->
            <div
                v-else
                class="mt-4 flex h-40 items-end gap-px overflow-x-auto"
            >
                <div
                    v-for="point in volume"
                    :key="point.bucket"
                    class="flex h-full min-w-1 flex-1 items-end"
                    :title="`${point.bucket} — ${count(point.requests)} requests, ${count(point.totalTokens)} tokens`"
                >
                    <div
                        class="w-full rounded-t-sm bg-accent"
                        :style="{ height: barHeight(point) }"
                    />
                </div>
            </div>

            <div
                v-if="volume.length > 0"
                class="mt-2 flex justify-between text-xs text-dim"
            >
                <span>{{ volume[0].bucket }}</span>
                <span>{{ volume[volume.length - 1].bucket }}</span>
            </div>
        </Card>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <Card :padded="false">
                <div class="p-4">
                    <h2 class="text-sm font-medium">
                        Per model
                    </h2>
                </div>

                <DataTable
                    :columns="modelColumns"
                    :rows="perModel"
                    row-key="model"
                >
                    <template #cell-requests="{ row }">
                        {{ count(row.requests) }}
                    </template>
                    <template #cell-inputTokens="{ row }">
                        {{ count(row.inputTokens) }}
                    </template>
                    <template #cell-outputTokens="{ row }">
                        {{ count(row.outputTokens) }}
                    </template>
                    <template #cell-totalTokens="{ row }">
                        {{ count(row.totalTokens) }}
                    </template>
                    <template #cell-avgDuration="{ row }">
                        {{ seconds(row.avgDuration) }}
                    </template>
                </DataTable>
            </Card>

            <Card :padded="false">
                <div class="p-4">
                    <h2 class="text-sm font-medium">
                        Per caller
                    </h2>
                </div>

                <DataTable
                    :columns="callerColumns"
                    :rows="perCaller"
                    row-key="caller"
                >
                    <!-- An unnamed key keeps the monospace treatment it has on
                         the logs page, so a hash never reads as a name. -->
                    <template #cell-caller="{ row }">
                        <span :class="row.named ? '' : 'font-mono text-xs text-dim'">
                            {{ row.caller }}
                        </span>
                    </template>
                    <template #cell-requests="{ row }">
                        {{ count(row.requests) }}
                    </template>
                    <template #cell-totalTokens="{ row }">
                        {{ count(row.totalTokens) }}
                    </template>
                </DataTable>
            </Card>
        </div>

        <p class="mt-4 text-xs text-dim">
            Every number above is read from the log entries themselves, and counts the tokens
            the upstream reported. An entry it answered without a usage block counts as a
            request and as no tokens.
        </p>
    </DashboardLayout>
</template>
