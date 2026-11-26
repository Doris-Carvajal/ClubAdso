const listaPagos =  document.getElementById("listaPagos");
let esAdministrador = false;
let pagos =[];
let prestamos = [];
let accionesConfiguradas = false;
const formularioPago = document.getElementById("formPagos");
const modalPago = new bootstrap.Modal(document.getElementById("modalPagos"),
);


async function solicitarApi(metodo, datos) {
    const opciones = {
        method: metodo,
        headers: {'Content-Type': 'application/json'}
    }

    if (datos){
        opciones.body = JSON.stringify(datos);
    }

    const respuesta = await fetch ("../Modelos/gestionarPagos.php", opciones);
    const texto = await respuesta.text();
    let resultado;
    try {
        resultado = JSON.parse(texto);
    } catch (error) {
        throw new Error(`El servidor devolvió una respuesta no válida (HTTP ${respuesta.status}).`);
    }

    if(respuesta.status === 401){
        window.location.href = "../Vistas/login.html";
        throw new Error(resultado.message || resultado.mensaje || "Inicia sesión para continuar.");
    }
    if(!respuesta.ok || resultado.success !== true){
        throw new Error (resultado.message || resultado.mensaje || "Error de la solicitud");
    }
    return resultado;
}

async function cargarPagos(){
    try{
        const resultado = await solicitarApi("GET");
        esAdministrador = resultado.rol === "admin";
        pagos = resultado.pagos;
        prestamos = resultado.prestamos;
        if(!Array.isArray(pagos)){
            throw new Error("No se pudieron cargar los pagos.");
        }
        if(!Array.isArray(prestamos)){
            throw new Error("No se pudieron cargar los préstamos disponibles.");
        }
        configurarAcciones();
        const encabezadoAcciones = document.getElementById("encabezadoAcciones");
        if (encabezadoAcciones) {
            encabezadoAcciones.classList.remove("d-none");
            encabezadoAcciones.textContent = esAdministrador ? "Acciones" : "Acción";
        }
        document.getElementById("tituloPagina").textContent = esAdministrador
            ? "Gestión de pagos del club"
            : "Mis pagos";
        document.getElementById("tituloHistorial").textContent = esAdministrador
            ? "Historial de pagos"
            : "Historial de mis pagos";
        mostrarPagos();

    }catch (error){
        alert("Error al cargar los pagos: " + error.message);
    }
    function mostrarPagos(){
        listaPagos.replaceChildren();
        const encabezadoValorPago = document.getElementById("encabezadoValorPago");
        const encabezadoFechaPago = document.getElementById("encabezadoFechaPago");
        if (encabezadoValorPago) {
            encabezadoValorPago.textContent = esAdministrador ? "Valor de cuota" : "Total pagado";
        }
        if (encabezadoFechaPago) {
            encabezadoFechaPago.textContent = esAdministrador ? "Fecha de pago" : "Último pago";
        }

        if ((esAdministrador && pagos.length === 0) || (!esAdministrador && prestamos.length === 0)) {
            const fila = document.createElement("tr");
            const celda = document.createElement("td");
            celda.colSpan = 6;
            celda.className = "text-center py-4 text-muted";
            celda.textContent = esAdministrador
                ? "No hay pagos registrados"
                : "No tienes préstamos registrados.";
            fila.append(celda);
            listaPagos.append(fila);
            return;
        }

        if (esAdministrador) {
            pagos.forEach((pago) => {
                const fila = crearFilaPago(pago);
                const prestamo = prestamos.find(
                    (elemento) => String(elemento.idPrestamo) === String(pago.idPrestamo)
                );
                fila.append(
                    crearCelda(prestamo
                        ? `${Number(prestamo.cuotasPagadas) || 0} de ${Number(prestamo.numeroCuotas)}`
                        : "—"
                    ),
                    crearCelda(prestamo ? formatearPesos(prestamo.valorPrestamo) : "—"),
                    crearCelda(formatearPesos(pago.valorCuota)),
                    crearCelda(pago.fechaPago)
                );

                const acciones = document.createElement("td");
                acciones.className = "text-nowrap";
                const botonEditar = document.createElement("button");
                botonEditar.type = "button";
                botonEditar.className = "btn btn-sm btn-outline-primary me-2";
                botonEditar.innerHTML = '<i class="bi bi-pencil-square"></i>';
                botonEditar.addEventListener("click", () => abrirEdicion(pago));

                const botonEliminar = document.createElement("button");
                botonEliminar.type = "button";
                botonEliminar.className = "btn btn-sm btn-outline-danger";
                botonEliminar.innerHTML = '<i class="bi bi-trash"></i>';
                botonEliminar.addEventListener("click", () => eliminarPago(pago));
                acciones.append(botonEditar, botonEliminar);
                fila.append(acciones);
                listaPagos.append(fila);
            });
            return;
        }

        const resumenesPorPrestamo = new Map();
        pagos.forEach((pago) => {
            const idPrestamo = String(pago.idPrestamo);
            const resumen = resumenesPorPrestamo.get(idPrestamo) || {
                totalPagado: 0,
                ultimoPago: ""
            };
            resumen.totalPagado += Number(pago.valorCuota) || 0;
            if (!resumen.ultimoPago || pago.fechaPago > resumen.ultimoPago) {
                resumen.ultimoPago = pago.fechaPago;
            }
            resumenesPorPrestamo.set(idPrestamo, resumen);
        });

        prestamos.forEach((prestamo) => {
            const resumen = resumenesPorPrestamo.get(String(prestamo.idPrestamo)) || {
                totalPagado: 0,
                ultimoPago: "Sin pagos registrados"
            };
            const fila = crearFilaPago({ idPrestamo: prestamo.idPrestamo });
            fila.append(
                crearCelda(`${Number(prestamo.cuotasPagadas) || 0} de ${Number(prestamo.numeroCuotas)}`),
                crearCelda(formatearPesos(prestamo.valorPrestamo)),
                crearCelda(formatearPesos(resumen.totalPagado)),
                crearCelda(resumen.ultimoPago || "Sin pagos registrados")
            );

            const acciones = document.createElement("td");
            const estaPendiente = esPrestamoPendiente(prestamo);
            const botonPagar = document.createElement("button");
            botonPagar.type = "button";
            botonPagar.className = estaPendiente
                ? "btn btn-sm btn-success"
                : "btn btn-sm btn-secondary";
            botonPagar.textContent = estaPendiente ? "Pagar cuota" : "Cuotas completas";
            botonPagar.disabled = !estaPendiente;
            if (estaPendiente) {
                botonPagar.addEventListener("click", () => abrirNuevoPago(prestamo));
            }
            acciones.append(botonPagar);
            fila.append(acciones);
            listaPagos.append(fila);
        });
    }

    function crearFilaPago(pago){
        const fila = document.createElement("tr");
        fila.append(crearCelda(`Préstamo #${pago.idPrestamo}`));
        return fila;
    }

    function crearCelda(texto){
        const celda = document.createElement("td");
        celda.textContent = texto;
        return celda;
    }

    function formatearPesos(valor){
        return Number(valor || 0).toLocaleString("es-CO", {
            style: "currency",
            currency: "COP"
        });
    }
}

