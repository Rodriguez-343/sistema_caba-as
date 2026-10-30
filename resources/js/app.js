import '@fontsource-variable/figtree';
import '@fontsource-variable/bitter';
import 'bootstrap';
import { iniciarBuscadores } from './buscador-huesped';

iniciarBuscadores();

document.querySelector('[data-resumen-errores]')?.focus();

document.querySelectorAll('form[data-confirmar]').forEach((formulario) => {
    formulario.addEventListener('submit', (evento) => {
        if (!window.confirm(formulario.dataset.confirmar)) {
            evento.preventDefault();
        }
    });
});
