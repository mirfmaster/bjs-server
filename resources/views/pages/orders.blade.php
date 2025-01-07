@extends('layouts.app', ['class' => 'g-sidenav-show bg-gray-100'])

@section('content')
    @include('layouts.navbars.auth.topnav', ['title' => 'Workers'])
    <div class="container-fluid py-4">
        <!-- Create Order Section -->
        <div class="row mt-4">
            <div class="col-lg-12 mb-lg-0 mb-4">
                <div class="card">
                    <div class="card-header pb-0 p-3">
                        <div class="d-flex justify-content-between">
                            <h6 class="mb-2">Create Direct Order</h6>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        @if (session('error'))
                            <div class="alert alert-danger">
                                {{ session('error') }}
                            </div>
                        @endif
                        @if (session('success'))
                            <div class="alert alert-success">
                                {{ session('success') }}
                            </div>
                        @endif

                        <form action="{{ route('orders.store') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="type" class="form-control-label">Order Type</label>
                                        <select class="form-control" id="type" name="type" required>
                                            <option value="follow" {{ old('type') == 'follow' ? 'selected' : '' }}>Follow
                                            </option>
                                            <option value="like" {{ old('type') == 'like' ? 'selected' : '' }}>Like
                                            </option>
                                        </select>
                                        @error('type')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="target" class="form-control-label">Target (Username/URL
                                            Post)</label>
                                        <input class="form-control" type="text" name="target" id="target"
                                            value="{{ old('target') }}" required>
                                        @error('target')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="requested" class="form-control-label">Quantity</label>
                                        <input class="form-control" type="number" name="requested" id="requested"
                                            value="{{ old('requested', 10) }}" min="1" required>
                                        @error('requested')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-4">
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-primary">Create Order</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-lg-12 mb-lg-0 mb-4">
                <div class="card">
                    <div class="card-header pb-0 p-3">
                        <div class="d-flex justify-content-between">
                            <h6 class="mb-2">Currently working</h6>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-items-center">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Order ID
                                    </th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Source
                                    </th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Type
                                    </th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Target
                                    </th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7"> Status
                                    </th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">DB
                                        Progress</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Redis
                                        Progress</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Time
                                        Elapsed</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Created
                                    </th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($processeds as $order)
                                    <tr>
                                        <td>
                                            <div class="d-flex px-2 py-1">
                                                <span
                                                    class="text-secondary text-xs font-weight-bold">#{{ $order->id }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1 flex-column">
                                                <span class="text-secondary text-xs font-weight-bold">{{ $order->source }}
                                                    (#{{ $order->bjs_id }})
                                                </span>
                                                <span class="text-xs font-weight-bold mt-2 text-info">
                                                    {{ $order->reseller_name }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1">
                                                <span
                                                    class="text-secondary text-xs font-weight-bold">{{ ucfirst($order->kind) }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1">
                                                <a href="https://anon.ws/?to={{ urlencode($order->target) }}"
                                                    target="_blank" class="text-primary text-xs font-weight-bold">
                                                    {{ $order->username }}
                                                </a>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1 flex-column">
                                                <span
                                                    class="text-xs {{ $order->redis_status === 'completed' ? 'text-info' : ($order->redis_status === 'processing' ? '' : 'text-danger') }}">
                                                    Redis: {{ $order->redis_status }}
                                                </span>
                                                <span
                                                    class="text-xs {{ $order->status === 'completed' ? 'text-info' : ($order->status === 'processing' ? '' : 'text-danger') }}">
                                                    DB: {{ $order->status }}
                                                </span>
                                                <span
                                                    class="text-xs {{ $order->status_bjs === 'completed' ? 'text-info' : ($order->status_bjs === 'processing' ? '' : 'text-danger') }}">
                                                    BJS: {{ $order->status_bjs }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1 flex-column">
                                                <span class="text-secondary text-xs font-weight-bold">
                                                    {{ number_format($order->processed) }}/{{ number_format($order->requested) }}
                                                </span>
                                                <span
                                                    class="text-xs {{ $order->processed >= $order->requested ? 'text-success' : 'text-warning' }}">
                                                    Margin: {{ number_format($order->requested - $order->processed) }}
                                                    left
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1 flex-column">
                                                <span class="text-secondary text-xs font-weight-bold">
                                                    {{ number_format($order->redis_processed) }}/{{ number_format($order->redis_requested) }}
                                                </span>
                                                <span class="text-xs text-info">
                                                    Processing: {{ number_format($order->redis_processing) }}
                                                </span>
                                                <span class="text-xs text-danger">
                                                    Failed: {{ number_format($order->redis_failed) }}
                                                </span>
                                                <span class="text-xs text-warning">
                                                    Duplicates: {{ number_format($order->redis_duplicate) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1">
                                                @if ($order->started_at)
                                                    <span class="text-secondary text-xs font-weight-bold">
                                                        {{ $order->started_at->diffForHumans(null, true) }}
                                                    </span>
                                                @else
                                                    <span class="text-secondary text-xs">-</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1">
                                                <span class="text-secondary text-xs font-weight-bold">
                                                    {{ $order->created_at->format('Y-m-d H:i') }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1 gap-2">
                                                <form action="{{ route('orders.increment-priority', $order->id) }}"
                                                    method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary"
                                                        title="Increase Priority">
                                                        <i class="ni ni-bold-up text-black opacity-10"></i>
                                                    </button>
                                                </form>

                                                <form action="{{ route('orders.decrement-priority', $order->id) }}"
                                                    method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-warning"
                                                        title="Decrease Priority">
                                                        <i class="ni ni-bold-down text-black opacity-10"></i>
                                                    </button>
                                                </form>

                                                <form action="{{ route('orders.destroy', $order->id) }}" method="POST"
                                                    class="d-inline"
                                                    onsubmit="return confirm('Are you sure you want to delete this order?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        title="Delete Order">
                                                        <i class="ni ni-fat-remove text-black opacity-10"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>


        {{-- Order History Section --}}
        <div class="row mt-4">
            <div class="col-lg-12 mb-lg-0 mb-4">
                <div class="card">
                    <div class="card-header pb-0 p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Order History</h6>
                            <div class="d-flex align-items-center">
                                <form class="me-3 flex-grow-1" method="GET" action="{{ route('orders.index') }}">
                                    <div class="d-flex gap-2">
                                        <div class="search-wrapper flex-grow-1" style="max-width: 400px;">
                                            <input type="text" name="search"
                                                class="form-control form-control-lg border ps-3"
                                                style="border-radius: 50px; height: 45px; background: white;"
                                                placeholder="Search orders..." value="{{ $search }}">
                                        </div>
                                        <button class="btn btn-primary px-4" style="border-radius: 50px;"
                                            type="submit">Search</button>
                                    </div>
                                </form>
                                <select class="form-control" style="width: auto"
                                    onchange="window.location.href=this.value">
                                    @foreach ([10, 25, 50, 100] as $size)
                                        <option
                                            value="{{ route('orders.index', ['per_page' => $size, 'search' => $search]) }}"
                                            {{ request('per_page', 10) == $size ? 'selected' : '' }}>
                                            {{ $size }} per page
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-items-center">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Order
                                        ID</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Source
                                    </th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Type
                                    </th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Target
                                    </th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status
                                    </th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                        Progress</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Start /
                                        End
                                        Time</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Elapsed
                                        Time</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Created
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($history as $order)
                                    <tr>
                                        <td>
                                            <div class="d-flex px-2 py-1">
                                                <span
                                                    class="text-secondary text-xs font-weight-bold">#{{ $order->id }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1 flex-column">
                                                <span class="text-secondary text-xs font-weight-bold">
                                                    {{ $order->source }}
                                                    @if ($order->bjs_id)
                                                        (#{{ $order->bjs_id }})
                                                    @endif
                                                </span>
                                                <span class="text-xs font-weight-bold text-info">
                                                    {{ $order->reseller_name }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1">
                                                <span class="text-secondary text-xs font-weight-bold">
                                                    {{ ucfirst($order->kind) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1">
                                                <a href="{{ $order->target }}" target="_blank"
                                                    class="text-primary text-xs font-weight-bold">
                                                    Target
                                                </a>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1">
                                                <span
                                                    class="badge badge-sm bg-gradient-{{ $order->status === 'completed' ? 'success' : ($order->status === 'partial' ? 'warning' : 'danger') }}">
                                                    {{ $order->status }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1 flex-column">
                                                <span class="text-secondary text-xs font-weight-bold">
                                                    {{ number_format($order->processed) }}/{{ number_format($order->requested) }}
                                                </span>
                                                <span
                                                    class="text-xs {{ $order->processed >= $order->requested ? 'text-success' : 'text-warning' }}">
                                                    {{ number_format(($order->processed / $order->requested) * 100, 1) }}%
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1 flex-column">
                                                @if ($order->started_at)
                                                    <span class="text-secondary text-xs font-weight-bold">
                                                        {{ $order->started_at->format('Y-m-d H:i') }}
                                                    </span>
                                                    <span class="text-secondary text-xs font-weight-bold">
                                                        {{ $order->end_at->format('Y-m-d H:i') }}
                                                    </span>
                                                @else
                                                    <span class="text-secondary text-xs">-</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1">
                                                @if ($order->started_at && $order->end_at)
                                                    <span class="text-secondary text-xs font-weight-bold">
                                                        {{ $order->started_at->diffForHumans($order->end_at, true) }}
                                                    </span>
                                                @elseif ($order->started_at)
                                                    <span class="text-secondary text-xs font-weight-bold">
                                                        {{ $order->started_at->diffForHumans(now(), true) }}
                                                    </span>
                                                @else
                                                    <span class="text-secondary text-xs">-</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1">
                                                <span class="text-secondary text-xs font-weight-bold">
                                                    {{ $order->created_at->format('Y-m-d H:i') }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer pb-0">
                        <div class="d-flex justify-content-center">
                            {{ $history->links() }}
                        </div>
                        @if ($history->total() > 0)
                            <div class="text-center text-sm text-gray-700 py-2">
                                Showing {{ $history->firstItem() }} to {{ $history->lastItem() }} of
                                {{ $history->total() }} results
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @include('layouts.footers.auth.footer')
    </div>
@endsection
