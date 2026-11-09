const usuarios = [
    { usuario: 'admin@gmail.com', clave: '123', rol: 'admin', nombre: 'Admin MR' },
    { usuario: 'pepito@gmail.com', clave: '456', rol: 'asociado', nombre: 'Jaun Perez' }
];

document.getElementById('formLogin').addEventListener('submit', function (e) {
    e.preventDefault();

    const usuarioInput = document.getElementById('usuario').value;
    const claveInput = document.getElementById('contraseña').value;
    const usuarioEncontrado = usuarios.find
        (u => u.usuario === usuarioInput && u.clave === claveInput
    );

    if (usuarioEncontrado){
        localStorage.setItem('logueado', 'true');
        localStorage.setItem('rol', usuarioEncontrado.rol);
        localStorage.setItem('nombre', usuarioEncontrado.nombre);
        window.location.href = 'dashboard.html';
    }else{
        alert('Usuario o contraseña incorrectos');
    }
});