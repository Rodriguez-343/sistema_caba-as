export function caracteresUtiles(valor) {
    return (valor.match(/[\p{L}\p{N}]/gu) ?? []).length;
}

export class BuscadorHuesped {
    constructor(raiz, { solicitar = (...argumentos) => fetch(...argumentos), demora = 250 } = {}) {
        this.raiz = raiz;
        this.solicitar = solicitar;
        this.demora = demora;
        this.version = 0;
        this.resultados = [];
        this.indice = -1;
        this.elementos = {};
        for (const nombre of ['entrada', 'identificador', 'estado', 'resultados', 'seleccion', 'cambiar', 'reintentar', 'crear', 'buscar']) {
            this.elementos[nombre] = raiz.querySelector('[data-' + nombre + ']');
        }
        this.iniciarAlta();
        const { entrada, buscar, cambiar, reintentar } = this.elementos;
        entrada.addEventListener('input', () => this.editar());
        entrada.addEventListener('keydown', (evento) => this.teclado(evento));
        buscar.addEventListener('click', () => this.consultar());
        reintentar.addEventListener('click', () => this.consultar());
        cambiar.addEventListener('click', () => {
            entrada.value = '';
            this.editar();
            entrada.focus();
        });
        raiz.closest('form')?.addEventListener('submit', (evento) => {
            if (raiz.dataset.requerido === '1' && !this.elementos.identificador.value) {
                evento.preventDefault();
                entrada.setCustomValidity('Selecciona un cliente de los resultados.');
                entrada.reportValidity();
                entrada.focus();
            }
        });
    }

    iniciarAlta() {
        this.panelAlta = this.raiz.querySelector('[data-alta-huesped]');
        if (!this.panelAlta) return;
        this.abrirAlta = this.raiz.querySelector('[data-abrir-alta]');
        this.guardarAlta = this.panelAlta.querySelector('[data-guardar-alta]');
        this.cerrarAlta = this.panelAlta.querySelector('[data-cerrar-alta]');
        this.estadoAlta = this.panelAlta.querySelector('[data-alta-estado]');
        this.camposAlta = ['nombre', 'telefono', 'identidad'].map(nombre => this.panelAlta.querySelector('[data-alta-' + nombre + ']'));
        this.abrirAlta.addEventListener('click', () => {
            this.mostrarAlta(true);
            this.camposAlta[0].focus();
        });
        this.cerrarAlta.addEventListener('click', () => {
            this.mostrarAlta(false);
            this.abrirAlta.focus();
        });
        this.guardarAlta.addEventListener('click', () => this.crearHuesped());
        this.panelAlta.addEventListener('keydown', evento => {
            if (evento.key === 'Enter' && this.camposAlta.includes(evento.target)) {
                evento.preventDefault();
                this.crearHuesped();
            }
        });
    }

    mostrarAlta(abierta) {
        this.panelAlta.hidden = !abierta;
        this.abrirAlta.setAttribute('aria-expanded', String(abierta));
        this.camposAlta.forEach(campo => { campo.disabled = !abierta; });
    }

    async crearHuesped() {
        if (this.creandoHuesped || this.panelAlta.hidden) return;
        for (const campo of this.camposAlta) {
            if (!campo.reportValidity()) return;
        }
        this.creandoHuesped = true;
        this.guardarAlta.disabled = true;
        this.cerrarAlta.disabled = true;
        this.estadoAlta.textContent = 'Creando huésped…';
        const [nombre, telefono, identidad] = this.camposAlta.map(campo => campo.value);
        try {
            const respuesta = await this.solicitar(this.panelAlta.dataset.url, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: {
                    Accept: 'application/json', 'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.raiz.ownerDocument.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ nombre, telefono, numero_identidad: identidad || null }),
            });
            if (respuesta.status === 422) {
                const datos = await respuesta.json();
                this.estadoAlta.textContent = Object.values(datos.errors ?? {}).flat().join(' ') || 'Revisa los datos del huésped.';
                return;
            }
            if (!respuesta.ok) throw new Error('Alta rechazada');
            const datos = await respuesta.json();
            if (!datos.huesped?.id) throw new Error('Respuesta inválida');
            this.cancelar();
            this.seleccionar(datos.huesped, this.version);
            this.mostrarAlta(false);
            this.camposAlta.forEach(campo => { campo.value = ''; });
            this.estadoAlta.textContent = '';
        } catch {
            this.estadoAlta.textContent = 'No se pudo completar el alta. Comprueba tu acceso y conexión; busca el huésped antes de reintentar para evitar duplicarlo.';
        } finally {
            this.creandoHuesped = false;
            this.guardarAlta.disabled = false;
            this.cerrarAlta.disabled = false;
        }
    }
    cancelar() {
        clearTimeout(this.temporizador);
        this.controlador?.abort();
        return ++this.version;
    }

    limpiarResultados() {
        this.resultados = [];
        this.indice = -1;
        this.elementos.resultados.replaceChildren();
        this.elementos.resultados.hidden = true;
        this.elementos.entrada.setAttribute('aria-expanded', 'false');
        this.elementos.entrada.removeAttribute('aria-activedescendant');
    }

    estado(texto, error = false, sinResultados = false) {
        this.elementos.estado.textContent = texto;
        this.elementos.reintentar.hidden = !error;
        if (this.elementos.crear) this.elementos.crear.hidden = !sinResultados;
    }

