const listaActividades = document.getElementById("listaActividades");
let esAdministrador = false;
let actividades = [];
let accionesConfiguradas = false;
const formularioActividad = document.getElementById("formActividad");
const modalActividad = new bootstrap.Modal(document.getElementById("modalActividad"),
);
//Permite controlar una ventana modal de bootstrap, busca el modal en el HTML, 
//guarda el ojeto que permite mostrarlo u ocultarlo.


async function solicitarApi(metodo, datos) {// Se encarga de ser asincrona, recibe el tipo de solicitud HTTP, recibe la informacion que se enviara al servidor.
    const opciones = {
        method: metodo, 
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos)
    }

    if (datos){// Comprueba si la variable datos contiene informacion
        opciones.body = JSON.stringify(datos); 
    }

    const respuesta = await fetch("../Modelos/gestionarActividades.php", opciones);
    const texto = await respuesta.text();
    let resultado;
    try {
        resultado = JSON.parse(texto);
    } catch (error) {
        throw new Error(`El servidor devolvió una respuesta no válida (HTTP ${respuesta.status}).`);
    }

    if (respuesta.status === 401) {// comprueba si el servidor devolvió si la solicitud no esta autenticada.
        window.location.href = "../Vistas/login.html";
        throw new Error(resultado.message || "Inicia sesión para continuar.");
        //El operador || seginifica que se utiliza el valor de la izquierda si existe, de lo contrario, se utiliza el de la derecha.
    }
    if (!respuesta.ok || resultado.success !== true) {
        throw new Error(resultado.message || resultado.mensaje || "Error en la solicitud");
    }

    return resultado; 

    //solicitarApi esta funcion es la encargada de enviar solicitudes al servidor,
    //recibir respuestas,convertirlas a JSON y controlar los errores
}
//Carga la actividad
async function cargarActividades() {
    try {
        const resultado = await solicitarApi("GET");
        esAdministrador = resultado.rol === "admin";
        configurarAcciones();
        actividades = resultado.actividades;
        mostrarActividades();
    } catch (error) {
        alert("Error al cargar las actividades: " + error.message);
    }
}

// Es la funcon encargada de mostrar las actividades y las acciones,
//dependiendo del rol , en este caso admin

function mostrarActividades() {
    listaActividades.replaceChildren();

    if (actividades.length === 0) {
        const fila = document.createElement("tr");
        const celda = document.createElement("td");
        celda.colSpan = 9;
        celda.className = "text-center py-4 text-muted";
        celda.textContent = "No hay actividades disponibles.";
        fila.append(celda);
        listaActividades.append(fila);
        return;
    }

    actividades.forEach((actividad) => {
        const fila = document.createElement("tr");
        fila.append(
            crearCelda(actividad.nombreActividad),
            crearCelda(actividad.fechaEvento),
            crearCelda(actividad.municipio),
            crearCelda(actividad.totalRecaudado === null
                ? "Pendiente"
                : formatearPesos(actividad.totalRecaudado)),
            crearRecaudoAsociados(actividad),
            crearCelda(actividad.finalizada
                ? formatearPesos(actividad.fondoClub)
                : "Pendiente"),
            crearCelda(actividad.numeroParticipantes),
            crearCeldaEstado(actividad),
            esAdministrador
                ? crearAccionesAdministrador(actividad)
                : crearAccionesAsociado(actividad)
        );
        listaActividades.append(fila);
    });
}

function crearCelda(texto) {
    const celda = document.createElement("td");
    celda.textContent = texto;
    return celda;
}

function crearCeldaEstado(actividad) {
    const celda = document.createElement("td");
    const estado = document.createElement("span");
    estado.className = actividad.estado === "Finalizada"
        ? "badge text-bg-secondary"
        : "badge text-bg-success";
    estado.textContent = actividad.estado;

    celda.append(estado);
    return celda;
}

function crearRecaudoAsociados(actividad) {
    const celda = document.createElement("td");
    const participantes = Number(actividad.numeroParticipantes);
    if (!actividad.finalizada) {
        celda.textContent = "Pendiente";
        return celda;
    }

    celda.textContent = formatearPesos(actividad.bolsaAsociados);
    if (participantes > 0) {
        const valorIndividual = !esAdministrador && actividad.participa
            ? `Tu parte: ${formatearPesos(actividad.valorPorAsociado)}`
            : `Por asociado: ${formatearPesos(actividad.valorPorAsociado)}`;
        const detalle = document.createElement("div");
        detalle.textContent = valorIndividual;
        celda.append(detalle);
    }
    return celda;
}

function formatearPesos(valor) {
    return new Intl.NumberFormat("es-CO", {
        style: "currency",
        currency: "COP",
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    }).format(Number(valor));
}

