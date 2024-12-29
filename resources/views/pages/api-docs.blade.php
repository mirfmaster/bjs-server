@extends('layouts.app', ['class' => 'g-sidenav-show bg-gray-100'])

@section('content')
    @include('layouts.navbars.auth.topnav', ['title' => 'API Documentation'])
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header pb-0">
                        <h6>Available APIs</h6>
                    </div>
                    <div class="card-body px-0 pt-0 pb-2">
                        @foreach ($apis as $api)
                            <div class="table-responsive p-3">
                                <div class="mb-3">
                                    <span
                                        class="badge bg-gradient-{{ $api['method'] === 'GET' ? 'success' : 'primary' }}">{{ $api['method'] }}</span>
                                    <code class="ms-2">{{ $api['endpoint'] }}</code>
                                </div>
                                <p class="text-sm mb-3">{{ $api['description'] }}</p>

                                @if (!empty($api['notes']))
                                    <div class="alert alert-info text-sm mb-3" role="alert">
                                        <strong>Note:</strong> {{ $api['notes'] }}
                                    </div>
                                @endif

                                <h6 class="text-sm">Parameters:</h6>
                                <table class="table align-items-center mb-3">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Parameter</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Type</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Required</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($api['parameters'] as $param)
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <code>{{ $param['name'] }}</code>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <span class="badge bg-gradient-info">{{ $param['type'] }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <span class="text-xs">{{ $param['required'] }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <span class="text-xs">{{ $param['description'] }}</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                <h6 class="text-sm">Response Example:</h6>
                                <pre class="bg-gray-100 p-3 rounded"><code>{{ $api['response_example'] }}</code></pre>

                                <hr class="horizontal dark my-4">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @include('layouts.footers.auth.footer')
    </div>
@endsection
