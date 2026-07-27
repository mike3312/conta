<nav class="app-navbar navbar navbar-expand bg-white">
    <div class="container-fluid px-3 px-lg-4">
        <button class="btn btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Abrir menú"><i class="bi bi-list fs-4"></i></button>
        <div class="ms-auto d-flex align-items-center gap-2 gap-lg-3">
            @if($layoutCompanies->isNotEmpty())
                <form action="{{ route('companies.switch') }}" method="POST" class="company-switcher">
                    @csrf
                    <input type="hidden" name="redirect_context" value="{{ request()->routeIs('fel.*', 'fel-documents.*', 'fel-imports.*') ? 'fel' : 'dashboard' }}">
                    <label for="companySwitcher" class="visually-hidden">Empresa activa</label>
                    <i class="bi bi-buildings"></i>
                    <select id="companySwitcher" name="company_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($layoutCompanies as $company)
                            <option value="{{ $company->id }}" @selected((string) session('company_id') === (string) $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </form>
            @else
                <a href="{{ route('companies.create') }}" class="btn btn-sm btn-primary">Crear empresa</a>
            @endif
            @if($layoutActiveCompany)
                <div class="active-company-name d-none d-xl-block"><small>Empresa activa</small><strong>{{ $layoutActiveCompany->name }}</strong></div>
            @endif
            <button class="btn btn-icon position-relative" type="button" aria-label="Notificaciones"><i class="bi bi-bell"></i></button>
            <div class="dropdown">
                <button class="btn user-menu d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="user-avatar">{{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="d-none d-md-block text-start"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></span>
                    <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>Mi perfil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button></form></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
