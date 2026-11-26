const listaPrestamos = document.getElementById("listaPrestamos");
let esAdministrador = false;
let prestamos= [];
let accionesConfiguradas = false;
const formularioPrestamo = document.getElementById("formPrestamos");
const modalPrestamos = new bootstrap.Modal(document.getElementById("modalPrestamos"),
);

async function solicitarApi(metodo, datos){
    const opciones = {
        method: metodo,
        headers: {'Content-Type': 'application/json'}
    }

    if(datos){
        opciones.body = JSON.stringify(datos);
    }

    const respuesta = await fetch("../Modelos/gestionarPrestamos.php", opciones);
    const texto = await respuesta.text();
    let resultado;
    try {
        resultado = JSON.parse(texto);
    } catch (error) {
        throw new Error(`El servidor devolvió una respuesta no válida (HTTP ${respuesta.status}).`);
    }

    if(respuesta.status === 401){
        window.location.href = "../Vistas/login.html";
        throw new Error(resultado.message || "Inicia sesión para continuar.");
    }
    if(!respuesta.ok || resultado.success !== true){
        throw new Error (resultado.message || resultado.mensaje || "Error en la solicitud");
    }
    return resultado;
}

async function cargarPrestamos() {
    try{
        const  resultado = await solicitarApi ("GET");
        esAdministrador =resultado.rol === "admin";
        if (esAdministrador) {
            mostrarAsociados(resultado.asociados);
        }
        configurarAcciones();
        prestamos = resultado.prestamos;
        mostrarPrestamos();

    }catch (error){
        alert ("Error al cargar los prestamos: " + error.message);
    }

    function mostrarPrestamos(){
        listaPrestamos.replaceChildren();
        if (prestamos.length === 0){
            const fila = document.createElement("tr");
            const celda = document.createElement("td");
            celda.colSpan = esAdministrador ? 5 : 4;
            celda.className="text-center py-4 text-muted";
            celda.textContent = "No hay actividades disponibles. ";
            fila.append(celda);
            listaPrestamos.append(fila);
            return;
        }

        prestamos.forEach((prestamo) =>{
            const fila = document.createElement("tr");
            fila.append(
                crearCelda(Number(prestamo.valorPrestamo).toLocaleString("es-CO", {
                    maximumFractionDigits: 0
                })),
                crearCelda(`${Number(prestamo.tasaInteres).toLocaleString("es-CO", {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 2
                })} %`),
                crearCelda (prestamo.fechaPrestamo),
                crearCelda (prestamo.numeroCuotas === null ? "Pendiente" : Number(prestamo.numeroCuotas).toLocaleString("es-CO")),
        );

        if(esAdministrador)
        {
            const acciones = document.createElement("td");
            acciones.className="text-nowrap";
            const botonEditar = document.createElement("button");
            botonEditar.type="button";
            botonEditar.className = "btn btn-sm btn-outline-primary me-2";
            botonEditar.innerHTML ='<i class="bi bi-pencil-square"></i>';
            botonEditar.addEventListener("click",()=>abrirEdicion(prestamo));

            const botonEliminar = document.createElement("button");
            botonEliminar.type="button";
            botonEliminar.className = "btn btn-sm btn-outline-danger";
            botonEliminar.innerHTML='<i class="bi bi-trash"></i>';
            botonEliminar.addEventListener("click",()=>eliminarPrestamo(prestamo));

            acciones.append(botonEditar,botonEliminar);
            fila.append(acciones);
        }
        listaPrestamos.append(fila);
        });
    }
    function crearCelda(texto){
        const celda = document.createElement("td");
        celda.textContent=texto;
        return celda;
    }
}

function mostrarAsociados(asociados) {
    if (!Array.isArray(asociados)) {
        throw new Error("No se pudo cargar la lista de asociados.");
    }

    const selector = document.getElementById("idAsociado");
    selector.replaceChildren();

    const opcionInicial = document.createElement("option");
    opcionInicial.value = "";
    opcionInicial.textContent = asociados.length
        ? "Selecciona un asociado"
        : "No hay asociados registrados";
    selector.append(opcionInicial);

    asociados.forEach((asociado) => {
        const opcion = document.createElement("option");
        opcion.value = asociado.idAsociado;
        opcion.textContent = `${asociado.nombres} ${asociado.apellidos} (${asociado.documento})`;
        selector.append(opcion);
    });
}

async function eliminarPrestamo(prestamo){
    const confirmar = window.confirm(`¿Desea eliminar el prestamo "${prestamo.valorPrestamo}"?`)

    if(!confirmar){
        return;
    }

    try{
        await solicitarApi("DELETE",{idPrestamo:prestamo.idPrestamo});
        await cargarPrestamos();
        alert("Se elimino correctamente");
    }catch(error){
        alert(error.message);
    }
}

function configurarAcciones(){
    if(accionesConfiguradas){
        return;
    }
    const botonNueva=document.getElementById("nuevoPrestamo");
    const encabezadoAcciones=document.getElementById("encabezadoAcciones");

    if(esAdministrador){
        botonNueva.classList.remove("d-none");
        encabezadoAcciones.classList.remove("d-none");
        botonNueva.addEventListener("click", () => {
            formularioPrestamo.reset();
            document.getElementById("idPrestamo").value = "";
            document.getElementById("idAsociado").disabled = false;
            document.getElementById("tituloModalPrestamos").textContent = "Nuevo préstamo";
            document.getElementById("guardarPrestamo").textContent = "Guardar";
        });
        accionesConfiguradas=true;
        return;
    }
}

function abrirEdicion(prestamo){
    formularioPrestamo.reset();
    document.getElementById("idPrestamo").value=prestamo.idPrestamo;
    document.getElementById("valorPrestamo").value=Number(prestamo.valorPrestamo);
    document.getElementById("tasaInteres").value=prestamo.tasaInteres;
    document.getElementById("fechaPrestamo").value=prestamo.fechaPrestamo;
    document.getElementById("numeroCuotas").value=prestamo.numeroCuotas ?? "";
    document.getElementById("idAsociado").value=prestamo.idAsociado;
    document.getElementById("idAsociado").disabled=true;


    document.getElementById("tituloModalPrestamos").textContent="Editar prestamos";
    document.getElementById("guardarPrestamo").textContent="Guardar cambios";
    modalPrestamos.show();
}

formularioPrestamo.addEventListener("submit", async(evento)=>{
    evento.preventDefault();
    const idPrestamo=document.getElementById("idPrestamo").value;

    const datos ={
        idAsociado: Number(document.getElementById("idAsociado").value),
        valorPrestamo: Number (document.getElementById("valorPrestamo").value),
        tasaInteres: Number (document.getElementById("tasaInteres").value),
        fechaPrestamo:document.getElementById("fechaPrestamo").value,
        numeroCuotas: Number(document.getElementById("numeroCuotas").value)
    };

    if(idPrestamo){
        datos.idPrestamo = Number(idPrestamo);
    }
    try{
        await solicitarApi(idPrestamo ? "PUT" : "POST", datos);
        modalPrestamos.hide();
        await cargarPrestamos();
        alert(idPrestamo ? "Préstamo actualizado correctamente" : "Préstamo guardado correctamente");
    }catch(error){
        alert(error.message);
    }
});

cargarPrestamos();