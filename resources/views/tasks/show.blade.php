@extends('layouts.app')

@section('title', $task->title)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2">
            <h1 class="h4 fw-bold mb-0" style="color: #1e3a5f;">{{ $task->title }}</h1>
            <x-status-badge :status="$task->status" :label="$task->statusLabel()" />
            <x-status-badge :status="$task->priority" :label="$task->priorityLabel()" />
        </div>
        <div class="small text-muted mt-1">{{ $task->client?->name ?? 'Internal Practice' }} · Created by {{ $task->creator?->name ?? 'System' }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('tasks.index') }}" class="btn btn-sm btn-outline-secondary">Back to Board</a>
        <form action="{{ route('tasks.destroy', $task) }}" method="POST" onsubmit="return confirm('Delete this task?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <form action="{{ route('tasks.update', $task) }}" method="POST" class="ca-card p-3 mb-3">
            @csrf @method('PUT')
            <h2 class="h6 fw-bold mb-3">Task Details</h2>
            <div class="row g-2">
                <div class="col-md-8"><label class="form-label small fw-semibold">Title</label><input type="text" name="title" value="{{ $task->title }}" class="form-control form-control-sm" required></div>
                <div class="col-md-4"><label class="form-label small fw-semibold">Category</label><select name="category" class="form-select form-select-sm">@foreach(\App\Models\Task::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected($task->category === $k)>{{ $v }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label small fw-semibold">Client</label><select name="client_id" class="form-select form-select-sm"><option value="">Internal</option>@foreach($clients as $c)<option value="{{ $c->id }}" @selected($task->client_id == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label small fw-semibold">Assignee</label><select name="assigned_to" class="form-select form-select-sm"><option value="">Unassigned</option>@foreach($staff as $m)<option value="{{ $m->id }}" @selected($task->assigned_to == $m->id)>{{ $m->name }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label small fw-semibold">Due Date</label><input type="date" name="due_date" value="{{ $task->due_date?->format('Y-m-d') }}" class="form-control form-control-sm"></div>
                <div class="col-md-6"><label class="form-label small fw-semibold">Priority</label><select name="priority" class="form-select form-select-sm">@foreach(\App\Models\Task::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected($task->priority === $k)>{{ $v }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label small fw-semibold">Status</label><select name="status" class="form-select form-select-sm">@foreach(\App\Models\Task::STATUSES as $k => $v)<option value="{{ $k }}" @selected($task->status === $k)>{{ $v }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label small fw-semibold">Description</label><textarea name="description" rows="3" class="form-control form-control-sm">{{ $task->description }}</textarea></div>
            </div>
            <div class="mt-3 text-end"><button type="submit" class="btn btn-sm btn-primary">Update Task</button></div>
        </form>

        <div class="ca-card p-3">
            <h2 class="h6 fw-bold mb-3">Comments &amp; Internal Notes</h2>
            <form action="{{ route('tasks.comments.store', $task) }}" method="POST" class="mb-3">
                @csrf
                <textarea name="comment" rows="2" class="form-control form-control-sm mb-2" required placeholder="Add progress update or review comment..."></textarea>
                <button type="submit" class="btn btn-sm btn-primary">Post Comment</button>
            </form>
            @forelse($task->comments as $c)
                <div class="border rounded p-2 mb-2 bg-light small">
                    <div class="d-flex justify-content-between fw-semibold mb-1">
                        <span>{{ $c->user?->name ?? 'User' }}</span>
                        <span class="text-muted fw-normal">{{ $c->created_at->diffForHumans() }}</span>
                    </div>
                    <div>{{ $c->comment }}</div>
                </div>
            @empty
                <div class="text-muted small">No comments yet.</div>
            @endforelse
        </div>
    </div>

    <div class="col-lg-5">
        <div class="ca-card p-3 mb-3">
            @php($totalMins = $task->timeLogs->sum('minutes'))
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0"><i class="bi bi-stopwatch me-1 text-primary"></i>Log Time &amp; Timesheet</h2>
                <span class="badge badge-soft-primary">{{ floor($totalMins / 60) }}h {{ $totalMins % 60 }}m logged</span>
            </div>
            <form action="{{ route('tasks.time_logs.store', $task) }}" method="POST" class="row g-2 mb-3">
                @csrf
                <div class="col-4">
                    <label class="form-label small mb-1">Minutes *</label>
                    <input type="number" name="minutes" min="1" max="1440" value="30" class="form-control form-control-sm" required>
                </div>
                <div class="col-5">
                    <label class="form-label small mb-1">Date *</label>
                    <input type="date" name="logged_date" value="{{ now()->format('Y-m-d') }}" class="form-control form-control-sm" required>
                </div>
                <div class="col-3 d-flex align-items-end">
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" name="is_billable" value="1" id="chkBillable" checked>
                        <label class="form-check-label small" for="chkBillable">Billable</label>
                    </div>
                </div>
                <div class="col-8">
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Work notes (optional)">
                </div>
                <div class="col-4">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Log Time</button>
                </div>
            </form>
            @forelse($task->timeLogs->take(5) as $tl)
                <div class="d-flex justify-content-between align-items-center border-top py-1 small">
                    <div>
                        <span class="fw-semibold">{{ $tl->minutes }}m</span> · {{ $tl->user?->name ?? 'Staff' }}
                        @if($tl->notes)<div class="text-muted" style="font-size: 0.72rem;">{{ $tl->notes }}</div>@endif
                    </div>
                    <div class="text-end">
                        <span class="badge {{ $tl->is_billable ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $tl->is_billable ? 'Billable' : 'Internal' }}</span>
                        <div class="text-muted" style="font-size: 0.72rem;">{{ $tl->logged_date->format('d M') }}</div>
                    </div>
                </div>
            @empty
                <div class="text-muted small">No time logged yet.</div>
            @endforelse
        </div>

        <div class="ca-card p-3 mb-3">
            <h2 class="h6 fw-bold mb-3">Attachments</h2>
            <form action="{{ route('tasks.attachments.store', $task) }}" method="POST" enctype="multipart/form-data" class="d-flex gap-2 mb-3">
                @csrf
                <input type="file" name="file" class="form-control form-control-sm" required>
                <button type="submit" class="btn btn-sm btn-primary text-nowrap">Upload</button>
            </form>
            @forelse($task->attachments as $att)
                <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-1 small">
                    <span class="text-truncate me-2">{{ $att->original_filename }}</span>
                    <a href="{{ route('tasks.attachments.download', [$task, $att]) }}" class="btn btn-sm btn-light border"><i class="bi bi-download"></i></a>
                </div>
            @empty
                <div class="text-muted small">No attachments.</div>
            @endforelse
        </div>

        <div class="ca-card p-3">
            <h2 class="h6 fw-bold mb-3">Task Activity History</h2>
            @forelse($task->activities as $act)
                <div class="border-bottom py-2 small">
                    <div class="fw-medium">{{ $act->details }}</div>
                    <div class="text-muted" style="font-size: 0.72rem;">{{ $act->user?->name ?? 'System' }} · {{ $act->created_at->format('d M Y, h:i A') }}</div>
                </div>
            @empty
                <div class="text-muted small">No activity recorded.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

