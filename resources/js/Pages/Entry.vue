<script setup>
import { Head, Link as InertiaLink } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Dashboard from '../Layouts/Dashboard.vue';

const props = defineProps({
    entry: { type: Object, required: true },
    urls: { type: Object, required: true },
});

function json(value) {
    return JSON.stringify(value ?? null, null, 2);
}

const hasAnswer = computed(() => Boolean(props.entry.answer));
const hasError = computed(() => Boolean(props.entry.error));

/*
 | Only the panes that have something in them. A tab per possible field would
 | leave a queued entry showing three empty ones, and "Error" on an entry that
 | did not fail reads as though it did.
 */
const tabs = computed(() => [
    ...(hasError.value ? [{ key: 'error', label: 'Error' }] : []),
    ...(hasAnswer.value ? [{ key: 'answer', label: 'Answer' }] : []),
    { key: 'request', label: 'Request' },
    { key: 'response', label: 'Response' },
    { key: 'meta', label: 'Meta' },
]);

// What the operator opened this page to look at: the failure if it failed, the
// answer if it worked, and otherwise the request — all a queued entry has.
const active = ref(hasError.value ? 'error' : hasAnswer.value ? 'answer' : 'request');
</script>

<template>
    <Head :title="entry.id" />

    <Dashboard>
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <Link
                    :as="InertiaLink"
                    :href="urls.backlog"
                    tone="muted"
                >
                    &larr; Back to backlog
                </Link>
                <PageHeading class="mt-1 font-mono">{{ entry.id }}</PageHeading>
            </div>

            <div class="flex items-center gap-2">
                <CopyButton
                    :value="entry.id"
                    label="Copy id"
                />
                <StatusBadge
                    :variant="entry.status.tone"
                    :label="entry.status.label"
                />
            </div>
        </header>

        <section class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Card
                v-for="fact in entry.facts"
                :key="fact.label"
                size="sm"
            >
                <DescriptionItem :label="fact.label">
                    <span :title="fact.exact ?? undefined">{{ fact.value }}</span>
                </DescriptionItem>
            </Card>

            <Card
                v-for="time in entry.times"
                :key="time.label"
                size="sm"
            >
                <div class="text-2xs uppercase tracking-wide text-muted">{{ time.label }}</div>
                <div class="mt-1 text-sm font-medium">{{ time.exact ?? '—' }}</div>
                <div class="text-xs text-dim">{{ time.human }}</div>
            </Card>
        </section>

        <Card class="mt-6">
            <Tabs
                v-model="active"
                :tabs="tabs"
            >
                <template #error>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <CopyButton :value="entry.error?.details ?? ''" />
                        <Button
                            as="a"
                            :href="urls.downloads.error"
                            variant="secondary"
                            size="sm"
                        >
                            Download .json
                        </Button>
                    </div>

                    <Alert
                        variant="danger"
                        title="Error"
                        class="mt-3"
                    >
                        <pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words font-mono text-xs">{{ entry.error?.details }}</pre>
                    </Alert>

                    <h3 class="mt-4 text-2xs uppercase tracking-wide text-muted">Raw error</h3>
                    <CodePreview
                        class="mt-2"
                        language="json"
                        max-height="24rem"
                        :value="json(entry.error?.raw)"
                    />
                </template>

                <template #answer>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <CopyButton :value="entry.answer ?? ''" />
                        <Button
                            as="a"
                            :href="urls.downloads.answer"
                            variant="secondary"
                            size="sm"
                        >
                            Download .md
                        </Button>
                    </div>

                    <CodePreview
                        class="mt-3"
                        language="markdown"
                        max-height="32rem"
                        :value="entry.answer ?? ''"
                    />
                </template>

                <template #request>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p
                            v-if="entry.protocolNote"
                            class="text-xs text-dim"
                        >
                            {{ entry.protocolNote }}
                        </p>
                        <span v-else />

                        <div class="flex items-center gap-2">
                            <CopyButton :value="json(entry.request)" />
                            <Button
                                as="a"
                                :href="urls.downloads.request"
                                variant="secondary"
                                size="sm"
                            >
                                Download .json
                            </Button>
                        </div>
                    </div>

                    <CodePreview
                        class="mt-3"
                        language="json"
                        max-height="32rem"
                        :value="json(entry.request)"
                    />
                </template>

                <template #response>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <CopyButton :value="json(entry.output)" />
                        <Button
                            as="a"
                            :href="urls.downloads.response"
                            variant="secondary"
                            size="sm"
                        >
                            Download .json
                        </Button>
                    </div>

                    <CodePreview
                        class="mt-3"
                        language="json"
                        max-height="32rem"
                        :value="json(entry.output)"
                    />
                </template>

                <template #meta>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div>
                            <h3 class="text-2xs uppercase tracking-wide text-muted">Usage</h3>
                            <CodePreview
                                class="mt-2"
                                language="json"
                                max-height="20rem"
                                :value="json(entry.usage)"
                            />
                        </div>

                        <div>
                            <h3 class="text-2xs uppercase tracking-wide text-muted">Forwarded header names</h3>
                            <CodePreview
                                class="mt-2"
                                language="json"
                                max-height="20rem"
                                :value="json(entry.headerNames)"
                            />
                            <p class="mt-1 text-xs text-dim">
                                Values are never shown — they carry the caller's credentials.
                            </p>
                        </div>
                    </div>

                    <h3 class="mt-6 text-2xs uppercase tracking-wide text-muted">API key hash</h3>
                    <div class="mt-2 flex items-center gap-2">
                        <code class="min-w-0 break-all rounded-control border border-line bg-surface px-3 py-2 font-mono text-xs">
                            {{ entry.apiKeyHash ?? '—' }}
                        </code>
                        <CopyButton
                            v-if="entry.apiKeyHash"
                            :value="entry.apiKeyHash"
                        />
                    </div>
                </template>
            </Tabs>
        </Card>
    </Dashboard>
</template>