async function eliminarPago(pago){
    const confirmar = window.confirm(`¿Deseas eliminar el pago del préstamo #${pago.idPrestamo}?`);

    if(!confirmar){
        return 
    }

    try{
        await solicitarApi("DELETE", {idPago : pago.idPago});
        await cargarPagos ();
        alert ("Se eliminó correctamente");
    }catch (error){
        alert(error.message);
    }
}

function configurarAcciones(){
    if (accionesConfiguradas){
        return;
    }
    const botonNuevo = document.getElementById("nuevaPago");
    const encabezadoAcciones = document.getElementById("encabezadoAcciones");

    if(esAdministrador){
        if(botonNuevo) botonNuevo.classList.remove("d-none");
        if(encabezadoAcciones) encabezadoAcciones.classList.remove("d-none");
        if(botonNuevo) {
            botonNuevo.addEventListener("click", () => {
                if(prestamos.filter(esPrestamoPendiente).length === 0){
                    alert("No hay préstamos pendientes de pago.");
                    return;
                }
                abrirNuevoPago();
            });
        }
        accionesConfiguradas = true;
    }
}

function esPrestamoPendiente(prestamo){
    return Number(prestamo.cuotasPagadas) < Number(prestamo.numeroCuotas);
}

function abrirNuevoPago(prestamoSeleccionado = null){
    formularioPago.reset();
    document.getElementById("idPago").value = "";
    cargarOpcionesPrestamos(prestamoSeleccionado?.idPrestamo || "");
    document.getElementById("idPrestamo").disabled = !esAdministrador;
    document.getElementById("tituloModalPagos").textContent = "Nuevo pago";
    document.getElementById("guardarPago").textContent = "Registrar pago";
    modalPago.show();
}

