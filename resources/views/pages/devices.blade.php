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
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Device</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                                        <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Mode</th>
                                        <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Last Activity</th>
                                        <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Tasks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($devices as $device)
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
                                            <span class="badge badge-sm bg-gradient-{{ $device->status === 'active' ? 'success' : 'secondary' }}">
                                                {{ $device->status }}
                                            </span>
                                        </td>
                                        <td class="align-middle text-center">
                                            <select class="form-control mode-selector" data-device-id="{{ $device->id }}">
                                                <option value="login" {{ $device->mode === 'login' ? 'selected' : '' }}>Login</option>
                                                <option value="worker" {{ $device->mode === 'worker' ? 'selected' : '' }}>Worker</option>
                                            </select>
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
@endsection

@push('js')
<script>
document.querySelectorAll('.mode-selector').forEach(select => {
    select.addEventListener('change', async function() {
        const deviceId = this.dataset.deviceId;
        const mode = this.value;

        try {
            const response = await fetch(`/devices/${deviceId}/mode`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ mode })
            });

            if (!response.ok) throw new Error('Network response was not ok');

            const result = await response.json();
            if (result.success) {
                // Optional: Show success toast/notification
            }
        } catch (error) {
            console.error('Error:', error);
            this.value = this.value === 'login' ? 'worker' : 'login'; // Revert selection
            // Optional: Show error toast/notification
        }
    });
});
</script>
@endpush
