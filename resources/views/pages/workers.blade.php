@extends('layouts.app', ['class' => 'g-sidenav-show bg-gray-100'])

@section('content')
    @include('layouts.navbars.auth.topnav', ['title' => 'Worker Management'])
    <div class="container-fluid py-4">
        <!-- Summary Cards Row -->
        <div class="row">
            <!-- Worker Status Card -->
            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Worker Status</p>
                                    <h5 class="font-weight-bolder">
                                        {{ number_format($statistics['active']) }} Active
                                    </h5>
                                    <p class="mb-0">
                                        <span
                                            class="text-warning text-sm font-weight-bolder">{{ number_format($statistics['active-limited']) }}</span>
                                        Limited
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-primary shadow-primary text-center rounded-circle">
                                    <i class="ni ni-single-02 text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feature Locks Card -->
            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Active Locks</p>
                                    <h5 class="font-weight-bolder">
                                        {{ number_format($locks['follow']) }} Follow
                                    </h5>
                                    <p class="mb-0">
                                        <span
                                            class="text-info text-sm font-weight-bolder">{{ number_format($locks['like']) }}</span>
                                        Like
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-danger shadow-danger text-center rounded-circle">
                                    <i class="ni ni-lock-circle-open text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Activity Card -->
            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">Today's Activity</p>
                                    <h5 class="font-weight-bolder">
                                        {{ number_format($statistics['total_follows']) }} Follows
                                    </h5>
                                    <p class="mb-0">
                                        <span
                                            class="text-success text-sm font-weight-bolder">{{ number_format($statistics['total_likes']) }}</span>
                                        Likes
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-success shadow-success text-center rounded-circle">
                                    <i class="ni ni-chart-bar-32 text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New Workers Card -->
            <div class="col-xl-3 col-sm-6">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-8">
                                <div class="numbers">
                                    <p class="text-sm mb-0 text-uppercase font-weight-bold">New Workers</p>
                                    <h5 class="font-weight-bolder">
                                        {{ number_format($newWorkers['daily']) }} Today
                                    </h5>
                                    <p class="mb-0">
                                        <span
                                            class="text-info text-sm font-weight-bolder">{{ number_format($newWorkers['weekly']) }}</span>
                                        This week
                                    </p>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon icon-shape bg-gradient-warning shadow-warning text-center rounded-circle">
                                    <i class="ni ni-user-run text-lg opacity-10" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Cards Row -->
        <div class="row mt-4">
            <!-- Worker Status Counts Table -->
            <div class="col-lg-8 mb-lg-0 mb-4">
                <div class="card">
                    <div class="card-header pb-0 p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Worker Status Distribution</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                data-bs-target="#bulkActionModal">
                                Bulk Actions
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive p-3">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status
                                    </th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                                        Count</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">%
                                        of Total</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($statusCounts as $status)
                                    <tr>
                                        <td>
                                            <div class="d-flex px-2 py-1">
                                                <div>
                                                    <div
                                                        class="icon icon-shape icon-sm
                                                        @if ($status->status == 'active') bg-gradient-success
                                                        @elseif($status->status == 'relogin')
                                                            bg-gradient-warning
                                                        @elseif($status->status == 'new_login')
                                                            bg-gradient-info
                                                        @else
                                                            bg-gradient-secondary @endif
                                                        shadow text-center me-2">
                                                        <i class="ni ni-circle-08 text-white opacity-10"></i>
                                                    </div>
                                                </div>
                                                <div class="d-flex flex-column justify-content-center">
                                                    <h6 class="mb-0 text-sm">
                                                        {{ ucfirst(str_replace('_', ' ', $status->status)) }}</h6>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <p class="text-sm font-weight-bold mb-0">{{ number_format($status->count) }}</p>
                                        </td>
                                        <td>
                                            <p class="text-sm font-weight-bold mb-0">
                                                {{ number_format(($status->count / $total) * 100, 1) }}%</p>
                                        </td>
                                        <td>
                                            <a href="#" class="btn btn-link text-info text-sm mb-0"
                                                data-bs-toggle="tooltip" data-bs-placement="top"
                                                title="View workers in this status">
                                                <i class="ni ni-zoom-split-in me-1"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                <tr class="bg-light">
                                    <td>
                                        <div class="d-flex px-2 py-1">
                                            <div class="d-flex flex-column justify-content-center">
                                                <h6 class="mb-0 text-sm font-weight-bold">Total</h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <p class="text-sm font-weight-bold mb-0">{{ number_format($total) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-sm font-weight-bold mb-0">100%</p>
                                    </td>
                                    <td>
                                        <a href="#" class="btn btn-link text-dark text-sm mb-0"
                                            data-bs-toggle="tooltip" data-bs-placement="top" title="View all workers">
                                            <i class="ni ni-bullet-list-67 me-1"></i> View All
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Worker Details Card -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header pb-0 p-3">
                        <h6 class="mb-0">Worker Details</h6>
                    </div>
                    <div class="card-body p-3">
                        <ul class="list-group">
                            <li class="list-group-item border-0 d-flex justify-content-between ps-0 mb-2 border-radius-lg">
                                <div class="d-flex align-items-center">
                                    <div class="icon icon-shape icon-sm me-3 bg-gradient-primary shadow text-center">
                                        <i class="ni ni-tag text-white opacity-10"></i>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <h6 class="mb-1 text-dark text-sm">2FA Enabled Workers</h6>
                                        <span class="text-xs">{{ number_format($twoFactors['active']) }} active, <span
                                                class="font-weight-bold">{{ number_format($twoFactors['all']) }}
                                                total</span></span>
                                    </div>
                                </div>
                                <div class="d-flex">
                                    <button
                                        class="btn btn-link btn-icon-only btn-rounded btn-sm text-dark icon-move-right my-auto">
                                        <i class="ni ni-bold-right" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </li>
                            <li class="list-group-item border-0 d-flex justify-content-between ps-0 mb-2 border-radius-lg">
                                <div class="d-flex align-items-center">
                                    <div class="icon icon-shape icon-sm me-3 bg-gradient-warning shadow text-center">
                                        <i class="ni ni-notification-70 text-white opacity-10"></i>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <h6 class="mb-1 text-dark text-sm">Max Following Error</h6>
                                        <span class="text-xs">{{ number_format($activeMaxFollow) }} active workers
                                            affected</span>
                                    </div>
                                </div>
                                <div class="d-flex">
                                    <button
                                        class="btn btn-link btn-icon-only btn-rounded btn-sm text-dark icon-move-right my-auto">
                                        <i class="ni ni-bold-right" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </li>
                            <li class="list-group-item border-0 d-flex justify-content-between ps-0 mb-2 border-radius-lg">
                                <div class="d-flex align-items-center">
                                    <div class="icon icon-shape icon-sm me-3 bg-gradient-info shadow text-center">
                                        <i class="ni ni-calendar-grid-58 text-white opacity-10"></i>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <h6 class="mb-1 text-dark text-sm">Monthly Growth</h6>
                                        <span class="text-xs">{{ number_format($newWorkers['monthly']) }} workers this
                                            month</span>
                                    </div>
                                </div>
                                <div class="d-flex">
                                    <button
                                        class="btn btn-link btn-icon-only btn-rounded btn-sm text-dark icon-move-right my-auto">
                                        <i class="ni ni-bold-right" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </li>
                            <li class="list-group-item border-0 d-flex justify-content-between ps-0 border-radius-lg">
                                <div class="d-flex align-items-center">
                                    <div class="icon icon-shape icon-sm me-3 bg-gradient-success shadow text-center">
                                        <i class="ni ni-chart-pie-35 text-white opacity-10"></i>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <h6 class="mb-1 text-dark text-sm">Active Rate</h6>
                                        <span class="text-xs font-weight-bold">
                                            {{ number_format(($statistics['active'] / $total) * 100, 1) }}% of workers
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex">
                                    <button
                                        class="btn btn-link btn-icon-only btn-rounded btn-sm text-dark icon-move-right my-auto">
                                        <i class="ni ni-bold-right" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Action Modal -->
        <div class="modal fade" id="bulkActionModal" tabindex="-1" role="dialog"
            aria-labelledby="bulkActionModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bulkActionModalLabel">Bulk Worker Actions</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="bulkActionForm">
                            <div class="form-group mb-3">
                                <label for="fromStatus" class="form-control-label">From Status</label>
                                <select class="form-control" id="fromStatus" name="from_status" required>
                                    <option value="">Select Status</option>
                                    @foreach ($statusCounts as $status)
                                        <option value="{{ $status->status }}">
                                            {{ ucfirst(str_replace('_', ' ', $status->status)) }}
                                            ({{ number_format($status->count) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group mb-3">
                                <label for="toStatus" class="form-control-label">To Status</label>
                                <select class="form-control" id="toStatus" name="to_status" required>
                                    <option value="">Select Status</option>
                                    <option value="active">Active</option>
                                    <option value="relogin">Relogin</option>
                                    <option value="pending">Pending</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="disabled">Disabled</option>
                                </select>
                            </div>
                            <div class="form-group mb-3">
                                <label for="limit" class="form-control-label">Limit (max records to update)</label>
                                <input type="number" class="form-control" id="limit" name="limit" min="1"
                                    max="1000" value="10">
                                <small class="form-text text-muted">Leave empty to update all matching records (use with
                                    caution)</small>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="executeBulkAction">Execute</button>
                    </div>
                </div>
            </div>
        </div>

        @include('layouts.footers.auth.footer')
    </div>
@endsection

@push('js')
    <script src="./assets/js/plugins/chartjs.min.js"></script>
    <script>
        // Handle bulk action form submission
        document.getElementById('executeBulkAction').addEventListener('click', function() {
        var form = document.getElementById('bulkActionForm');
        var fromStatus = document.getElementById('fromStatus').value;
        var toStatus = document.getElementById('toStatus').value;
        var limit = document.getElementById('limit').value;

        if (!fromStatus || !toStatus) {
            alert('Please select both From and To status');
            return;
        }

        // Confirmation dialog
        if (!confirm(
                `Are you sure you want to change workers from "${fromStatus}" to "${toStatus}" status?${limit ? ' (Limit: ' + limit + ' workers)' : ''}`
            )) {
            return;
        }

        // Build the URL with query parameters
        var url = '/api/worker/update-status?from_status=' + encodeURIComponent(fromStatus) +
            '&to_status=' + encodeURIComponent(toStatus);

        if (limit) {
            url += '&limit=' + encodeURIComponent(limit);
        }

        // Make the API request
        fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(
                        `Successfully updated ${data.data.affected_rows} workers from "${fromStatus}" to "${toStatus}"`
                    );
                    // Reload the page to reflect changes
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error occurred'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing your request');
            });

        // Close the modal
        var modal = bootstrap.Modal.getInstance(document.getElementById('bulkActionModal'));
        modal.hide();
        });
        });
    </script>
@endpush
