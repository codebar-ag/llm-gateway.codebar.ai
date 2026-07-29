<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    urls: { type: Object, required: true },
});

const form = useForm({
    email: '',
    password: '',
});

function submit() {
    // The password never survives a failed attempt: without this it stays in
    // the form state and is re-serialised into the page on the way back.
    form.post(props.urls.submit, {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Sign in" />

    <AuthLayout
        title="Sign in"
        description="The dashboard shows every prompt and answer this gateway has handled."
    >
        <form
            class="space-y-4"
            @submit.prevent="submit"
        >
            <Field
                label="Email"
                name="email"
                required
                :error="form.errors.email"
            >
                <Input
                    v-model="form.email"
                    name="email"
                    type="email"
                    autocomplete="username"
                    :invalid="Boolean(form.errors.email)"
                    autofocus
                />
            </Field>

            <Field
                label="Password"
                name="password"
                required
                :error="form.errors.password"
            >
                <!-- Sets its own autocomplete; its root is a wrapper div, so
                     passing one here would land on the div and do nothing. -->
                <PasswordInput
                    v-model="form.password"
                    name="password"
                    :invalid="Boolean(form.errors.password)"
                />
            </Field>

            <Button
                type="submit"
                variant="primary"
                :loading="form.processing"
            >
                Sign in
            </Button>
        </form>

        <p class="mt-6 text-xs text-dim">
            There is no password reset. Accounts are created on the server with
            <code>php artisan user:register</code>.
        </p>
    </AuthLayout>
</template>
