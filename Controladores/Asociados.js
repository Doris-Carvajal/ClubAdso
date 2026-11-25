const listaAsociados = document.getElementById("listaAsociados");
const formularioAsociado = document.getElementById("formAsociados");
const elementoModalAsociado = document.getElementById("modalAsociados");
const modalAsociado = elementoModalAsociado
    ? new bootstrap.Modal(elementoModalAsociado)
    : null;
const botonNuevoAsociado = document.getElementById("nuevoAsociado");
const formularioRegistro = document.getElementById("formRegistro");
let asociados = [];
let esAdministrador=false;
let accionesConfiguradas = false;

async function solicitarApi(url, opciones = {}) {
    const respuesta = await fetch(url, opciones);
    const texto = await respuesta.text();
    let resultado;

    try {
        resultado = JSON.parse(texto);
    } catch (error) {
        throw new Error(`El servidor devolvió una respuesta no válida (HTTP ${respuesta.status}).`);
    }

    if (respuesta.status === 401) {
        window.location.href = "../Vistas/login.html";
        throw new Error(resultado.message || "Inicia sesión para continuar.");
    }

    if (!respuesta.ok || resultado.success !== true) {
        throw new Error(resultado.message || resultado.mensaje || "Error en la solicitud.");
    }

    return resultado;
}

async function cargarAsociados() {
    if (!listaAsociados) {
        return;
    }

    try {
        const resultado = await solicitarApi("../Modelos/gestionarAsociados.php");
        asociados = resultado.asociados;

        if (!Array.isArray(asociados)) {
            throw new Error("La respuesta no contiene una lista de asociados.");
        }

        esAdministrador = resultado.rol === "admin";
        botonNuevoAsociado.classList.toggle("d-none", resultado.rol !== "admin");
        document.getElementById("encabezadoAcciones").classList.toggle("d-none", !esAdministrador);
        configurarAcciones();
        mostrarAsociados();
    } catch (error) {
        mostrarEstado(error.message);
    }
}

function mostrarAsociados() {
    if (!listaAsociados) {
        return;
    }

    listaAsociados.replaceChildren();

    if (asociados.length === 0) {
        mostrarEstado("No hay asociados registrados.");
        return;
    }

    asociados.forEach((asociado) => {
        const fila = document.createElement("tr");
        [
            asociado.documento,
            asociado.nombres,
            asociado.apellidos,
            asociado.fechaNacimiento,
            asociado.ciudad,
            asociado.telefono
        ].forEach((valor) => {
            const celda = document.createElement("td");
            celda.textContent = valor ?? "";
            fila.append(celda);
        });

        if (esAdministrador) {
            const acciones = document.createElement("td");
            acciones.className = "text-nowrap";

            const botonEditar = document.createElement("button");
            botonEditar.type = "button";
            botonEditar.className = "btn btn-sm btn-outline-primary me-2";
            botonEditar.innerHTML = '<i class="bi bi-pencil-square"></i>';
            botonEditar.setAttribute("aria-label", `Editar asociado ${asociado.documento}`);
            botonEditar.addEventListener("click", () => abrirEdicion(asociado));

            const botonEliminar = document.createElement("button");
            botonEliminar.type = "button";
            botonEliminar.className = "btn btn-sm btn-outline-danger";
            botonEliminar.innerHTML = '<i class="bi bi-trash"></i>';
            botonEliminar.setAttribute("aria-label", `Eliminar asociado ${asociado.documento}`);
            botonEliminar.addEventListener("click", () => eliminarAsociado(asociado));

            acciones.append(botonEditar, botonEliminar);
            fila.append(acciones);
        }

        listaAsociados.append(fila);
    });
}

function mostrarEstado(mensaje) {
    if (!listaAsociados) {
        return;
    }

    const fila = document.createElement("tr");
    const celda = document.createElement("td");
    celda.colSpan = esAdministrador ? 7 : 6;
    celda.className = "text-center py-4";
    celda.textContent = mensaje;
    fila.append(celda);
    listaAsociados.replaceChildren(fila);
}

function configurarAcciones() {
    if (!esAdministrador || accionesConfiguradas) {
        return;
    }

    botonNuevoAsociado.addEventListener("click", () => {
        formularioAsociado.reset();
        document.getElementById("idAsociado").value = "";
        document.getElementById("contraseña").required = true;
        document.getElementById("tituloModalAsociados").textContent = "Nuevo asociado";
        document.getElementById("guardarAsociado").textContent = "Guardar";
    });
    accionesConfiguradas = true;
}

