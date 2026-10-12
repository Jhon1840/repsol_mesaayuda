<script setup lang="ts">
import { ref, computed } from "vue";
import { Head, Link, useForm, usePage, router } from "@inertiajs/vue3";
import AppLayout from "../../layouts/AppLayout.vue";
import Icon from "../../components/Icon.vue";
import Badge from "../../components/Badge.vue";
import FormErrors from "../../components/FormErrors.vue";
import ResolutionModal from "../../components/ResolutionModal.vue";
import AttachmentGallery from "../../components/AttachmentGallery.vue";
import AttachmentPicker from "../../components/AttachmentPicker.vue";
import {
    priorities,
    statuses,
    initials,
    date,
    type Ticket,
    type Person,
    type Status,
} from "../../types";
const props = defineProps<{ ticket: Ticket; technicians: Person[] }>();
const page = usePage();
const support = computed(
    () => (page.props.auth as { user: Person }).user.role === "support",
);
const tab = ref("conversation");
const tabKeys = ["conversation", "files", "history"];
function navigateTabs(event: KeyboardEvent) {
    const index = tabKeys.indexOf(tab.value);
    const next =
        event.key === "ArrowRight"
            ? (index + 1) % 3
            : event.key === "ArrowLeft"
              ? (index + 2) % 3
              : event.key === "Home"
                ? 0
                : event.key === "End"
                  ? 2
                  : null;
    if (next === null) return;
    event.preventDefault();
    tab.value = tabKeys[next]!;
    document.getElementById(`tab-${tab.value}`)?.focus();
}

