@extends('layouts.app', ['class' => 'g-sidenav-show bg-gray-100'])

@section('content')
    @include('layouts.navbars.auth.topnav', ['title' => 'Devices'])
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header pb-0">
                        <h6>Devices Management</h6>
                    </div>
                    <div class="card-body px-0 pt-0 pb-2">
                        <div class="table-responsive p-0">
                            <table class="table align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                            Device</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                                            Status</th>
                                        <th
                                            class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                            Mode</th>
                                        <th
                                            class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                            Version</th>
                                        <th
                                            class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                            Last Activity</th>
                                        <th
                                            class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                            Tasks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($devices as $device)
                                        <tr>
                                            <td>
                                                <div class="d-flex px-2 py-1">
                                                    <div class="d-flex flex-column justify-content-center">
                                                        <h6 class="mb-0 text-sm">{{ $device->name }}</h6>
                                                        <p class="text-xs text-secondary mb-0">{{ $device->modem_type }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge badge-sm bg-gradient-{{ $device->status === 'active' ? 'success' : 'secondary' }}">
                                                    {{ $device->status }}
                                                </span>
                                            </td>
                                            <td class="align-middle text-center">
                                                <div class="d-flex justify-content-center align-items-center">
                                                    <select class="form-control form-control-sm mode-selector"
                                                        data-device-id="{{ $device->id }}" style="max-width: 100px;">
                                                        <option value="login"
                                                            {{ $device->mode === 'login' ? 'selected' : '' }}>Login</option>
                                                        <option value="worker"
                                                            {{ $device->mode === 'worker' ? 'selected' : '' }}>Worker
                                                        </option>
                                                    </select>
                                                </div>
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="text-secondary text-xs font-weight-bold">
                                                    {{ $device->version }}
                                                </span>
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="text-secondary text-xs font-weight-bold">
                                                    {{ $device->last_activity ? $device->last_activity->diffForHumans() : 'Never' }}
                                                </span>
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="text-secondary text-xs font-weight-bold">
                                                    {{ $device->statistics ? $device->statistics->success_task_counter : 0 }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @include('layouts.footers.auth.footer')
    </div>

    <!-- Success Toast -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert"
            aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    Mode updated successfully
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                    aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Error Toast -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert"
            aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    Failed to update mode. Please try again.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                    aria-label="Close"></button>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        document.querySelectorAll('.mode-selector').forEach(select => {
            select.addEventListener('change', async function() {
                const deviceId = this.dataset.deviceId;
                const mode = this.value;
                const originalValue = this.value;
                const successToast = new bootstrap.Toast(document.getElementById('successToast'));
                const errorToast = new bootstrap.Toast(document.getElementById('errorToast'));

                try {
                    const response = await fetch(`/devices/${deviceId}/mode`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                .content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            mode: mode
                        })
                    });

                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }

                    const result = await response.json();
                    if (result.success) {
                        successToast.show();
                    } else {
                        throw new Error('Update failed');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    this.value = originalValue; // Revert selection
                    errorToast.show();
                }
            });
        });
    </script>
@endpush
