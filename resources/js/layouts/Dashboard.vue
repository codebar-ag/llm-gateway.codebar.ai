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
                <!-- overflow-x-auto + shrink-0: on a narrow viewport this row has
                     less width than four links need. Without it the last link
                     (Gateways) is silently squeezed out of view with no way to
                     reach it, instead of staying reachable via a scroll. -->
                <nav class="flex items-center gap-4 overflow-x-auto">
                    <Link
                        :as="InertiaLink"
                        :href="nav.dashboard"
                        :tone="isActive(nav.dashboard) ? 'default' : 'muted'"
                        class="shrink-0"
                    >
                        Dashboard
                    </Link>
                    <Link
                        :as="InertiaLink"
                        :href="nav.logs"
                        :tone="isActive(nav.logs) ? 'default' : 'muted'"
                        class="shrink-0"
                    >
                        Logs
                    </Link>
                    <Link
                        :as="InertiaLink"
                        :href="nav.callers"
                        :tone="isActive(nav.callers) ? 'default' : 'muted'"
                        class="shrink-0"
                    >
                        Callers
                    </Link>
                    <Link
                        :as="InertiaLink"
                        :href="nav.gateways"
                        :tone="isActive(nav.gateways) ? 'default' : 'muted'"
                        class="shrink-0"
                    >
                        Gateways
                    </Link>
                </nav>

                <template #actions>
                    <!-- Wrapped rather than hidden via a class on Badge itself:
                         Badge's own root already sets `inline-flex`, and fighting
                         that with a `display` utility passed in as a prop is a
                         CSS-cascade-order gamble. A wrapper sidesteps it. -->
                    <span
                        v-if="tunnel"
                        class="hidden sm:inline-flex"
                    >
                        <Badge
                            :variant="tunnel.tone"
                            size="sm"
                        >
                            {{ tunnel.label }}
                        </Badge>
                    </span>

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
