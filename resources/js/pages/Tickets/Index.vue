<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import AppLayout from "../../layouts/AppLayout.vue";
import Icon from "../../components/Icon.vue";
import Badge from "../../components/Badge.vue";
import FormErrors from "../../components/FormErrors.vue";
import ResolutionModal from "../../components/ResolutionModal.vue";
import Pagination from "../../components/Pagination.vue";
import {
    statuses,
    priorities,
    initials,
    date,
    type Ticket,
    type Category,
    type Person,
    type Status,
    type Pagination as Paginated,
} from "../../types";
const props = defineProps<{
    support: boolean;
    view: string;
    filters: Record<string, string>;
    columns: Record<Status, { items: Ticket[]; total: number }>;
    tickets: Paginated<Ticket> | null;
    categories: Category[];
    technicians: Person[];
    requesters: Person[];
    metrics: {
        pending: number;
        in_progress: number;
        unassigned: number;
        urgent: number;
        resolved_today: number;
        avg_minutes: number | null;
    };
}>();
const page = usePage();
const user = computed(() => (page.props.auth as { user: Person }).user);
const filters = useForm({
    q: props.filters.q || "",
    status: props.filters.status || "",
    priority: props.filters.priority || "",
    category_id: props.filters.category_id || "",
    assigned_to: props.filters.assigned_to || "",
    user_id: props.filters.user_id || "",
    from: props.filters.from || "",
    to: props.filters.to || "",
    unassigned: props.filters.unassigned || "",
});
const expanded = ref(false);
const mobileColumn = ref<Status>(props.filters.status in statuses ? props.filters.status as Status : "pending");
watch(
    () => props.filters,
    (value) => {
        for (const key of Object.keys(filters.data())) {
            (filters as unknown as Record<string, string>)[key] =
                value[key] || "";
        }
        if (value.status && value.status in statuses)
            mobileColumn.value = value.status as Status;
    },
);
const quickView = computed(() =>
    props.filters.unassigned === "1"
        ? "unassigned"
        : props.filters.priority === "urgent"
          ? "urgent"
          : props.filters.assigned_to === String(user.value.id)
            ? "mine"
            : "all",
);
function selectQueue(queue: string) {
    filters.assigned_to = queue === "mine" ? String(user.value.id) : "";
    filters.unassigned = queue === "unassigned" ? "1" : "";
    filters.priority = queue === "urgent" ? "urgent" : "";
    search();
}
const appliedFilters = computed(() =>
    Object.entries(props.filters).filter(
        ([key, value]) => key in filters.data() && value,
    ),
);
function filterLabel(key: string, value: string) {
    const names: Record<string, string> = {
        q: "Búsqueda",
        status: "Estado",
        priority: "Prioridad",
        category_id: "Categoría",
        assigned_to: "Responsable",
        user_id: "Solicitante",
        from: "Desde",
        to: "Hasta",
        unassigned: "Asignación",
    };
    const label =
        key === "status"
            ? statuses[value as Status]
            : key === "priority"
              ? priorities[value as keyof typeof priorities]
              : key === "category_id"
                ? props.categories.find((c) => String(c.id) === value)?.name
                : key === "assigned_to"
                  ? props.technicians.find((p) => String(p.id) === value)?.name
                  : key === "user_id"
                    ? props.requesters.find((p) => String(p.id) === value)?.name
                    : key === "unassigned"
                      ? "Sin asignar"
                      : value;
    return `${names[key]}: ${label || value}`;
}
function removeFilter(key: string) {
    (filters as unknown as Record<string, string>)[key] = "";
    search();
}

