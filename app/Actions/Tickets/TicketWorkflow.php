<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TicketWorkflow
{
    public function create(User $actor, array $data, array $files = []): Ticket
    {
        $paths = [];
        try {
            return DB::transaction(function () use ($actor, $data, $files, &$paths) {
                $ticket = Ticket::create([...$data, 'user_id' => $actor->id, 'status' => 'pending', 'priority' => 'medium']);
                $this->history($ticket, $actor, 'created');
                $this->storeFiles($ticket, $actor, $files, $paths);
                $this->notify($ticket, $actor, 'Nueva solicitud: '.$ticket->subject, true);

                return $ticket;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }
    }

    public function update(Ticket $ticket, User $actor, array $data): void
    {
        DB::transaction(function () use ($ticket, $actor, $data) {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($ticket->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'Otro técnico actualizó este ticket. Recarga la página antes de continuar.']);
            }
            // Assignment, priority and status are separate operations, never a multi-field overwrite.
            $fields = array_intersect(array_keys($data), ['assigned_to', 'priority', 'status']);
            if (count($fields) !== 1) {
                throw ValidationException::withMessages(['status' => 'Realiza una operación por vez.']);
            }
            $field = array_values($fields)[0];
            $before = $ticket->{$field};
            $before = $before instanceof \BackedEnum ? $before->value : $before;
            $after = $data[$field];
            if ($before == $after) {
                return;
            }
            $metadata = ['field' => $field, 'from' => $before, 'to' => $after];
            $action = $field === 'assigned_to' ? 'assigned' : 'priority_changed';
            if ($field === 'assigned_to') {
                if ($after && ! User::whereKey($after)->where('is_active', true)->where('role', 'support')->exists()) {
                    throw ValidationException::withMessages(['assigned_to' => 'Selecciona un soporte activo.']);
                }
                if (! $after && $ticket->status === TicketStatus::InProgress) {
                    throw ValidationException::withMessages(['assigned_to' => 'Un ticket en proceso necesita responsable.']);
                }
                if ($after) {
                    $ticket->assigned_at ??= now();
                }
            }
            if ($field === 'status') {
                $allowed = ['pending' => ['in_progress'], 'in_progress' => ['pending', 'completed'], 'completed' => ['in_progress']];
                if (! in_array($after, $allowed[$before], true)) {
                    throw ValidationException::withMessages(['status' => 'Transición de estado no permitida.']);
                }
                if ($after === 'in_progress' && (! $ticket->assigned_to || ! User::whereKey($ticket->assigned_to)->where('role', 'support')->where('is_active', true)->exists())) {
                    throw ValidationException::withMessages(['status' => 'Asigna primero un técnico activo para iniciar la atención.']);
                }
                $action = 'status_changed';
                if ($after === 'completed') {
                    if (trim($data['resolution'] ?? '') === '') {
                        throw ValidationException::withMessages(['resolution' => 'Describe la solución aplicada para finalizar.']);
                    }
                    $ticket->resolution = $data['resolution'];
                    $ticket->resolved_at = now();
                    $ticket->resolved_by = $actor->id;
                    $action = 'resolved';
                    $metadata['resolution'] = $ticket->resolution;
                } elseif ($before === 'completed') {
                    $action = 'reopened';
                    $ticket->resolution = null;
                    $ticket->resolved_at = null;
                    $ticket->resolved_by = null;
                }
                if ($after === 'in_progress') {
                    $ticket->started_at ??= now();
                }
            }
            $previousAssignee = $ticket->assigned_to;
            $ticket->{$field} = $after;
            $ticket->version++;
            $ticket->save();
            $this->history($ticket, $actor, $action, $metadata);
            $this->notify($ticket, $actor, match ($action) {
                'assigned' => 'Se actualizó el responsable', 'resolved' => 'Ticket finalizado',
                'reopened' => 'Ticket reabierto', 'priority_changed' => 'Prioridad actualizada', default => 'Estado actualizado',
            }, false, $previousAssignee);
        });
    }

    public function comment(Ticket $ticket, User $actor, string $message): void
    {
        DB::transaction(function () use ($ticket, $actor, $message) {
            $ticket->comments()->create(['user_id' => $actor->id, 'message' => $message]);
            $this->history($ticket, $actor, 'commented');
            $this->notify($ticket, $actor, 'Nuevo comentario de '.$actor->name);
        });
    }

    public function attach(Ticket $ticket, User $actor, array $files): void
    {
        $paths = [];
        try {
            DB::transaction(function () use ($ticket, $actor, $files, &$paths) {
                $this->storeFiles($ticket, $actor, $files, $paths);
                $this->notify($ticket, $actor, 'Nuevos archivos adjuntos');
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }
    }

    private function storeFiles(Ticket $ticket, User $actor, array $files, array &$paths): void
    {
        foreach ($files as $file) {
            $path = $file->store('tickets/'.$ticket->id, 'local');
            if (! $path) {
                throw new \RuntimeException('No se pudo guardar el archivo.');
            }
            $paths[] = $path;
            $ticket->attachments()->create(['user_id' => $actor->id, 'path' => $path,
                'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);
            $this->history($ticket, $actor, 'attached', ['filename' => $file->getClientOriginalName()]);
        }
    }

    public function history(Ticket $ticket, User $actor, string $action, array $metadata = []): void
    {
        $ticket->histories()->create(['user_id' => $actor->id, 'action' => $action, 'metadata' => $metadata]);
    }

    private function notify(Ticket $ticket, User $actor, string $message, bool $allSupport = false, ?int $previousAssignee = null): void
    {
        $recipients = User::where('is_active', true)->where('id', '!=', $actor->id)
            ->where(function ($query) use ($ticket, $allSupport, $previousAssignee) {
                $query->whereIn('id', array_filter([$ticket->user_id, $ticket->assigned_to, $previousAssignee]));
                if ($allSupport) {
                    $query->orWhere('role', 'support');
                }
            })->get();
        Notification::send($recipients, new TicketActivity($ticket->id, $ticket->code, $message));
    }
}
