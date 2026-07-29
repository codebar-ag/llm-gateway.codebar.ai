<script setup>
/*
 | The dashboard frame: nav, tunnel indicator and flash banners.
 |
 | Inertia's <Link> is aliased, because the design system registers a <Link>
 | atom of its own globally. The atom takes an `as` prop precisely so it can
 | render through a router's link component — that is what pairs them up.
 */
import { Link as InertiaLink, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const nav = computed(() => page.props.nav ?? {});
const tunnel = computed(() => page.props.tunnel ?? null);
const flash = computed(() => page.props.flash ?? {});
const user = computed(() => page.props.auth?.user ?? null);
const current = computed(() => page.url.split('?')[0]);

function isActive(href) {
    const path = new URL(href, 'http://x').pathname;

    return path === '/' ? current.value === '/' : current.value.startsWith(path);
}

// A POST, so logging out cannot be triggered by a link someone else planted.
function logout() {
    router.post(nav.value.logout);
}
</script>

<template>
    <AppShell>
        <template #navbar>
            <Navbar :menu-button="false">
                <nav class="flex items-center gap-4">
                    <Link
                        :as="InertiaLink"
                        :href="nav.backlog"
                        :tone="isActive(nav.backlog) ? 'default' : 'muted'"
                    >
                        Backlog
                    </Link>
                    <Link
                        :as="InertiaLink"
                        :href="nav.callers"
                        :tone="isActive(nav.callers) ? 'default' : 'muted'"
                    >
                        Callers
                    </Link>
                    <Link
                        :as="InertiaLink"
                        :href="nav.gateways"
                        :tone="isActive(nav.gateways) ? 'default' : 'muted'"
                    >
                        Gateways
                    </Link>
                </nav>

                <template #actions>
                    <Badge
                        v-if="tunnel"
                        :variant="tunnel.tone"
                        size="sm"
                    >
                        {{ tunnel.label }}
                    </Badge>

                    <span
                        v-if="user"
                        class="hidden text-xs text-dim sm:inline"
                    >
                        {{ user.name }}
                    </span>

                    <Button
                        v-if="user"
                        variant="ghost"
                        size="sm"
                        @click="logout"
                    >
                        Sign out
                    </Button>
                </template>
            </Navbar>
        </template>

        <Alert
            v-if="flash.status"
            variant="success"
            class="mb-4"
        >
            {{ flash.status }}
        </Alert>

        <Alert
            v-if="flash.error"
            variant="danger"
            class="mb-4"
        >
            {{ flash.error }}
        </Alert>

        <slot />
    </AppShell>

    <Toaster />
</template>