const resolution = ref<Ticket | null>(null);
const operationErrors = ref<Record<string, string>>({});
const busy = ref(false);
const dragTicket = ref<Ticket | null>(null);
const overColumn = ref<string | null>(null);
const base = computed(() =>
    props.support ? "/support/tickets" : "/mis-tickets",
);
const activeFilters = computed(() => appliedFilters.value.length);
function search(view = props.view, limit = props.filters.limit || "12") {
    const data = Object.fromEntries(
        Object.entries(filters.data()).filter(([, value]) => value !== ""),
    );
    filters
        .transform(() => ({ ...data, view, limit }))
        .get(base.value, { preserveState: true, preserveScroll: true });
}
function clear() {
    filters.reset();
    Object.assign(filters, {
        q: "",
        status: "",
        priority: "",
        category_id: "",
        assigned_to: "",
        user_id: "",
        from: "",
        to: "",
        unassigned: "",
    });
    search();
}
function mutate(ticket: Ticket, data: Record<string, unknown>) {
    if (busy.value) return;
    operationErrors.value = {};
    busy.value = true;
    router.patch(
        `/tickets/${ticket.id}`,
        { version: ticket.version, ...data },
        {
            preserveScroll: true,
            onError: (errors) => {
                operationErrors.value = errors;
            },
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}
function move(ticket: Ticket, status: Status) {
    if (ticket.status === status) return;
    if (status === "completed") {
        resolution.value = ticket;
        return;
    }
    mutate(ticket, { status });
}
function drop(status: Status) {
    if (dragTicket.value) move(dragTicket.value, status);
    dragTicket.value = null;
    overColumn.value = null;
}
function allowed(ticket: Ticket): Status[] {
    return ticket.status === "pending"
        ? ["in_progress"]
        : ticket.status === "in_progress"
          ? ["pending", "completed"]
          : ["in_progress"];
}
const greeting = computed(() => user.value.name.split(" ")[0]);
const mean = computed(() =>
    props.metrics.avg_minutes === null
        ? "Sin datos"
        : props.metrics.avg_minutes < 60
          ? `${props.metrics.avg_minutes} min`
          : `${Math.floor(props.metrics.avg_minutes / 60)}h ${props.metrics.avg_minutes % 60}m`,
);
</script>
<template>
    <Head :title="support ? 'Mesa de soporte' : 'Mis solicitudes'" />
    <AppLayout :title="support ? 'Mesa de soporte' : 'Mis solicitudes'">
        <div class="page-heading">
            <div>
                <div class="eyebrow">
                    {{
                        support
                            ? "CENTRO DE OPERACIONES"
                            : "TU ESPACIO DE SOPORTE"
                    }}
                </div>
                <h1>
                    {{ support ? "Mesa de soporte" : "Mis solicitudes"
                    }}<span class="heading-dot">.</span>
                </h1>
                <p>
                    {{
                        support
                            ? `Hola, ${greeting}. Aquí empieza una mejor atención para tu equipo.`
                            : "El seguimiento de tus solicitudes, sin perder ningún detalle."
                    }}
                </p>
            </div>
            <Link href="/tickets/create" class="btn primary"
                ><Icon name="Plus" />Nueva solicitud</Link
            >
        </div>
        <section class="metrics-grid" aria-label="Resumen de tickets">
            <div class="metric-card">
                <div class="metric-top">
                    <span>Pendientes</span
                    ><span class="metric-icon amber"
                        ><Icon name="Clock3"
                    /></span>
                </div>
                <div class="metric-value">
                    {{ metrics.pending
                    }}<span class="metric-caption">por atender</span>
                </div>
                <div class="metric-foot">
                    <span class="status-dot amber-dot"></span>Esperando atención
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-top">
                    <span>En proceso</span
                    ><span class="metric-icon blue"
                        ><Icon name="RefreshCw"
                    /></span>
                </div>
                <div class="metric-value">
                    {{ metrics.in_progress
                    }}<span class="metric-caption">en atención</span>
                </div>
                <div class="metric-foot">
                    <span class="status-dot blue-dot"></span>Estamos trabajando
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-top">
                    <span>Finalizados hoy</span
                    ><span class="metric-icon green"
                        ><Icon name="CircleCheck"
                    /></span>
                </div>
                <div class="metric-value">
                    {{ metrics.resolved_today
                    }}<span class="metric-caption">solicitudes</span>
                </div>
                <div class="metric-foot">
                    <Icon name="CheckCheck" :size="14" />Soluciones entregadas
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-top">
                    <span>Tiempo de resolución</span
                    ><span class="metric-icon violet"
                        ><Icon name="Clock3"
                    /></span>
                </div>
                <div
                    :class="[
                        'metric-value',
                        { 'text-metric': mean === 'Sin datos' },
                    ]"
                >
                    {{ mean }}
                </div>
                <div class="metric-foot">
                    Promedio de primeros cierres de hoy
                </div>
            </div>
        </section>
        <div v-if="support" class="queue-summary">
            <span
                ><span class="status-dot red-dot"></span
                ><strong>{{ metrics.urgent }}</strong> urgentes abiertos</span
            ><span class="summary-divider"></span
            ><span
                ><Icon name="UserRound" :size="15" /><strong>{{
                    metrics.unassigned
                }}</strong>
                sin asignar</span
            ><span class="summary-hint">Cada solicitud cuenta.</span>
        </div>
        <div class="board-heading">
            <div>
                <h2>
                    {{ support ? "Todas las solicitudes" : "Tus solicitudes" }}
                    <span class="neutral-count">{{
                        support
                            ? Object.values(columns).reduce(
                                  (sum, c) => sum + c.total,
                                  0,
                              ) ||
                              tickets?.total ||
                              0
                            : tickets?.total || 0
                    }}</span>
                </h2>
                <p>
                    {{
                        support
                            ? "Organiza, prioriza y acompaña cada solicitud."
                            : "Consulta el progreso y conversa con soporte."
                    }}
                </p>
            </div>
            <div
                v-if="support"
                class="view-switch"
                aria-label="Vista de solicitudes"
            >
                <button
                    :class="{ selected: view === 'board' }"
                    :aria-pressed="view === 'board'"
                    @click="search('board')"
                >
                    <Icon name="LayoutGrid" :size="16" />Tablero</button
                ><button
                    :class="{ selected: view === 'table' }"
                    :aria-pressed="view === 'table'"
                    @click="search('table')"
                >
                    <Icon name="List" :size="16" />Lista
                </button>
            </div>
        </div>
        <div
            v-if="support"
            class="queue-shortcuts"
            role="group"
            aria-label="Accesos rápidos de solicitudes"
        >
            <button
                v-for="queue in [
                    { key: 'all', label: 'Todas', icon: 'Inbox' },
                    { key: 'mine', label: 'Asignadas a mí', icon: 'UserRound' },
                    { key: 'unassigned', label: 'Sin asignar', icon: 'Users' },
                    { key: 'urgent', label: 'Urgentes', icon: 'AlertTriangle' },
                ]"
                :key="queue.key"
                type="button"
                :class="{ selected: quickView === queue.key }"
                :aria-pressed="quickView === queue.key"
                :disabled="filters.processing"
                @click="selectQueue(queue.key)"
            >
                <Icon :name="queue.icon" :size="16" />{{ queue.label
                }}<span v-if="queue.key === 'unassigned'">{{
                    metrics.unassigned
                }}</span>
            </button>
        </div>
        <form class="filters" @submit.prevent="search()">
            <div class="filter-main">
                <div class="search-field">
                    <Icon name="Search" /><input
                        v-model="filters.q"
                        aria-label="Buscar solicitudes"
                        placeholder="Buscar por código, asunto o solicitante…"
                    /><button
                        class="search-submit"
                        type="submit"
                        aria-label="Buscar solicitudes"
                        :disabled="filters.processing"
                    >
                        <Icon name="ArrowRight" :size="18" />
                    </button>
                </div>
                <select
                    v-model="filters.priority"
                    aria-label="Filtrar por prioridad"
                    @change="search()"
                >
                    <option value="">Todas las prioridades</option>
                    <option
                        v-for="(name, key) in priorities"
                        :key="key"
                        :value="key"
                    >
                        {{ name }}
                    </option></select
                ><select
                    v-model="filters.category_id"
                    aria-label="Filtrar por categoría"
                    @change="search()"
                >
                    <option value="">Todas las categorías</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">
                        {{ c.name }}
                    </option></select
                ><button
                    type="button"
                    :class="[
                        'btn secondary filter-toggle',
                        { selected: expanded },
                    ]"
                    :aria-expanded="expanded"
                    @click="expanded = !expanded"
                >
                    <Icon name="SlidersHorizontal" />Filtros<span
                        v-if="activeFilters"
                        class="filter-count"
                        >{{ activeFilters }}</span
                    ></button
                ><button class="sr-only" type="submit">Buscar</button>
            </div>
            <div v-if="expanded" class="filter-expanded">
                <label class="field"
                    >Estado<select v-model="filters.status">
                        <option value="">Todos</option>
                        <option
                            v-for="(name, key) in statuses"
                            :key="key"
                            :value="key"
                        >
                            {{ name }}
                        </option>
                    </select></label
                ><label v-if="support" class="field"
                    >Responsable<select v-model="filters.assigned_to">
                        <option value="">Todos</option>
                        <option
                            v-for="tech in technicians"
                            :key="tech.id"
                            :value="tech.id"
                        >
                            {{ tech.name }}
                        </option>
                    </select></label
                ><label v-if="support" class="field"
                    >Solicitante<select v-model="filters.user_id">
                        <option value="">Todos</option>
                        <option
                            v-for="p in requesters"
                            :key="p.id"
                            :value="p.id"
                        >
                            {{ p.name }}
                        </option>
                    </select></label
                ><label class="field"
                    >Desde<input v-model="filters.from" type="date" /></label
                ><label class="field"
                    >Hasta<input v-model="filters.to" type="date" /></label
                ><label v-if="support" class="checkbox-label"
                    ><input
                        v-model="filters.unassigned"
                        type="checkbox"
                        true-value="1"
                        false-value=""
                    />Sin asignar</label
                ><button class="btn primary" :disabled="filters.processing">
                    Aplicar
                </button>
            </div>
            <div v-if="activeFilters" class="active-filters">
                <button
                    v-for="[key, value] in appliedFilters"
                    :key="key"
                    type="button"
                    class="filter-chip"
                    :aria-label="`Quitar filtro ${filterLabel(key, value)}`"
                    @click="removeFilter(key)"
                >
                    {{ filterLabel(key, value)
                    }}<Icon name="X" :size="13" /></button
                ><button type="button" class="text-button" @click="clear">
                    <Icon name="X" :size="14" />Limpiar filtros
                </button>
            </div>
        </form>
        <FormErrors :errors="{ ...filters.errors, ...operationErrors }" />
        <div class="results-feedback" role="status" aria-live="polite">
            {{
                filters.processing
                    ? "Actualizando solicitudes…"
                    : activeFilters
                      ? "Mostrando los resultados de tus filtros"
                      : ""
            }}
        </div>
        <div
            v-if="view === 'board'"
            class="mobile-board-nav"
            role="group"
            aria-label="Columnas del tablero"
        >
            <button
                v-for="(label, status) in statuses"
                :key="status"
                :aria-pressed="mobileColumn === status"
                :class="{ selected: mobileColumn === status }"
                @click="mobileColumn = status"
            >
                {{ label }}<span>{{ columns[status]?.total || 0 }}</span>
            </button>
        </div>
        <section
            v-if="view === 'board'"
            class="kanban"
            aria-label="Tablero de solicitudes"
            :aria-busy="busy"
        >
            <div
                v-for="(name, status) in statuses"
                :key="status"
                :class="[
                    'kanban-column',
                    status,
                    {
                        'drag-over': overColumn === status,
                        'mobile-active': mobileColumn === status,
                    },
                ]"
                @dragover.prevent="overColumn = status"
                @dragleave="overColumn = null"
                @drop.prevent="drop(status)"
            >
                <div class="column-heading">
                    <div>
                        <span class="column-status-icon"
                            ><Icon
                                :name="
                                    status === 'pending'
                                        ? 'Circle'
                                        : status === 'in_progress'
                                          ? 'Clock3'
                                          : 'CircleCheck'
                                "
                                :size="17"
                        /></span>
                        <h3>{{ name }}</h3>
                        <span class="column-count">{{
                            columns[status]?.total || 0
                        }}</span>
                    </div>
                    <span class="column-caption">{{
                        status === "pending"
                            ? "Por atender"
                            : status === "in_progress"
                              ? "En atención"
                              : "Resueltas"
                    }}</span>
                </div>
                <div class="column-content">
                    <article
                        v-for="ticket in columns[status]?.items || []"
                        :key="ticket.id"
                        :class="[
                            'ticket-card',
                            { 'is-dragging': dragTicket?.id === ticket.id },
                        ]"
                        :draggable="!busy"
                        @dragstart="dragTicket = ticket"
                        @dragend="
                            dragTicket = null;
                            overColumn = null;
                        "
                    >
                        <div class="ticket-card-top">
                            <span class="ticket-code">{{ ticket.code }}</span
                            ><Badge :value="ticket.priority" />
                        </div>
                        <Link
                            :href="`/tickets/${ticket.id}`"
                            class="ticket-title"
                            >{{ ticket.subject }}</Link
                        >
                        <p class="ticket-excerpt">{{ ticket.description }}</p>
                        <span class="category-tag">{{
                            ticket.category.name
                        }}</span>
                        <div class="ticket-requester">
                            <span
                                class="avatar tiny"
                                :class="`tone-${ticket.user_id % 4}`"
                                >{{ initials(ticket.requester.name) }}</span
                            ><span>{{ ticket.requester.name }}</span
                            ><time :datetime="ticket.created_at">{{
                                date(ticket.created_at)
                            }}</time>
                        </div>
                        <div class="ticket-card-footer">
                            <span v-if="ticket.assignee" class="assigned-person"
                                ><span class="avatar mini">{{
                                    initials(ticket.assignee.name)
                                }}</span
                                >{{ ticket.assignee.name.split(" ")[0] }}</span
                            ><button
                                v-else
                                class="assign-button"
                                :disabled="busy"
                                @click="
                                    mutate(ticket, { assigned_to: user.id })
                                "
                            >
                                <Icon name="Plus" :size="13" />Asignarme
                            </button>
                            <div class="ticket-counters">
                                <span
                                    ><Icon name="MessageSquare" :size="14" />{{
                                        ticket.comments_count
                                    }}</span
                                ><span v-if="ticket.attachments_count"
                                    ><Icon name="Paperclip" :size="14" />{{
                                        ticket.attachments_count
                                    }}</span
                                >
                                <details class="card-menu">
                                    <summary
                                        :aria-label="`Acciones de ${ticket.code}`"
                                    >
                                        <Icon
                                            name="MoreHorizontal"
                                            :size="18"
                                        />
                                    </summary>
                                    <div class="dropdown">
                                        <Link :href="`/tickets/${ticket.id}`"
                                            >Ver detalle</Link
                                        ><button
                                            v-for="next in allowed(ticket)"
                                            :key="next"
                                            :disabled="busy"
                                            @click="move(ticket, next)"
                                        >
                                            {{
                                                next === "completed"
                                                    ? "Finalizar"
                                                    : next === "pending"
                                                      ? "Volver a pendiente"
                                                      : ticket.status ===
                                                          "completed"
                                                        ? "Reabrir ticket"
                                                        : "Iniciar atención"
                                            }}
                                        </button>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </article>
                    <div
                        v-if="!columns[status]?.items.length"
                        class="column-empty"
                    >
                        <Icon name="Inbox" :size="28" /><strong>{{
                            activeFilters
                                ? "Sin coincidencias"
                                : "Todo despejado"
                        }}</strong>
                        <p>
                            {{
                                activeFilters
                                    ? "Prueba con otros filtros."
                                    : "No hay solicitudes en esta columna."
                            }}
                        </p>
                    </div>
                    <button
                        v-if="
                            columns[status]?.total >
                                columns[status]?.items.length &&
                            Number(props.filters.limit || 12) < 120
                        "
                        class="load-more"
                        :disabled="filters.processing"
                        @click="
                            search(
                                view,
                                String(
                                    Math.min(
                                        120,
                                        Number(props.filters.limit || 12) + 12,
                                    ),
                                ),
                            )
                        "
                    >
                        Cargar más solicitudes
                        <Icon name="ChevronDown" :size="15" /></button
                    ><button
                        v-if="
                            columns[status]?.total >
                                columns[status]?.items.length &&
                            Number(props.filters.limit || 12) >= 120
                        "
                        class="load-more"
                        @click="search('table')"
                    >
                        Ver todas en lista <Icon name="ArrowRight" :size="15" />
                    </button>
                </div>
            </div>
        </section>
        <section v-else class="table-panel">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Solicitud</th>
                            <th>Solicitante</th>
                            <th>Estado</th>
                            <th>Prioridad</th>
                            <th>Responsable</th>
                            <th>Creada</th>
                            <th><span class="sr-only">Detalle</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ticket in tickets?.data" :key="ticket.id">
                            <td>
                                <Link
                                    :href="`/tickets/${ticket.id}`"
                                    class="table-ticket"
                                    ><span class="ticket-code">{{
                                        ticket.code
                                    }}</span
                                    ><strong>{{ ticket.subject }}</strong
                                    ><small>{{
                                        ticket.category.name
                                    }}</small></Link
                                >
                            </td>
                            <td>{{ ticket.requester.name }}</td>
                            <td>
                                <Badge :value="ticket.status" kind="status" />
                            </td>
                            <td><Badge :value="ticket.priority" /></td>
                            <td>
                                {{ ticket.assignee?.name || "Sin asignar" }}
                            </td>
                            <td class="nowrap">
                                {{ date(ticket.created_at) }}
                            </td>
                            <td>
                                <Link
                                    :href="`/tickets/${ticket.id}`"
                                    class="icon-button"
                                    :aria-label="`Ver ${ticket.code}`"
                                    ><Icon name="ArrowRight"
                                /></Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="!tickets?.data.length" class="empty-state">
                <span class="icon-tile"><Icon name="Inbox" :size="30" /></span>
                <h3>No encontramos solicitudes</h3>
                <p>
                    {{
                        activeFilters
                            ? "Prueba otra búsqueda o elimina los filtros para ver todas tus solicitudes."
                            : "Cuando necesites ayuda, crea tu primera solicitud y sigue su progreso aquí."
                    }}
                </p>
                <button
                    v-if="activeFilters"
                    class="btn secondary"
                    @click="clear"
                >
                    Limpiar filtros</button
                ><Link v-else href="/tickets/create" class="btn primary"
                    ><Icon name="Plus" />Nueva solicitud</Link
                >
            </div>
            <div v-if="tickets?.total" class="table-bottom">
                <span
                    >{{ tickets.from }}–{{ tickets.to }} de
                    {{ tickets.total }} solicitudes</span
                ><Pagination :links="tickets.links" />
            </div>
        </section>
        <div v-if="support && view === 'board'" class="board-tip">
            <Icon name="ShieldCheck" :size="15" />Usa el menú de cada tarjeta para cambiar el estado. En escritorio también puedes arrastrarla entre columnas.
        </div>
        <ResolutionModal :ticket="resolution" @close="resolution = null" />
    </AppLayout>
</template>
