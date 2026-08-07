<script setup>
import { Head, Link as InertiaLink, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Dashboard from '../layouts/Dashboard.vue';

const props = defineProps({
    callers: { type: Array, required: true },
    urls: { type: Object, required: true },
});

const columns = [
    { key: 'caller', label: 'Caller' },
    { key: 'name', label: 'Name' },
    { key: 'entries', label: 'Entries', align: 'right' },
    { key: 'firstSeen', label: 'First seen', align: 'right' },
    { key: 'lastSeen', label: 'Last seen', align: 'right' },
];

// Which row is being renamed, keyed by hash — one at a time, so the page does
// not turn into a grid of open inputs.
const editing = ref(null);
const name = ref('');

const form = useForm({ api_key_hash: '', name: '' });

function edit(caller) {
    editing.value = caller.hash;
    name.value = caller.name;
}

function save(caller) {
    form.api_key_hash = caller.hash;
    form.name = name.value;

    form.post(props.urls.store, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
}

function forget(caller) {
    router.delete(caller.destroyUrl, { preserveScroll: true });
}
</script>

<template>
    <Head title="Callers" />

    <Dashboard>
        <header>
            <PageHeading>Callers</PageHeading>
            <p class="mt-1 max-w-3xl text-sm text-dim">
                Every key the backlog has seen, identified by the SHA-256 of the bearer token
                it queued with. Naming one is a reading aid only — it grants nothing, revokes
                nothing, and the key itself is never stored.
            </p>
        </header>

        <Card
            class="mt-6"
            :padded="false"
        >
            <DataTable
                :columns="columns"
                :rows="callers"
                row-key="hash"
            >
                <template #cell-caller="{ row }">
                    <Badge
                        v-if="row.isAnonymous"
                        variant="warning"
                        size="sm"
                    >
                        no key
                    </Badge>
                    <code
                        v-else
                        class="font-mono text-xs"
                        :title="row.hash"
                    >{{ row.shortHash }}…</code>
                </template>

                <template #cell-name="{ row }">
                    <form
                        v-if="editing === row.hash"
                        class="flex items-center gap-2"
                        @submit.prevent="save(row)"
                    >
                        <Input
                            v-model="name"
                            name="name"
                            placeholder="Nightly batch"
                        />
                        <Button
                            type="submit"
                            size="sm"
                            :loading="form.processing"
                        >
                            Save
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="editing = null"
                        >
                            Cancel
                        </Button>
                    </form>

                    <span
                        v-else-if="row.name"
                        class="text-sm"
                    >{{ row.name }}</span>

                    <span
                        v-else
                        class="text-xs text-dim"
                    >unnamed</span>
                </template>

                <template #cell-firstSeen="{ row }">
                    <span :title="row.firstSeen.exact">{{ row.firstSeen.human }}</span>
                </template>

                <template #cell-lastSeen="{ row }">
                    <span :title="row.lastSeen.exact">{{ row.lastSeen.human }}</span>
                </template>

                <template #row-actions="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <Button
                            :as="InertiaLink"
                            :href="row.entriesUrl"
                            variant="ghost"
                            size="sm"
                        >
                            Entries
                        </Button>
                        <!--
                            Labelled as well as captioned. "Name" and "Rename"
                            are the same control in two states, so a screen
                            reader hearing the row out gets the caller it acts
                            on rather than a bare verb — and a test can address
                            it by attribute instead of by matching its text.
                        -->
                        <Button
                            variant="ghost"
                            size="sm"
                            :aria-label="row.name ? 'Rename caller' : 'Name caller'"
                            @click="edit(row)"
                        >
                            {{ row.name ? 'Rename' : 'Name' }}
                        </Button>
                        <Button
                            v-if="row.name"
                            variant="ghost"
                            size="sm"
                            @click="forget(row)"
                        >
                            Forget
                        </Button>
                    </div>
                </template>

                <template #empty>
                    <EmptyState
                        title="No callers yet"
                        description="A caller appears here once it has queued something."
                    />
                </template>
            </DataTable>
        </Card>

        <p
            v-if="form.errors.name"
            class="mt-3 text-sm text-danger"
        >
            {{ form.errors.name }}
        </p>
    </Dashboard>
</template>
