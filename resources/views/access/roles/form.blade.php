@extends('layouts.app')

@section('title', $role->exists ? 'Edit role' : 'Add role')

@section('content')
    @include('masters.partials.head', [
        'title' => $role->exists ? ($canSave ? 'Edit '.$role->name : $role->name) : 'Add role',
        'intro' => $role->is_system
            ? 'This role is locked. Its permissions follow the system list.'
            : 'Tick what people with this role are allowed to do.',
    ])
    <form method="POST" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}" id="role-form">
        @csrf
        @if ($role->exists)
            @method('PUT')
        @endif
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    @include('masters.partials.field', ['name' => 'name', 'label' => 'Name', 'value' => $role->name, 'col' => 'col-md-4', 'disabled' => ! $canSave, 'autofocus' => ! $role->exists])
                    @include('masters.partials.field', ['name' => 'description', 'label' => 'What this role is for', 'value' => $role->description, 'col' => 'col-md-5', 'maxlength' => 255, 'required' => false, 'disabled' => ! $canSave])
                    <div class="col-md-3">
                        <div class="customer-stat text-center"><span id="permission-count">{{ count($selected) }}</span> permissions ticked</div>
                    </div>
                </div>
            </div>
        </div>
        @error('permissions')<div class="alert alert-danger">{{ $message }}</div>@enderror
        <div class="row g-3">
            @foreach ($groups as $group => $permissions)
                <div class="col-lg-6">
                    <div class="card h-100" data-permission-group>
                        <div class="card-body">
                            <div class="permission-group-head mb-2">
                                <div class="weigh-section-title mb-0">{{ $groupLabels[$group] ?? $group }}</div>
                                @if ($canSave)
                                    <button class="btn btn-link btn-sm p-0" type="button" data-toggle-group>Tick all</button>
                                @endif
                            </div>
                            <div class="permission-grid">
                                @foreach ($permissions as $permission)
                                    <div class="form-check">
                                        <input class="form-check-input" id="permission-{{ $permission->code }}" name="permissions[]" type="checkbox" value="{{ $permission->code }}" @checked(in_array($permission->code, $selected, true)) @disabled(! $canSave)>
                                        <label class="form-check-label" for="permission-{{ $permission->code }}">{{ $permission->name }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @if ($canSave)
            @include('masters.partials.form-foot', ['label' => 'Save role', 'backUrl' => route('roles.index')])
        @else
            <a class="btn btn-outline-secondary mt-3" href="{{ route('roles.index') }}">Back to roles</a>
        @endif
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('role-form');
            const boxes = () => form.querySelectorAll('input[name="permissions[]"]');
            const refresh = () => {
                document.getElementById('permission-count').textContent = [...boxes()].filter((box) => box.checked).length;
                form.querySelectorAll('[data-permission-group]').forEach((group) => {
                    const button = group.querySelector('[data-toggle-group]');
                    if (!button) return;
                    const inGroup = [...group.querySelectorAll('input[type="checkbox"]')];
                    button.textContent = inGroup.every((box) => box.checked) ? 'Untick all' : 'Tick all';
                });
            };
            form.addEventListener('click', (event) => {
                const button = event.target.closest('[data-toggle-group]');
                if (!button) return;
                const inGroup = [...button.closest('[data-permission-group]').querySelectorAll('input[type="checkbox"]')];
                const tick = !inGroup.every((box) => box.checked);
                inGroup.forEach((box) => { box.checked = tick; });
                refresh();
            });
            form.addEventListener('change', refresh);
            refresh();
        })();
    </script>
@endpush