function cargarOpcionesPrestamos(idSeleccionado = ""){
    const selectorPrestamo = document.getElementById("idPrestamo");
    selectorPrestamo.replaceChildren();

    const opcionInicial = document.createElement("option");
    opcionInicial.value = "";
    opcionInicial.textContent = "Selecciona un préstamo pendiente";
    selectorPrestamo.append(opcionInicial);

    const prestamosDisponibles = prestamos.filter(esPrestamoPendiente);
    const seleccionado = prestamos.find(
        (prestamo) => String(prestamo.idPrestamo) === String(idSeleccionado)
    );
    if (seleccionado && !prestamosDisponibles.includes(seleccionado)) {
        prestamosDisponibles.push(seleccionado);
    }

    prestamosDisponibles.forEach((prestamo) => {
        const opcion = document.createElement("option");
        opcion.value = String(prestamo.idPrestamo);
        opcion.textContent = `Préstamo #${prestamo.idPrestamo} - Asociado #${prestamo.idAsociado} - ${Number(prestamo.valorPrestamo).toLocaleString("es-CO", {
            style: "currency",
            currency: "COP"
        })}`;
        selectorPrestamo.append(opcion);
    });

    selectorPrestamo.value = String(idSeleccionado);
}

function abrirEdicion(pago){
    formularioPago.reset();
    document.getElementById("idPago").value = pago.idPago;
    cargarOpcionesPrestamos(pago.idPrestamo);
    document.getElementById("idPrestamo").disabled = false;
    document.getElementById("valorCuota").value = pago.valorCuota;
    document.getElementById("fechaPago").value = pago.fechaPago;

    const tituloModal = document.getElementById("tituloModalPagos");
    const textoGuardar = document.getElementById("guardarPago");
    if (tituloModal) tituloModal.textContent = "Editar pago";
    if (textoGuardar) textoGuardar.textContent = "Guardar cambios";
    if (modalPago) modalPago.show();
}

formularioPago.addEventListener("submit", async (evento)=>{
    evento.preventDefault();
    const idPago = document.getElementById("idPago").value;

    const datos={
        idPrestamo: Number(document.getElementById("idPrestamo").value),
        valorCuota: document.getElementById("valorCuota").value,
        fechaPago: document.getElementById("fechaPago").value
    };

    if(idPago){
        datos.idPago = Number(idPago);
    }
    try{
        await solicitarApi(idPago ? "PUT" : "POST", datos);
        modalPago.hide();
        await cargarPagos();
        alert(idPago ? "Pago actualizado correctamente" : "Pago registrado correctamente");
    }catch(error){
        alert(error.message);
    }
});

cargarPagos();
