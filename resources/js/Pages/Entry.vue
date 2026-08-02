<script setup>
import { Head, Link as InertiaLink } from '@inertiajs/vue3';
import { useToast } from '@codebar-ag/storybook';
import { computed, ref } from 'vue';
import Dashboard from '../Layouts/Dashboard.vue';

const props = defineProps({
    entry: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const { push } = useToast();

function json(value) {
    return JSON.stringify(value ?? null, null, 2);
}

/*
 | The design system's CopyButton is an icon on its own, which next to a full
 | Download button reads as decoration rather than an action. These panes pair
 | the two, so copying gets the same weight — the toast is what CopyButton
 | would have raised.
 */
async function copy(value) {
    await navigator.clipboard?.writeText(value ?? '');

    push({ message: 'Copied to clipboard', type: 'success' });
}

const hasAnswer = computed(() => Boolean(props.entry.answer));
const hasError = computed(() => Boolean(props.entry.error));
const hasOutput = computed(() => Boolean(props.entry.output));

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

const answerIsJson = computed(() => props.entry.answerFormat === 'json');

/*
 | A model asked for structured output writes its JSON on a single line, which
 | is unreadable in a preview pane. Re-indenting is display only: the download
 | still hands over the model's own bytes, so a `1284.50` in the answer does
 | not quietly become `1284.5` in the file.
 */
const answerPreview = computed(() => {
    if (!answerIsJson.value) {
        return props.entry.answer ?? '';
    }

    try {
        return json(JSON.parse(props.entry.answer));
    } catch {
        return props.entry.answer ?? '';
    }
});

/*
 | Serialised once, not once per render. These feed the preview panes straight
 | from the template, and calling json() there re-stringified the whole request
 | payload and the whole model output on every tab click.
 */
const errorPreview = computed(() => json(props.entry.error?.raw));
const requestPreview = computed(() => json(props.entry.request));
const responsePreview = computed(() => json(props.entry.output));
const usagePreview = computed(() => json(props.entry.usage));
const headerNamesPreview = computed(() => json(props.entry.headerNames));
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

        <!-- Label above value throughout. DescriptionItem lays its pair out in a
             row, so a short value like the model sat beside its label while a
             long one such as the upstream URL wrapped underneath — the same
             tile in two shapes, depending on the entry. -->
        <section class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Card
                v-for="fact in entry.facts"
                :key="fact.label"
                size="sm"
            >
                <div class="text-2xs uppercase tracking-wide text-muted">{{ fact.label }}</div>
                <div
                    class="mt-1 truncate text-sm font-medium"
                    :title="fact.exact ?? undefined"
                >
                    {{ fact.value }}
                </div>
            </Card>

            <Card
                v-for="time in entry.times"
                :key="time.label"
                size="sm"
            >
                <div class="text-2xs uppercase tracking-wide text-muted">{{ time.label }}</div>
                <div class="mt-1 truncate text-sm font-medium">{{ time.exact ?? '—' }}</div>
                <div class="truncate text-xs text-dim">{{ time.human }}</div>
            </Card>
        </section>

        <Card class="mt-6">
            <Tabs
                v-model="active"
                :tabs="tabs"
            >
                <template #error>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <Button
                            variant="secondary"
                            size="sm"
                            @click="copy(entry.error?.details ?? '')"
                        >
                            Copy
                        </Button>
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
                    <!-- Tabs renders every pane and hides the inactive ones with
                         v-show, so without this each CodePreview would build a
                         CodeMirror EditorView off-screen on page load — five
                         editors parsing five documents nobody has opened, and
                         the CodeMirror chunks fetched before the first click. -->
                    <CodePreview
                        v-if="active === 'error'"
                        class="mt-2"
                        language="json"
                        max-height="24rem"
                        :value="errorPreview"
                    />
                </template>

                <template #answer>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <Button
                            variant="secondary"
                            size="sm"
                            @click="copy(answerPreview)"
                        >
                            Copy
                        </Button>
                        <Button
                            as="a"
                            :href="urls.downloads.answer"
                            variant="secondary"
                            size="sm"
                        >
                            Download {{ answerIsJson ? '.json' : '.md' }}
                        </Button>
                    </div>

                    <CodePreview
                        v-if="active === 'answer'"
                        class="mt-3"
                        :language="answerIsJson ? 'json' : 'markdown'"
                        max-height="32rem"
                        :value="answerPreview"
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
                            <Button
                                variant="secondary"
                                size="sm"
                                @click="copy(json(entry.request))"
                            >
                                Copy
                            </Button>
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
                        v-if="active === 'request'"
                        class="mt-3"
                        language="json"
                        max-height="32rem"
                        :value="requestPreview"
                    />
                </template>

                <template #response>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <Button
                            variant="secondary"
                            size="sm"
                            @click="copy(json(entry.output))"
                        >
                            Copy
                        </Button>
                        <Button
                            as="a"
                            :href="urls.downloads.response"
                            variant="secondary"
                            size="sm"
                        >
                            Download .json
                        </Button>
                    </div>

                    <!--
                        A queued entry has no answer yet. Rendering the preview
                        anyway would print a literal `null`, which reads as a
                        result the model returned rather than as one that has
                        not arrived — so it gets the same em dash every other
                        blank on this dashboard uses.
                    -->
                    <CodePreview
                        v-if="active === 'response' && hasOutput"
                        class="mt-3"
                        language="json"
                        max-height="32rem"
                        :value="responsePreview"
                    />
                    <p
                        v-else-if="active === 'response'"
                        class="mt-3 text-dim"
                    >
                        &mdash;
                    </p>
                </template>

                <template #meta>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div>
                            <h3 class="text-2xs uppercase tracking-wide text-muted">Usage</h3>
                            <CodePreview
                                v-if="active === 'meta'"
                                class="mt-2"
                                language="json"
                                max-height="20rem"
                                :value="usagePreview"
                            />
                        </div>

                        <div>
                            <h3 class="text-2xs uppercase tracking-wide text-muted">Forwarded header names</h3>
                            <CodePreview
                                v-if="active === 'meta'"
                                class="mt-2"
                                language="json"
                                max-height="20rem"
                                :value="headerNamesPreview"
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
