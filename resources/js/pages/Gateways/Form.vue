<script setup>
import { Head, Link as InertiaLink, useForm } from '@inertiajs/vue3';
import Dashboard from '../../layouts/Dashboard.vue';

const props = defineProps({
    gateway: { type: Object, required: true },
    protocols: { type: Array, required: true },
    maxTimeout: { type: Number, required: true },
    editing: { type: Boolean, required: true },
    urls: { type: Object, required: true },
});

const form = useForm({
    name: props.gateway.name ?? '',
    protocol: props.gateway.protocol ?? props.protocols[0]?.value,
    base_url: props.gateway.baseUrl ?? '',
    path: props.gateway.path ?? '',
    default_model: props.gateway.defaultModel ?? '',
    timeout: props.gateway.timeout,
    connect_timeout: props.gateway.connectTimeout,
    description: props.gateway.description ?? '',
    is_active: props.gateway.isActive,
    is_default: props.gateway.isDefault,
});

function submit() {
    if (props.editing) {
        form.put(props.urls.submit);

        return;
    }

    form.post(props.urls.submit);
}
</script>

<template>
    <Head :title="editing ? 'Edit gateway' : 'New gateway'" />

    <Dashboard>
        <header class="flex flex-wrap items-end justify-between gap-3">
            <PageHeading>{{ editing ? 'Edit gateway' : 'New gateway' }}</PageHeading>

            <Link
                :as="InertiaLink"
                :href="urls.index"
                tone="muted"
            >
                Back to gateways
            </Link>
        </header>

        <Alert
            v-if="Object.keys(form.errors).length"
            variant="danger"
            title="That did not save"
            class="mt-4"
        >
            <ul class="list-inside list-disc">
                <li
                    v-for="(message, field) in form.errors"
                    :key="field"
                >
                    {{ message }}
                </li>
            </ul>
        </Alert>

        <Card class="mt-6">
            <form @submit.prevent="submit">
                <FormGrid :cols="3">
                    <Field
                        label="Name"
                        name="name"
                        required
                        hint="Operator-facing only. Callers address a gateway by its id, never by its name."
                        :error="form.errors.name"
                    >
                        <Input
                            v-model="form.name"
                            name="name"
                        />
                    </Field>

                    <Field
                        label="Protocol"
                        name="protocol"
                        hint="OpenAI-compatible upstreams get the caller's body untouched. Anthropic and Ollama are translated on the way out and back, and tool calls are not translated."
                        :error="form.errors.protocol"
                    >
                        <Select
                            v-model="form.protocol"
                            name="protocol"
                            :options="protocols"
                        />
                    </Field>

                    <Field
                        class="sm:col-span-2"
                        label="Base URL"
                        name="base_url"
                        required
                        hint="Where this gateway forwards to, without the protocol's path."
                        :error="form.errors.base_url"
                    >
                        <Input
                            v-model="form.base_url"
                            name="base_url"
                            placeholder="https://llm.codebar.net"
                        />
                    </Field>

                    <Field
                        label="Path override"
                        name="path"
                        hint="Leave empty to use the protocol's own path. Set it for upstreams behind a prefix."
                        :error="form.errors.path"
                    >
                        <Input
                            v-model="form.path"
                            name="path"
                            :placeholder="gateway.defaultPath"
                        />
                    </Field>

                    <Field
                        label="Default model"
                        name="default_model"
                        hint="Used only when the caller's request names no model. A request that names one is never overridden."
                        :error="form.errors.default_model"
                    >
                        <Input
                            v-model="form.default_model"
                            name="default_model"
                        />
                    </Field>

                    <Field
                        label="Timeout (s)"
                        name="timeout"
                        required
                        :hint="`At most ${maxTimeout}s — the ceiling the queue's retry window is built on.`"
                        :error="form.errors.timeout"
                    >
                        <InputNumber
                            v-model="form.timeout"
                            name="timeout"
                            :min="1"
                            :max="maxTimeout"
                        />
                    </Field>

                    <Field
                        label="Connect timeout (s)"
                        name="connect_timeout"
                        required
                        hint="How long to wait for the upstream to answer at all."
                        :error="form.errors.connect_timeout"
                    >
                        <InputNumber
                            v-model="form.connect_timeout"
                            name="connect_timeout"
                            :min="1"
                            :max="120"
                        />
                    </Field>

                    <Field
                        class="sm:col-span-2 lg:col-span-3"
                        label="Description"
                        name="description"
                        :error="form.errors.description"
                    >
                        <Textarea
                            v-model="form.description"
                            name="description"
                            :rows="2"
                        />
                    </Field>
                </FormGrid>

                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <Checkbox
                        v-model="form.is_active"
                        name="is_active"
                        description="Deactivating stops new work reaching it without touching what is already queued."
                    >
                        Accepts new requests
                    </Checkbox>

                    <Checkbox
                        v-model="form.is_default"
                        name="is_default"
                        :disabled="gateway.isDefault"
                        :description="gateway.isDefault
                            ? 'Already takes every request that arrives without an id in front of /v1. To move the flag, promote a different gateway — there is always exactly one default.'
                            : 'Takes every request that arrives without an id in front of /v1. Ticking this clears the flag from whichever gateway holds it now.'"
                    >
                        Default gateway
                    </Checkbox>
                </div>

                <FormActions class="mt-6">
                    <Button
                        type="submit"
                        :loading="form.processing"
                    >
                        {{ editing ? 'Save changes' : 'Create gateway' }}
                    </Button>

                    <Link
                        :as="InertiaLink"
                        :href="urls.index"
                        tone="muted"
                    >
                        Cancel
                    </Link>
                </FormActions>
            </form>
        </Card>

        <p
            v-if="editing"
            class="mt-4 flex flex-wrap items-center gap-2 text-xs text-muted"
        >
            Callers reach this gateway at
            <code class="font-mono">{{ gateway.callerBaseUrl }}</code>
            <CopyButton :value="gateway.callerBaseUrl" />
        </p>
    </Dashboard>
</template>