function abrirEdicion(asociado) {
    formularioAsociado.reset();
    document.getElementById("idAsociado").value = asociado.idAsociado;
    document.getElementById("documento").value = asociado.documento;
    document.getElementById("nombres").value = asociado.nombres;
    document.getElementById("apellidos").value = asociado.apellidos;
    document.getElementById("fechaNacimiento").value = asociado.fechaNacimiento;
    document.getElementById("genero").value = asociado.genero;
    document.getElementById("direccionResidencia").value = asociado.direccionResidencia;
    document.getElementById("ciudad").value = asociado.ciudad;
    document.getElementById("telefono").value = asociado.telefono;
    document.getElementById("email").value = asociado.email;
    document.getElementById("rol").value = asociado.rol;
    document.getElementById("contraseña").required = false;
    document.getElementById("contraseña").value = "";
    document.getElementById("contraseña").placeholder = "Dejar en blanco para conservar la actual";
    document.getElementById("tituloModalAsociados").textContent = "Editar asociado";
    document.getElementById("guardarAsociado").textContent = "Guardar cambios";
    modalAsociado.show();
}

async function eliminarAsociado(asociado) {
    const confirmar = window.confirm(
        `¿Desea eliminar al asociado "${asociado.nombres} ${asociado.apellidos}"?`
    );

    if (!confirmar) {
        return;
    }

    try {
        await solicitarApi("../Modelos/gestionarAsociados.php", {
            method: "DELETE",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ idAsociado: asociado.idAsociado })
        });
        await cargarAsociados();
        alert("Asociado eliminado correctamente.");
    } catch (error) {
        alert(error.message);
    }
}

if (formularioAsociado) {
    formularioAsociado.addEventListener("reset", () => {
        document.getElementById("contraseña").required = true;
        document.getElementById("contraseña").placeholder = "Ingresa la contraseña";
    });

    formularioAsociado.addEventListener("submit", async (evento) => {
        evento.preventDefault();
        const botonGuardar = document.getElementById("guardarAsociado");
        const datos = Object.fromEntries(new FormData(formularioAsociado).entries());
        const idAsociado = datos.idAsociado;
        if (!idAsociado) {
            delete datos.idAsociado;
        } else if (!datos.contraseña) {
            delete datos.contraseña;
        }

        botonGuardar.disabled = true;
        try {
            const resultado = await solicitarApi(
                idAsociado ? "../Modelos/gestionarAsociados.php" : formularioAsociado.action,
                {
                method: idAsociado ? "PUT" : "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            });
            formularioAsociado.reset();
            modalAsociado?.hide();
            await cargarAsociados();
            alert(resultado.mensaje || (idAsociado
                ? "Asociado actualizado correctamente."
                : "Asociado guardado correctamente."));
        } catch (error) {
            alert(error.message);
        } finally {
            botonGuardar.disabled = false;
            document.getElementById("contraseña").required = true;
            document.getElementById("contraseña").placeholder = "Ingresa la contraseña";
        }
    });

    cargarAsociados();
}

if (formularioRegistro) {
    formularioRegistro.addEventListener("submit", async (evento) => {
        evento.preventDefault();

        const botonGuardar = formularioRegistro.querySelector('button[type="submit"]');
        const mensajeRegistro = document.getElementById("mensajeRegistro");
        const asociado = Object.fromEntries(new FormData(formularioRegistro).entries());

        if (asociado.contraseña !== asociado.confirmarContraseña) {
            mensajeRegistro.textContent = "Las contraseñas no coinciden.";
            return;
        }

        delete asociado.confirmarContraseña;

        botonGuardar.disabled = true;
        try {
            const resultado = await solicitarApi(formularioRegistro.action, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(asociado)
            });

            mensajeRegistro.textContent = resultado.mensaje || "Asociado guardado correctamente.";
            formularioRegistro.reset();
        } catch (error) {
            console.error("Error al enviar el formulario:", error);
            mensajeRegistro.textContent = error.message || "Ocurrió un error al enviar el formulario.";
        } finally {
            botonGuardar.disabled = false;
        }
    });
}
