const formulario = document.getElementById('formLogin');
formulario.addEventListener('submit', async function (e) {
    e.preventDefault();

    const boton = formulario.querySelector('button[type="submit"]');
    const mensaje = document.getElementById("mensajeLogin");

    boton.disabled = true;

    try {
        const respuesta = await fetch(formulario.action, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(Object.fromEntries(new FormData(formulario).entries())),
        });

        const resultado = await respuesta.json();

        if (!resultado.success) {
            mensaje.textContent =
                "No fue posible iniciar sesión. Revisa los datos e inténtalo de nuevo.";
            return;
        }
        localStorage.setItem('logueado', 'true');
        localStorage.setItem('rol', resultado.rol);
        localStorage.setItem('nombre', resultado.nombre);

        window.location.href = 'dashboard.html';
    }catch(error) {
        console.error("Error al iniciar sesion:", error);
        mensaje.textContent =
            "Ocurrió un error al intentar iniciar sesión. Por favor, inténtalo de nuevo más tarde.";
    }finally{
        boton.disabled = false;
    }
});