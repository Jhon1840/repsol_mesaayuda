<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\TicketWorkflow;
use App\Http\Requests\AttachmentRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $support = $request->routeIs('support.tickets');
        if ($support) {
            abort_unless($request->user()->isSupport(), 403);
        }
        $filters = $request->validate([
            'q' => 'nullable|string|max:180', 'status' => 'nullable|in:pending,in_progress,completed',
            'priority' => 'nullable|in:low,medium,high,urgent', 'category_id' => 'nullable|integer',
            'assigned_to' => 'nullable|integer', 'user_id' => 'nullable|integer', 'unassigned' => 'nullable|in:1',
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d',
            'view' => 'nullable|in:board,table', 'limit' => 'nullable|integer|min:12|max:120',
        ]);
        if ($request->filled('from') && $request->filled('to') && $filters['to'] < $filters['from']) {
            throw ValidationException::withMessages(['to' => 'La fecha final debe ser posterior a la inicial.']);
        }
        $query = Ticket::query()->with(['requester:id,name', 'assignee:id,name', 'category:id,name'])->withCount(['comments', 'attachments']);
        if (! $support) {
            $query->where('user_id', $request->user()->id);
        }
        if ($request->filled('q')) {
            $q = $filters['q'];
            $query->where(function ($query) use ($q) {
                $query->where('subject', 'like', '%'.$q.'%')->orWhere('description', 'like', '%'.$q.'%')
                    ->orWhereHas('requester', fn ($query) => $query->where('name', 'like', '%'.$q.'%'));
                if (preg_match('/^(?:HD-)?0*(\d+)$/i', $q, $matches)) {
                    $query->orWhere('id', (int) $matches[1]);
                }
            });
        }
        foreach (['status', 'priority', 'category_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($support) {
            foreach (['assigned_to', 'user_id'] as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $filters[$field]);
                }
            }
            if ($request->filled('unassigned')) {
                $query->whereNull('assigned_to');
            }
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters['from'], 'America/La_Paz')->startOfDay()->utc());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', CarbonImmutable::parse($filters['to'], 'America/La_Paz')->endOfDay()->utc());
        }
        $view = $support ? ($filters['view'] ?? 'board') : 'table';
        $ordered = fn ($q) => $q->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")->orderBy('created_at');
        $columns = [];
        if ($view === 'board') {
            foreach (['pending', 'in_progress', 'completed'] as $status) {
                $column = (clone $query)->where('status', $status);
                $total = $column->count();
                $items = $status === 'completed' ? $column->orderByDesc('resolved_at') : $ordered($column);
                $columns[$status] = ['items' => $items->limit($filters['limit'] ?? 12)->get(), 'total' => $total];
            }
        }

        return Inertia::render('Tickets/Index', [
            'support' => $support, 'view' => $view, 'filters' => $filters, 'columns' => $columns,
            'tickets' => $view === 'table' ? $ordered(clone $query)->paginate(15)->withQueryString() : null,
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            'technicians' => $support ? User::where('role', 'support')->where('is_active', true)->get(['id', 'name']) : [],
            'requesters' => $support ? User::orderBy('name')->get(['id', 'name']) : [],
            'metrics' => $this->metrics($request, $support),
        ]);
    }

    private function metrics(Request $request, bool $support): array
    {
        $base = Ticket::query();
        if (! $support) {
            $base->where('user_id', $request->user()->id);
        }
        $start = CarbonImmutable::now('America/La_Paz')->startOfDay()->utc();
        $end = $start->addDay();
        $closures = TicketHistory::where('action', 'resolved')->where('created_at', '>=', $start)->where('created_at', '<', $end)->whereIn('ticket_id', (clone $base)->select('id'));
        $firstClosures = TicketHistory::where('action', 'resolved')->whereIn('ticket_id', (clone $base)->select('id'))->selectRaw('ticket_id, MIN(created_at) AS first_closed_at')->groupBy('ticket_id')->get();
        $times = [];
        $ticketDates = (clone $base)->whereIn('id', $firstClosures->pluck('ticket_id'))->pluck('created_at', 'id');
        foreach ($firstClosures as $closure) {
            $closedAt = CarbonImmutable::parse($closure->first_closed_at, 'UTC');
            if ($closedAt->gte($start) && $closedAt->lt($end)) {
                $times[] = CarbonImmutable::parse($ticketDates[$closure->ticket_id])->diffInMinutes($closedAt);
            }
        }

        return ['pending' => (clone $base)->where('status', 'pending')->count(),
            'in_progress' => (clone $base)->where('status', 'in_progress')->count(),
            'unassigned' => (clone $base)->whereNull('assigned_to')->where('status', '!=', 'completed')->count(),
            'urgent' => (clone $base)->where('priority', 'urgent')->where('status', '!=', 'completed')->count(),
            'resolved_today' => $closures->distinct()->count('ticket_id'), 'avg_minutes' => count($times) ? (int) round(array_sum($times) / count($times)) : null];
    }

    public function create()
    {
        return Inertia::render('Tickets/Create', ['categories' => Category::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(StoreTicketRequest $request, TicketWorkflow $workflow)
    {
        $ticket = $workflow->create($request->user(), $request->safe()->only(['subject', 'description', 'category_id']), $request->file('files', []));

        return redirect('/tickets/'.$ticket->id)->with('success', 'Solicitud creada. Nuestro equipo te ayudará pronto.');
    }

    public function show(Ticket $ticket, Request $request)
    {
        Gate::authorize('view', $ticket);
        $ticket->load(['requester:id,name,email', 'assignee:id,name', 'category',
            'comments' => fn ($q) => $q->with('author:id,name,role')->orderBy('id'), 'attachments',
            'histories' => fn ($q) => $q->with('actor:id,name')->orderByDesc('id')]);
        $ticket->attachments->each(function (TicketAttachment $attachment): void {
            $attachment->makeHidden(['path']);
            $attachment->setAttribute('download_url', route('attachments.download', $attachment, false));
            $attachment->setAttribute('preview_url', in_array($attachment->mime_type, ['image/jpeg', 'image/png'], true)
                ? route('attachments.preview', $attachment, false)
                : null);
        });

        return Inertia::render('Tickets/Show', ['ticket' => $ticket, 'technicians' => $request->user()->isSupport() ? User::where('role', 'support')->where('is_active', true)->get(['id', 'name']) : []]);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket, TicketWorkflow $workflow)
    {
        $workflow->update($ticket, $request->user(), $request->validated());

        return back()->with('success', 'Ticket actualizado.');
    }

    public function comment(Request $request, Ticket $ticket, TicketWorkflow $workflow)
    {
        Gate::authorize('comment', $ticket);
        $data = $request->validate(['message' => 'required|string|max:5000']);
        $workflow->comment($ticket, $request->user(), $data['message']);

        return back()->with('success', 'Comentario enviado.');
    }

    public function attach(AttachmentRequest $request, Ticket $ticket, TicketWorkflow $workflow)
    {
        $workflow->attach($ticket, $request->user(), $request->file('files'));

        return back()->with('success', 'Archivos adjuntados.');
    }

    public function download(TicketAttachment $attachment)
    {
        Gate::authorize('view', $attachment->ticket);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function preview(TicketAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->ticket);
        abort_unless(in_array($attachment->mime_type, ['image/jpeg', 'image/png'], true), 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