function crearAccionesAdministrador(actividad) {
    const acciones = document.createElement("td");
    acciones.className = "text-nowrap";

    const botonEditar = document.createElement("button");
    botonEditar.type = "button";
    botonEditar.className = "btn btn-sm btn-outline-primary me-2";
    botonEditar.setAttribute("aria-label", "Editar actividad");
    botonEditar.innerHTML = '<i class="bi bi-pencil-square"></i>';
    botonEditar.addEventListener("click", () => abrirEdicion(actividad));

    const botonEliminar = document.createElement("button");
    botonEliminar.type = "button";
    botonEliminar.className = "btn btn-sm btn-outline-danger";
    botonEliminar.setAttribute("aria-label", "Eliminar actividad");
    botonEliminar.innerHTML = '<i class="bi bi-trash"></i>';
    botonEliminar.addEventListener("click", () => eliminarActividad(actividad));

    acciones.append(botonEditar, botonEliminar);
    return acciones;
}

function crearAccionesAsociado(actividad) {
    const acciones = document.createElement("td");
    acciones.className = "text-nowrap";

    if (!actividad.permiteInscripcion) {
        acciones.textContent = actividad.participa ? "Participaste" : "Inscripción cerrada";
        return acciones;
    }
    if (actividad.participa && !actividad.permiteRetiro) {
        acciones.textContent = "Inscrito";
        return acciones;
    }

    const boton = document.createElement("button");
    boton.type = "button";
    boton.className = actividad.participa
        ? "btn btn-sm btn-outline-danger"
        : "btn btn-sm btn-outline-success";
    boton.textContent = actividad.participa ? "Cancelar inscripción" : "Participar";
    boton.addEventListener("click", () => gestionarParticipacion(actividad));
    acciones.append(boton);
    return acciones;
}

async function eliminarActividad(actividad){
    const confirmar = window.confirm(`¿Desea eliminar la actividad "${actividad.nombreActividad}"?`); 

    if(!confirmar){
        return;
    }

    try{
        await solicitarApi("DELETE",{idActividad:actividad.idActividad});
        await cargarActividades();
        alert("Se elimino Correctamente");
    } catch (error){
        alert(error.message);
    }
}

async function gestionarParticipacion(actividad) {
    const retirar = actividad.participa;
    const accion = retirar ? "retirarse" : "participar";

    try {
        const resultado = await solicitarApi(retirar ? "DELETE" : "POST", {
            accion,
            idActividad: Number(actividad.idActividad)
        });
        await cargarActividades();
        alert(resultado.message);
    } catch (error) {
        alert(error.message);
    }
}

function configurarAcciones(){ 
        if(accionesConfiguradas){
            return;
        }
        const botonNueva=document.getElementById("nuevaActividad");

        if(esAdministrador){
            botonNueva.classList.remove("d-none");
            botonNueva.addEventListener("click", prepararNuevaActividad);
        }
        accionesConfiguradas = true;
}

function prepararNuevaActividad() {
    formularioActividad.reset();
    document.getElementById("idActividad").value = "";
    document.getElementById("campoEstadoActividad").classList.add("d-none");
    document.getElementById("campoTotalRecaudado").classList.add("d-none");
    document.getElementById("estadoActividad").disabled = false;
    document.getElementById("totalRecaudado").disabled = false;
    document.getElementById("tituloModalActividad").textContent = "Nueva actividad";
    document.getElementById("guardarActividad").textContent = "Guardar";
}

function abrirEdicion(actividad){
    formularioActividad.reset();
    document.getElementById("idActividad").value=actividad.idActividad;
    document.getElementById("nombreActividad").value=actividad.nombreActividad;
    document.getElementById("fechaEvento").value=actividad.fechaEvento;
    document.getElementById("municipio").value=actividad.municipio;
    document.getElementById("totalRecaudado").value=Number(actividad.totalRecaudado);
    document.getElementById("estadoActividad").value=actividad.estado;

    const estaFinalizada = actividad.estado === "Finalizada";
    document.getElementById("estadoActividad").disabled = estaFinalizada;
    document.getElementById("totalRecaudado").disabled = estaFinalizada;

    //Para cuando se usa el modal en html
    document.getElementById("campoEstadoActividad").classList.remove("d-none");
    document.getElementById("campoTotalRecaudado").classList.remove("d-none");
    document.getElementById("tituloModalActividad").textContent= "Editar actividad";
    document.getElementById("guardarActividad").textContent = "Guardar cambios";
    modalActividad.show();
}

formularioActividad.addEventListener("submit", async(evento)=>{
    evento.preventDefault();
    const idActividad=document.getElementById("idActividad").value;


    const datos ={
        nombreActividad: document.getElementById("nombreActividad").value.trim(),
        fechaEvento: document.getElementById("fechaEvento").value,
        municipio: document.getElementById("municipio").value.trim(),
    }
    if(idActividad){
        datos.idActividad = Number(idActividad);
        const total = document.getElementById("totalRecaudado").value;
        datos.totalRecaudado = total === "" ? 0 : Number(total);
        datos.estado = document.getElementById("estadoActividad").value;
    }

    try{
        await solicitarApi(idActividad ? "PUT" : "POST", datos);
        modalActividad.hide();
        alert("actualizada correctamente");
        cargarActividades();
    }catch (error){
        alert(error.message);
    }
});

cargarActividades();