const resolving = ref(false);
const errors = ref<Record<string, string>>({});
const busy = ref(false);
const comment = useForm({ message: "" });
const attachment = useForm({ files: [] as File[] });
const nextStates = computed<Status[]>(() =>
    props.ticket.status === "pending"
        ? ["in_progress"]
        : props.ticket.status === "in_progress"
          ? ["pending", "completed"]
          : ["in_progress"],
);
function update(data: Record<string, unknown>) {
    busy.value = true;
    errors.value = {};
    router.patch(
        `/tickets/${props.ticket.id}`,
        { version: props.ticket.version, ...data },
        {
            preserveScroll: true,
            onError: (value) => {
                errors.value = value;
            },
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}
function changeStatus(status: Status) {
    if (status === "completed") resolving.value = true;
    else update({ status });
}
function submitComment() {
    comment.post(`/tickets/${props.ticket.id}/comments`, {
        preserveScroll: true,
        onSuccess: () => comment.reset(),
    });
}
function submitFiles() {
    if (!attachment.files.length || attachment.processing) return;
    attachment.post(`/tickets/${props.ticket.id}/attachments`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            attachment.reset();
        },
    });
}
const actions: Record<string, string> = {
    created: "Creó la solicitud",
    assigned: "Actualizó el responsable",
    status_changed: "Cambió el estado",
    priority_changed: "Cambió la prioridad",
    resolved: "Finalizó el ticket",
    reopened: "Reabrió el ticket",
    commented: "Añadió un comentario",
    attached: "Adjuntó un archivo",
};
function historyDetail(item: NonNullable<Ticket["histories"]>[number]) {
    const meta = item.metadata;
    if (meta?.field === "status")
        return `${statuses[meta.from as Status]} → ${statuses[meta.to as Status]}`;
    if (meta?.field === "priority")
        return `${priorities[meta.from as keyof typeof priorities]} → ${priorities[meta.to as keyof typeof priorities]}`;
    if (meta?.field === "assigned_to")
        return `${meta.to ? props.technicians.find((person) => person.id === Number(meta.to))?.name || "Responsable #" + meta.to : "Sin asignar"}`;
    return String(meta?.filename || "");
}
</script>
<template>
    <AppLayout
        :title="ticket.code"
        section="Solicitudes"
        ><Head :title="ticket.code" /><Link
            :href="support ? '/support/tickets' : '/mis-tickets'"
            class="back-link"
            ><Icon name="ArrowLeft" :size="16" />Volver a solicitudes</Link
        >
        <div class="detail-grid">
            <section class="panel detail-panel">
                <div class="ticket-card-top">
                    <span class="ticket-code">{{ ticket.code }}</span
                    ><Badge :value="ticket.status" kind="status" />
                </div>
                <h1 class="detail-subject">{{ ticket.subject }}</h1>
                <div class="detail-meta">
                    <Badge :value="ticket.priority" /><span>{{
                        ticket.category.name
                    }}</span
                    ><span>Creado el {{ date(ticket.created_at, true) }}</span>
                </div>
                <h2 class="section-label">Descripción</h2>
                <p class="detail-text">{{ ticket.description }}</p>
                <div v-if="ticket.resolution" class="resolution-box">
                    <h3>
                        <Icon name="CircleCheck" :size="17" />Solución aplicada
                    </h3>
                    <p>{{ ticket.resolution }}</p>
                    <p class="helper" style="margin-top: 8px">
                        Finalizado el {{ date(ticket.resolved_at, true) }}
                    </p>
                </div>
                <div
                    class="detail-tabs"
                    role="tablist"
                    aria-label="Contenido del ticket"
                    @keydown="navigateTabs"
                >
                    <button
                        id="tab-conversation"
                        role="tab"
                        :tabindex="tab === 'conversation' ? 0 : -1"
                        :aria-selected="tab === 'conversation'"
                        aria-controls="ticket-content"
                        :class="{ selected: tab === 'conversation' }"
                        @click="tab = 'conversation'"
                    >
                        <Icon name="MessageSquare" :size="15" />Conversación
                        <span class="neutral-count">{{
                            ticket.comments?.length
                        }}</span></button
                    ><button
                        id="tab-files"
                        role="tab"
                        :tabindex="tab === 'files' ? 0 : -1"
                        :aria-selected="tab === 'files'"
                        aria-controls="ticket-content"
                        :class="{ selected: tab === 'files' }"
                        @click="tab = 'files'"
                    >
                        <Icon name="Paperclip" :size="15" />Archivos
                        <span class="neutral-count">{{ ticket.attachments?.length || 0 }}</span></button
                    ><button
                        id="tab-history"
                        role="tab"
                        :tabindex="tab === 'history' ? 0 : -1"
                        :aria-selected="tab === 'history'"
                        aria-controls="ticket-content"
                        :class="{ selected: tab === 'history' }"
                        @click="tab = 'history'"
                    >
                        <Icon name="Clock3" :size="15" />Historial
                    </button>
                </div>
                <div
                    id="ticket-content"
                    role="tabpanel"
                    :aria-labelledby="`tab-${tab}`"
                >
                    <template v-if="tab === 'conversation'"
                        ><div
                            v-if="!ticket.comments?.length"
                            class="empty-state"
                            style="padding: 30px"
                        >
                            <Icon name="MessageSquare" :size="25" />
                            <h3>Inicia la conversación</h3>
                            <p>
                                Añade detalles o consulta al equipo de soporte.
                            </p>
                        </div>
                        <article
                            v-for="message in ticket.comments"
                            :key="message.id"
                            class="comment"
                        >
                            <span
                                class="avatar"
                                :class="`tone-${message.author.id % 4}`"
                                >{{ initials(message.author.name) }}</span
                            >
                            <div class="comment-body">
                                <div class="comment-head">
                                    <strong>{{ message.author.name }}</strong
                                    ><span
                                        v-if="message.author.role === 'support'"
                                        class="role-chip"
                                        >SOPORTE</span
                                    ><time>{{
                                        date(message.created_at, true)
                                    }}</time>
                                </div>
                                <p class="comment-message">
                                    {{ message.message }}
                                </p>
                            </div>
                        </article>
                        <form
                            class="comment-form"
                            @submit.prevent="submitComment"
                        >
                            <FormErrors :errors="comment.errors" /><label
                                class="field"
                                >Añadir comentario<textarea
                                    v-model="comment.message"
                                    required
                                    maxlength="5000"
                                    rows="3"
                                    placeholder="Escribe tu mensaje…"
                                />
                            </label>
                            <div class="form-actions">
                                <button
                                    class="btn primary"
                                    :disabled="
                                        comment.processing ||
                                        !comment.message.trim()
                                    "
                                >
                                    <Icon name="Send" :size="16" />{{
                                        comment.processing
                                            ? "Enviando…"
                                            : "Enviar comentario"
                                    }}
                                </button>
                            </div>
                        </form></template
                    ><template v-if="tab === 'files'">
                        <AttachmentGallery :files="ticket.attachments || []" />
                        <form
                            class="attachment-upload-form"
                            :aria-busy="attachment.processing"
                            @submit.prevent="submitFiles"
                        >
                            <div class="attachment-section-heading">
                                <div><h2>Añadir archivos</h2><p>Comparte capturas o documentos relacionados con esta solicitud.</p></div>
                            </div>
                            <FormErrors :errors="attachment.errors" />
                            <AttachmentPicker v-model="attachment.files" :disabled="attachment.processing" />
                            <div v-if="attachment.progress" class="attachment-upload-progress" role="status">
                                <span>Subiendo archivos… {{ attachment.progress.percentage }}%</span>
                                <progress :value="attachment.progress.percentage" max="100" aria-label="Progreso de carga de archivos" />
                            </div>
                            <div class="form-actions attachment-upload-actions">
                                <span class="helper"><Icon name="ShieldCheck" :size="15" />Solo visibles para ti y soporte</span>
                                <button
                                    class="btn primary"
                                    :disabled="attachment.processing || !attachment.files.length"
                                >
                                    <Icon name="Upload" />{{
                                        attachment.processing
                                            ? "Subiendo…"
                                            : attachment.files.length > 1 ? `Adjuntar ${attachment.files.length} archivos` : "Adjuntar archivo"
                                    }}
                                </button>
                            </div>
                        </form></template
                    ><template v-if="tab === 'history'"
                        ><article
                            v-for="item in ticket.histories"
                            :key="item.id"
                            class="history-item"
                        >
                            <span class="history-marker"
                                ><Icon name="Check" :size="12"
                            /></span>
                            <div>
                                <strong>{{ item.actor.name }}</strong> ·
                                {{ actions[item.action] || item.action }}
                                <p v-if="historyDetail(item)">
                                    {{ historyDetail(item) }}
                                </p>
                                <p
                                    v-if="item.metadata?.resolution"
                                    class="detail-text"
                                >
                                    {{ item.metadata.resolution }}
                                </p>
                                <time>{{ date(item.created_at, true) }}</time>
                            </div>
                        </article></template
                    >
                </div>
            </section>
            <aside class="detail-side">
                <div class="panel side-panel">
                    <h3>
                        {{
                            support
                                ? "Gestión del ticket"
                                : "Estado de tu solicitud"
                        }}
                    </h3>
                    <FormErrors :errors="errors" /><template v-if="support"
                        ><label class="field"
                            >Responsable<select
                                :value="ticket.assigned_to || ''"
                                :disabled="busy"
                                @change="
                                    update({
                                        assigned_to: (
                                            $event.target as HTMLSelectElement
                                        ).value
                                            ? Number(
                                                  (
                                                      $event.target as HTMLSelectElement
                                                  ).value,
                                              )
                                            : null,
                                    })
                                "
                            >
                                <option value="">Sin asignar</option>
                                <option
                                    v-for="tech in technicians"
                                    :key="tech.id"
                                    :value="tech.id"
                                >
                                    {{ tech.name }}
                                </option>
                            </select></label
                        ><label class="field"
                            >Prioridad<select
                                :value="ticket.priority"
                                :disabled="busy"
                                @change="
                                    update({
                                        priority: (
                                            $event.target as HTMLSelectElement
                                        ).value,
                                    })
                                "
                            >
                                <option
                                    v-for="(name, priority) in priorities"
                                    :key="priority"
                                    :value="priority"
                                >
                                    {{ name }}
                                </option>
                            </select></label
                        >
                        <div class="field">
                            Siguiente paso
                            <p v-if="!ticket.assigned_to" class="action-hint">
                                Selecciona un responsable antes de iniciar la
                                atención.
                            </p>
                            <div
                                style="display: flex; flex-wrap: wrap; gap: 8px"
                            >
                                <button
                                    v-for="state in nextStates"
                                    :key="state"
                                    :class="[
                                        'btn small',
                                        state === 'completed' ||
                                        state === 'in_progress'
                                            ? 'primary'
                                            : 'secondary',
                                    ]"
                                    :disabled="busy || !ticket.assigned_to"
                                    @click="changeStatus(state)"
                                >
                                    {{
                                        state === "completed"
                                            ? "Finalizar"
                                            : state === "pending"
                                              ? "Volver a pendiente"
                                              : ticket.status === "completed"
                                                ? "Reabrir ticket"
                                                : "Iniciar atención"
                                    }}
                                </button>
                            </div>
                        </div></template
                    ><template v-else
                        ><div class="info-row">
                            <span>Estado actual</span
                            ><Badge :value="ticket.status" kind="status" />
                        </div>
                        <div class="info-row">
                            <span>Responsable</span
                            ><strong>{{
                                ticket.assignee?.name ||
                                "Pendiente de asignación"
                            }}</strong>
                        </div>
                        <div class="info-row">
                            <span>Prioridad</span
                            ><Badge :value="ticket.priority" /></div
                    ></template>
                </div>
                <div class="panel side-panel">
                    <h3>Solicitante</h3>
                    <div class="person-info">
                        <span
                            class="avatar"
                            :class="`tone-${ticket.user_id % 4}`"
                            >{{ initials(ticket.requester.name) }}</span
                        >
                        <div>
                            <strong>{{ ticket.requester.name }}</strong
                            ><small>{{ ticket.requester.email }}</small>
                        </div>
                    </div>
                    <div class="info-row">
                        <span>Categoría</span
                        ><strong>{{ ticket.category.name }}</strong>
                    </div>
                </div>
                <div class="panel side-panel">
                    <h3>Tiempos de atención</h3>
                    <div class="info-row">
                        <span>Creación</span
                        ><strong>{{ date(ticket.created_at, true) }}</strong>
                    </div>
                    <div class="info-row">
                        <span>Primera asignación</span
                        ><strong>{{ date(ticket.assigned_at, true) }}</strong>
                    </div>
                    <div class="info-row">
                        <span>Primer inicio de atención</span
                        ><strong>{{ date(ticket.started_at, true) }}</strong>
                    </div>
                    <div class="info-row">
                        <span>Resolución actual</span
                        ><strong>{{ date(ticket.resolved_at, true) }}</strong>
                    </div>
                </div>
            </aside>
        </div>
        <ResolutionModal
            :ticket="resolving ? ticket : null"
            @close="resolving = false"
    /></AppLayout>
</template>
