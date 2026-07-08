<nav class="navbar navbar-light bg-white border-bottom px-4">
    <div class="ms-auto">
        @auth
            @php
                $userCompanies = auth()->user()
                    ->companies()
                    ->wherePivot('is_active', true)
                    ->get();

                $currentCompanyId = session('company_id');
            @endphp

            @if($userCompanies->count())
                <form action="{{ route('companies.switch') }}" method="POST" class="d-flex align-items-center gap-2">
                    @csrf

                    <label class="text-muted small mb-0">Empresa:</label>

                    <select name="company_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($userCompanies as $company)
                            <option value="{{ $company->id }}" @selected((int) $currentCompanyId === (int) $company->id)>
                                {{ $company->commercial_name ?? $company->business_name ?? $company->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
        @endauth
    </div>
</nav>