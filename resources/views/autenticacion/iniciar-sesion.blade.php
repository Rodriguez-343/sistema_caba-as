@extends('layouts.base')
@section('titulo', 'Iniciar sesión')
@section('pagina')
<x-acceso>
    <h1 class="h2 mb-2">Iniciar sesión</h1>
    <p class="text-body-secondary mb-4">Ingresa con tu cuenta de personal.</p>
    <x-mensajes />
    <form method="POST" action="{{ route('sesion.iniciar') }}">
        @csrf
        <div class="mb-3">
            <label for="usuario" class="form-label">Usuario</label>
            <input id="usuario" name="usuario" type="text" class="form-control @error('usuario') is-invalid @enderror"
                value="{{ is_string(old('usuario')) ? old('usuario') : '' }}" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="100" required
                @error('usuario') aria-invalid="true" aria-describedby="error-usuario" @enderror>
            @error('usuario') <div id="error-usuario" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label for="contrasena" class="form-label">Contraseña</label>
            <input id="contrasena" name="contrasena" type="password" class="form-control @error('contrasena') is-invalid @enderror"
                autocomplete="current-password" maxlength="1024" required
                @error('contrasena') aria-invalid="true" aria-describedby="error-contrasena" @enderror>
            @error('contrasena') <div id="error-contrasena" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="form-check mb-4">
            <input id="recordar" name="recordar" type="checkbox" class="form-check-input" value="1" @checked(old('recordar'))>
            <label class="form-check-label" for="recordar">Recordarme en este dispositivo</label>
        </div>
        <button class="btn btn-primary w-100 py-2" type="submit">Iniciar sesión</button>
    </form>
</x-acceso>
@endsection
