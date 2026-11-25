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
        mostrarPagos();

    }catch (error){
        alert("Error al cargar los pagos: " + error.message);
    }
    function mostrarPagos(){
        listaPagos.replaceChildren();
        if (pagos.length == 0){
            const fila = document.createElement("tr");
            const celda = document.createElement("td");
            celda.colSpan = esAdministrador ? 3 : 2;
            celda.className = "text-center py-4 text-muted";
            celda.textContent = "No hay pagos registrados";
            fila.append(celda);
            listaPagos.append(fila);
            return; 
        }

        pagos.forEach((pago)=>{
            const fila = document.createElement("tr");
            fila.append(
                crearCelda(pago.valorCuota === null? "Pendiente": Number(pago.valorCuota).toLocaleString("es-CO",{
                    style: "currency",
                    currency: "COP"}
                )),
                crearCelda(pago.fechaPago),
            );
        if(esAdministrador){
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
        }

        listaPagos.append(fila);
        })
    }

    function crearCelda(texto){
        const celda = document.createElement("td");
        celda.textContent = texto;
        return celda;
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
                if(prestamos.length === 0){
                    alert("No hay préstamos disponibles para registrar un pago.");
                    return;
                }
                formularioPago.reset();
                document.getElementById("idPago").value = "";
                cargarOpcionesPrestamos();
                document.getElementById("tituloModalPagos").textContent = "Nuevo Pago";
                document.getElementById("guardarPago").textContent = "Guardar";
                modalPago.show();
            });
        }
        accionesConfiguradas = true;
    }
}

function cargarOpcionesPrestamos(idSeleccionado = ""){
    const selectorPrestamo = document.getElementById("idPrestamo");
    selectorPrestamo.replaceChildren();

    const opcionInicial = document.createElement("option");
    opcionInicial.value = "";
    opcionInicial.textContent = "Selecciona un préstamo";
    selectorPrestamo.append(opcionInicial);

    prestamos.forEach((prestamo) => {
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
