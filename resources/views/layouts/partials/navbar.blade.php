<nav class="navbar navbar-expand-lg bg-white border-bottom shadow-sm">

    <div class="container-fluid">

        <span class="navbar-brand fw-bold">
            ERP Conta
        </span>

        <div class="ms-auto d-flex align-items-center">

            <span class="me-3">

                {{ auth()->user()->name }}

            </span>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button class="btn btn-outline-danger btn-sm">
                    Cerrar sesión
                </button>

            </form>

        </div>

    </div>

</nav>