    editar() {
        this.cancelar();
        const { entrada, identificador, seleccion, cambiar } = this.elementos;
        identificador.value = '';
        entrada.setCustomValidity('');
        seleccion.textContent = '';
        cambiar.hidden = true;
        this.limpiarResultados();
        if (caracteresUtiles(entrada.value) < 3) {
            this.estado('Escribe al menos tres letras o números para buscar.');
            return;
        }
        this.estado('Preparando búsqueda…');
        this.temporizador = setTimeout(() => this.consultar(), this.demora);
    }

    async consultar() {
        const version = this.cancelar();
        const termino = this.elementos.entrada.value;
        // No invalidar una selección por pulsar Buscar: sí se invalida al editar el texto.
        this.limpiarResultados();
        if (caracteresUtiles(termino) < 3) {
            this.estado('Escribe al menos tres letras o números para buscar.');
            return;
        }
        this.controlador = new AbortController();
        this.estado('Buscando clientes…');
        try {
            const respuesta = await this.solicitar(this.raiz.dataset.url + '?' + new URLSearchParams({ termino_busqueda: termino }), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: this.controlador.signal,
            });
            if (!respuesta.ok) throw new Error('Consulta rechazada');
            const datos = await respuesta.json();
            if (version !== this.version) return;
            if (!Array.isArray(datos.resultados)) throw new Error('Respuesta inválida');
            this.resultados = datos.resultados;
            this.indice = -1;
            const botones = datos.resultados.map((huesped, posicion) => {
                const boton = this.raiz.ownerDocument.createElement('button');
                boton.type = 'button';
                boton.className = 'list-group-item list-group-item-action';
                boton.id = this.raiz.id + '-resultado-' + posicion;
                boton.setAttribute('role', 'option');
                boton.setAttribute('aria-selected', 'false');
                boton.tabIndex = -1;
                boton.textContent = this.resumen(huesped);
                boton.addEventListener('click', () => this.seleccionar(huesped, version));
                return boton;
            });
            this.elementos.resultados.replaceChildren(...botones);
            this.elementos.resultados.hidden = botones.length === 0;
            this.elementos.entrada.setAttribute('aria-expanded', String(botones.length > 0));
            this.estado(botones.length === 0 ? 'No se encontraron clientes' :
                (botones.length === 1 ? '1 coincidencia. Selecciona el cliente.' : botones.length + ' coincidencias. Selecciona un cliente.') + (datos.hay_mas ? ' Hay más resultados; refina la búsqueda.' : ''), false, botones.length === 0);
        } catch (error) {
            if (version !== this.version || error.name === 'AbortError') return;
            this.limpiarResultados();
            this.estado('No se pudo consultar. Comprueba tu conexión y acceso e inténtalo de nuevo.', true);
        }
    }

    resumen(huesped) {
        return huesped.nombre + ' · Teléfono: ' + (huesped.telefono || 'Sin registrar') +
            ' · Identidad: ' + (huesped.identidad_visible ? (huesped.numero_identidad || 'Sin registrar') : 'Restringida') +
            (!huesped.activo ? ' · Advertencia: inactivo' : '') +
            (huesped.lista_negra ? ' · Advertencia: lista negra' : '');
    }

    seleccionar(huesped, version) {
        if (version !== this.version) return;
        this.cancelar();
        this.elementos.identificador.value = String(huesped.id);
        this.elementos.entrada.value = huesped.nombre;
        this.elementos.entrada.setCustomValidity('');
        this.elementos.seleccion.textContent = 'Cliente seleccionado: ' + this.resumen(huesped);
        this.elementos.cambiar.hidden = false;
        this.limpiarResultados();
        this.estado('Cliente seleccionado.');
        this.elementos.entrada.focus();
        this.raiz.dispatchEvent(new CustomEvent('huesped-seleccionado', { bubbles: true, detail: { huesped_id: huesped.id } }));
    }

    teclado(evento) {
        if (evento.key === 'Escape') {
            this.cancelar();
            this.limpiarResultados();
            this.estado(this.elementos.identificador.value ? 'Cliente seleccionado.' : 'Búsqueda cerrada. Puedes volver a buscar.');
            return;
        }
        if (evento.key === 'Enter') {
            if (this.indice >= 0) {
                evento.preventDefault();
                this.seleccionar(this.resultados[this.indice], this.version);
            } else if (!this.elementos.identificador.value) {
                evento.preventDefault();
            }
            return;
        }
        if (!['ArrowDown', 'ArrowUp'].includes(evento.key) || this.resultados.length === 0) return;
        evento.preventDefault();
        this.indice = evento.key === 'ArrowDown'
            ? (this.indice + 1) % this.resultados.length
            : (this.indice <= 0 ? this.resultados.length - 1 : this.indice - 1);
        [...this.elementos.resultados.children].forEach((boton, indice) => {
            const activo = indice === this.indice;
            boton.setAttribute('aria-selected', String(activo));
            boton.classList.toggle('active', activo);
            if (activo) this.elementos.entrada.setAttribute('aria-activedescendant', boton.id);
        });
    }
}

export function iniciarBuscadores() {
    document.querySelectorAll('[data-buscador-huesped]').forEach((raiz) => new BuscadorHuesped(raiz));
}
