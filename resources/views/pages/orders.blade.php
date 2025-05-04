@extends('layouts.app', ['class' => 'g-sidenav-show bg-gray-100'])

@section('content')
    @include('layouts.navbars.auth.topnav', ['title' => 'Workers'])
    <div class="container-fluid py-4">
        {{-- Order sections --}}
        <div class="row mt-3">
            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Completed Orders</p>
                                    <h5 class="font-weight-bolder">
                                        {{ number_format($orders['completed']) }}
                                    </h5>
                                    <p class="mb-0">
                                        <span class="text-success text-sm font-weight-bolder"><br></span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-primary shadow-primary text-center rounded-circle">
                                    <i class="ni ni-money-coins text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Processing</p>
                                    <h5 class="font-weight-bolder">
                                        {{ number_format($orders['processing']) }}
                                    </h5>
                                    <p class="mb-0">
                                        <span
                                            class="text-success text-sm font-weight-bolder">{{ number_format($orders['inprogress']) }}</span>
                                        In progress
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-danger shadow-danger text-center rounded-circle">
                                    <i class="ni ni-world text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Cancelled Order</p>
                                    <h5 class="font-weight-bolder">
                                        {{ number_format($orders['canceled']) }}
                                    </h5>
                                    <p class="mb-0">
                                        <span
                                            class="text-danger text-sm font-weight-bolder">{{ number_format($orders['partial']) }}</span>
                                        Partialled
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-success shadow-success text-center rounded-circle">
                                    <i class="ni ni-paper-diploma text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Today Orders</p>
                                    @foreach ($statistics['order'] as $item)
                                        <p class="mb-0">
                                            {{ $item->kind }}: {{ number_format($item->total_requested) }}
                                        </p>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-warning shadow-warning text-center rounded-circle">
                                    <i class="ni ni-cart text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                            <h6 class="mb-2">Currently working (Left:
                                {{ number_format($statistics['leftOrders']->total_orders) }}
                                ({{ number_format($statistics['leftOrders']->total_requested) }}) )</h6>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-items-center">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Order
                                        ID
                                    </th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Source
                                    </th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Type
                                    </th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Target
                                    </th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7"> Status
                                    </th>
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
                                                    class="text-secondary text-xs font-weight-bold">{{ $loop->iteration }}.
                                                    #{{ $order->id }}</span>
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
                                                <a href="{{ $order->getAnonymizedUrl() }}" target="_blank"
                                                    class="text-primary text-xs font-weight-bold">
                                                    {{ $order->username }}
                                                </a>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1 flex-column">
                                                <span class="text-xs">
                                                    Start: {{ number_format($order->start_count) }}
                                                </span>
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
                                                    {{ number_format($order->redis_processed) }}/{{ number_format($order->redis_requested) }}
                                                    ({{ number_format($order->redis_requested - $order->redis_processed) }})
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
                                    <div class="d-flex gap-2 align-items-center">
                                        <div class="search-wrapper flex-grow-1" style="max-width: 400px;">
                                            <input type="text" name="search"
                                                class="form-control form-control-lg border ps-3"
                                                style="border-radius: 50px; height: 45px; background: white;"
                                                placeholder="Search orders..." value="{{ $search }}">
                                        </div>
                                        <button class="btn btn-primary px-4 mb-0" style="border-radius: 50px;"
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
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($history as $order)
                                    <tr>
                                        <td>
                                            <div class="d-flex px-2 py-1 flex-column">
                                                <span
                                                    class="text-secondary text-xs font-weight-bold">#{{ $order->id }}</span>
                                                <span
                                                    class="text-secondary text-xs font-weight-bold">{{ $order->created_at?->format('Y-m-d H:i') }}</span>
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
                                                <a href="{{ $order->getInstagramUrl() }}" rel="noreferrer nofollow"
                                                    target="_blank" class="text-primary text-xs font-weight-bold">
                                                    {{ $order->username }}
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
                                                        {{ $order->started_at?->format('Y-m-d H:i') }}
                                                    </span>
                                                    <span class="text-secondary text-xs font-weight-bold">
                                                        {{ $order->end_at?->format('Y-m-d H:i') }}
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
                                                        {{ $order->started_at?->diffForHumans($order->end_at, true) }}
                                                    </span>
                                                @elseif ($order->started_at)
                                                    <span class="text-secondary text-xs font-weight-bold">
                                                        {{ $order->started_at?->diffForHumans(now(), true) }}
                                                    </span>
                                                @else
                                                    <span class="text-secondary text-xs">-</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex px-2 py-1 gap-2">
                                                @if ($order->status == 'completed')
                                                    <form action="{{ route('orders.refill', $order->id) }}"
                                                        method="POST" class="d-inline"
                                                        onsubmit="return confirm('Are you sure you want to refill this order?');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-warning"
                                                            title="Refill Order">
                                                            <i class="ni ni-curved-next text-black"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($history->hasPages())
                        <div class="card-footer pb-0">
                            <div class="d-flex flex-column align-items-center">
                                <!-- Pagination Controls -->
                                <div class="d-flex gap-2 align-items-center mb-3">
                                    <!-- Previous Page Link -->
                                    @if ($history->onFirstPage())
                                        <span class="btn btn-sm btn-outline-secondary disabled">« Previous</span>
                                    @else
                                        <a href="{{ $history->previousPageUrl() }}"
                                            class="btn btn-sm btn-outline-secondary">
                                            « Previous
                                        </a>
                                    @endif

                                    <!-- Page Input -->
                                    <div class="d-flex align-items-center gap-2" @style(['align-self: flex-start'])>
                                        <input type="number" id="pageInput"
                                            class="form-control form-control-sm text-center" style="width: 60px;"
                                            value="{{ $history->currentPage() }}" min="1"
                                            max="{{ $history->lastPage() }}">
                                        <span class="text-sm text-secondary">of {{ $history->lastPage() }}</span>
                                    </div>

                                    <!-- Next Page Link -->
                                    @if ($history->hasMorePages())
                                        <a href="{{ $history->nextPageUrl() }}" class="btn btn-sm btn-outline-secondary">
                                            Next »
                                        </a>
                                    @else
                                        <span class="btn btn-sm btn-outline-secondary disabled">Next »</span>
                                    @endif
                                </div>

                                <!-- Results Count Text -->
                                <div class="text-sm text-secondary mb-3">
                                    Showing {{ $history->firstItem() }} to {{ $history->lastItem() }} of
                                    {{ $history->total() }} results
                                </div>
                            </div>
                        </div>

                        <script>
                            document.getElementById('pageInput').addEventListener('keypress', function(e) {
                                if (e.key === 'Enter') {
                                    const currentUrl = new URL(window.location.href);
                                    const page = parseInt(this.value);
                                    const maxPage = {{ $history->lastPage() }};

                                    if (page >= 1 && page <= maxPage) {
                                        currentUrl.searchParams.set('page', page);
                                        window.location.href = currentUrl.toString();
                                    } else {
                                        this.value = {{ $history->currentPage() }};
                                    }
                                }
                            });

                            document.getElementById('pageInput').addEventListener('blur', function() {
                                const page = parseInt(this.value);
                                const maxPage = {{ $history->lastPage() }};

                                if (page < 1 || page > maxPage || isNaN(page)) {
                                    this.value = {{ $history->currentPage() }};
                                }
                            });
                        </script>

                        <style>
                            .btn-outline-secondary {
                                background: white;
                                border-color: #dee2e6;
                                color: #6c757d;
                            }

                            .btn-outline-secondary:hover:not(.disabled) {
                                background: #f8f9fa;
                                border-color: #dee2e6;
                                color: #6c757d;
                            }

                            .btn-outline-secondary.disabled {
                                opacity: 0.5;
                                cursor: not-allowed;
                            }

                            .gap-2 {
                                gap: 0.5rem;
                            }

                            #pageInput::-webkit-inner-spin-button,
                            #pageInput::-webkit-outer-spin-button {
                                -webkit-appearance: none;
                                margin: 0;
                            }

                            #pageInput {
                                -moz-appearance: textfield;
                            }
                        </style>
                </div>
                @endif
            </div>
        </div>
        @include('layouts.footers.auth.footer')
    </div>
@endsection
