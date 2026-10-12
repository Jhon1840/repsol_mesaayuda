<script setup lang="ts">
import {
    computed,
    ref,
    onMounted,
    onBeforeUnmount,
    watch,
    nextTick,
} from "vue";
import { Link, router, usePage, usePoll } from "@inertiajs/vue3";
import Icon from "../components/Icon.vue";
import { initials, type Person } from "../types";
defineProps<{ title: string; section?: string }>();
const page = usePage();
const user = computed(() => (page.props.auth as { user: Person }).user);
const unread = computed(() => Number(page.props.unreadCount || 0));
const flash = computed(
    () => (page.props.flash as { success?: string })?.success,
);
const open = ref(false);
const mobile = ref(false);
let media: MediaQueryList;
const syncMobile = () => {
    mobile.value = media.matches;
};
watch(open, async (value) => {
    if (!mobile.value) return;
    await nextTick();
    const target = value ? ".sidebar a" : ".mobile-menu";
    document.querySelector<HTMLElement>(target)?.focus();
});
onMounted(() => {
    media = window.matchMedia("(max-width: 760px)");
    syncMobile();
    media.addEventListener("change", syncMobile);
});
onBeforeUnmount(() => media?.removeEventListener("change", syncMobile));
const support = computed(() => user.value.role === "support");
usePoll(60000, { only: ["unreadCount"] }, { keepAlive: false });
const active = (path: string) => page.url.split("?")[0] === path;
</script>
<template>
    <a href="#main-content" class="skip-link">Saltar al contenido</a>
    <div class="app-shell">
        <button
            v-if="open"
            class="sidebar-backdrop"
            aria-label="Cerrar navegación"
            @click="open = false"
        ></button>
        <aside
            :class="['sidebar', { open }]"
            :inert="mobile && !open"
            @keydown.esc="open = false"
        >
            <Link href="/dashboard" class="brand"
                ><span class="brand-mark"
                    ><Icon name="Headset" :size="23" /></span
                ><span
                    >Mesa<span class="brand-light"> de ayuda</span
                    ><small>WORKSPACE INTERNO</small></span
                ></Link
            >
            <div class="workspace">
                <span class="workspace-avatar">TI</span>
                <div>
                    <strong>Equipo de tecnología</strong
                    ><small>Espacio de trabajo</small>
                </div>
            </div>
            <span class="nav-label">PRINCIPAL</span>
            <nav aria-label="Navegación principal">
                <Link
                    v-if="support"
                    href="/support/tickets"
                    :aria-current="
                        active('/support/tickets') ? 'page' : undefined
                    "
                    :class="[
                        'nav-item',
                        { active: active('/support/tickets') },
                    ]"
                    ><Icon name="LayoutDashboard" />Mesa de soporte<span
                        class="nav-indicator"
                    ></span
                ></Link>
                <Link
                    href="/mis-tickets"
                    :aria-current="active('/mis-tickets') ? 'page' : undefined"
                    :class="['nav-item', { active: active('/mis-tickets') }]"
                    ><Icon name="Ticket" />Mis solicitudes</Link
                >
                <Link
                    href="/notifications"
                    :aria-current="
                        active('/notifications') ? 'page' : undefined
                    "
                    :class="['nav-item', { active: active('/notifications') }]"
                    ><Icon name="Bell" />Notificaciones<span
                        v-if="unread"
                        class="nav-count"
                        >{{ unread }}</span
                    ></Link
                >
            </nav>
            <template v-if="support"
                ><span class="nav-label">ADMINISTRACIÓN</span>
                <nav aria-label="Administración">
                    <Link
                        href="/support/users"
                        :aria-current="
                            active('/support/users') ? 'page' : undefined
                        "
                        :class="[
                            'nav-item',
                            { active: active('/support/users') },
                        ]"
                        ><Icon name="Users" />Usuarios</Link
                    ><Link
                        href="/support/categories"
                        :aria-current="
                            active('/support/categories') ? 'page' : undefined
                        "
                        :class="[
                            'nav-item',
                            { active: active('/support/categories') },
                        ]"
                        ><Icon name="Tags" />Categorías</Link
                    >
                </nav></template
            >
            <div class="sidebar-bottom">
                <div class="support-note">
                    <span class="support-note-icon"
                        ><Icon name="LifeBuoy" :size="22" /></span
                    ><strong>Estamos para ayudarte</strong>
                    <p>
                        Una solicitud, una solución.<br />Todo en un mismo
                        lugar.
                    </p>
                    <Link href="/tickets/create"
                        >Crear una solicitud <Icon name="ArrowRight" :size="15"
                    /></Link>
                </div>
                <div class="sidebar-user">
                    <span class="avatar">{{ initials(user.name) }}</span>
                    <div>
                        <strong>{{ user.name }}</strong
                        ><small>{{
                            support ? "Equipo de soporte" : "Usuario"
                        }}</small>
                    </div>
                    <button
                        class="icon-button"
                        aria-label="Cerrar sesión"
                        @click="router.post('/logout')"
                    >
                        <Icon name="LogOut" />
                    </button>
                </div>
            </div>
        </aside>
        <div class="app-main" :inert="mobile && open">
            <header class="topbar">
                <div class="breadcrumb">
                    <button
                        class="icon-button mobile-menu"
                        aria-label="Abrir navegación"
                        :aria-expanded="open"
                        @click="open = !open"
                    >
                        <Icon name="Menu" /></button
                    ><span>{{ section || "Espacio de trabajo" }}</span
                    ><Icon name="ChevronRight" :size="14" /><strong>{{
                        title
                    }}</strong>
                </div>
                <div class="topbar-actions">
                    <span class="workspace-status"
                        ><span></span>Espacio interno</span
                    ><Link
                        href="/notifications"
                        class="icon-button notification-button"
                        aria-label="Ver notificaciones"
                        ><Icon name="Bell" :size="20" /><span
                            v-if="unread"
                            class="notification-dot"
                        ></span></Link
                    ><span class="avatar small">{{ initials(user.name) }}</span>
                </div>
            </header>
            <main id="main-content" class="main-content">
                <div v-if="flash" class="toast" role="status">
                    <Icon name="CircleCheck" />{{ flash }}
                </div>
                <slot />
            </main>
            <footer class="app-footer">
                <span>Mesa de ayuda · Equipo de tecnología</span
                ><span>Una mejor atención, juntos.</span>
            </footer>
        </div>
    </div>
</template>
