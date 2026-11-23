const listaPagos =  document.getElementById("listaPagos");
let esAdministrador = false;
let pagos =[];
let accionesConfiguradas = false;
const formularioPago = document.getElementById("formPagos");
const modalPago = new bootstrap.Modal(document.getElementById("modalPagos"),
);


async function solicitarApi(metodo, datos) {
    const opciones = {
        method: metodo,
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(datos)
    }

    if (datos){
        opciones.body = JSON.stringify(datos);
    }

    const respuesta = await fetch ("../Modelos/gestionarPagos.php", opciones);
    const resultado = await respuesta.json();
    if(respuesta.status === 401){
        window.location.href = "../Vistas/login.html";
    }
    if(!respuesta.ok){
        throw new Error (resultado.message || resultado.mensaje || "Error de la solicitud");
    }
    return resultado;
}

async function cargarPagos(){
    try{
        const resultado = await solicitarApi("GET");
        esAdministrador = resultado.rol === "admin";
        configurarAcciones();
        pagos = resultado.pagos;
        mostrarPagos();

    }catch (error){
        alert("Error al cargar los pagos: " + error.message);
    }
    function mostrarPagos(){
        listaPagos.replaceChildren();
        if (pagos.length == 0){
            const fila = document.createElement("tr");
            const celda = document.createElement("td");
            celda.colSpan = esAdministrador ? 4 : 3;
            celda.className = "text-center py-4 text-muted";
            celda.textContent = "No hay pagos registrados";
            fila.append(celda);
            listaPagos.append(fila);
            return; 
        }

        pagos.forEach((pago)=>{
            const fila = document.createElement("tr");
            fila.append(
                crearCelda(pago.idPrestamo),
                crearCelda(pago.valorCuota === null? "Pendiente": Number(pago.valorCuota).toLocaleString("es-CO",{
                    style: "currency",
                    currency: "COP"}
                )),
            );
        if(esAdministrador){
            const acciones = document.createElement("td");
            acciones.className = "text-nowrap";

            const botonEditar = document.createElement("button");
            botonEditar.type = "button";
            botonEditar.className = "btn btn-sm btn-outline-primary me-2";
            botonEditar.innerHTML = '<i class="bi bi-trash"></i>';
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
        accionesConfiguradas = true;
    }
}

function abrirEdicion(pago){
    formularioPago.reset();
    document.getElementById("idPago").value = pago.idPago;
    document.getElementById("idPrestamo").value = pago.idPrestamo;
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
        idPrestamo: document.getElementById("idPrestamo").value,
        valorCuota: document.getElementById("valorCuota").value,
        fechaPago: document.getElementById("fechaPago").value
    };

    if(idPago){
        datos.idPago = Number(idPago);
    }
    try{
        await solicitarApi(idPago ? "PUT" : "POST", datos);
        modalPago.hide();
        alert("Pago actualizado correctamente");
        cargarPagos();
    }catch(error){
        alert(error.message);
    }
});

