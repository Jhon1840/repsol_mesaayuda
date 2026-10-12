export type Person = { id: number; name: string; email?: string; role?: string; is_active?: boolean };
export type Category = { id: number; name: string; is_active: boolean };
export type Status = 'pending' | 'in_progress' | 'completed';
export type Priority = 'low' | 'medium' | 'high' | 'urgent';
export type Attachment = {
    id: number; original_name: string; size: number; mime_type: string; created_at: string;
    download_url: string; preview_url: string | null;
};
export const fileSize = (bytes: number) => bytes < 1024
    ? `${bytes} B`
    : `${new Intl.NumberFormat('es-BO', { maximumFractionDigits: 1 }).format(bytes / (bytes < 1024 * 1024 ? 1024 : 1024 * 1024))} ${bytes < 1024 * 1024 ? 'KB' : 'MB'}`;
export const fileType = (name: string) => name.includes('.') ? name.split('.').pop()!.toUpperCase() : 'ARCHIVO';
export type Ticket = {
    id: number; code: string; subject: string; description: string; status: Status; priority: Priority;
    version: number; user_id: number; assigned_to: number | null; requester: Person; assignee: Person | null;
    category: Category; created_at: string; assigned_at: string | null; started_at: string | null; resolved_at: string | null;
    resolution: string | null; comments_count: number; attachments_count: number;
    comments?: { id: number; message: string; author: Person; created_at: string }[];
    attachments?: Attachment[];
    histories?: { id: number; action: string; actor: Person; metadata: Record<string, unknown>; created_at: string }[];
};
export type Pagination<T> = { data: T[]; total: number; from: number; to: number; links: { url: string | null; label: string; active: boolean }[] };
export const statuses: Record<Status, string> = { pending: 'Pendiente', in_progress: 'En proceso', completed: 'Finalizado' };
export const priorities: Record<Priority, string> = { low: 'Baja', medium: 'Media', high: 'Alta', urgent: 'Urgente' };
export const initials = (name: string) => name.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase();
export const date = (value: string | null, full = false) => value ? new Intl.DateTimeFormat('es-BO', { timeZone: 'America/La_Paz', day: '2-digit', month: 'short', ...(full ? { hour: '2-digit', minute: '2-digit' } : {}) }).format(new Date(value)) : '—';
