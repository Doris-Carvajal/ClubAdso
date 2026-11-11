<?php
//para decirle que vamos a manejar la respuenta en json 
header ("Content-Type: application/json; charset=utf-8");

// verifica que script solo procese solicitudes que utilicen HHTP POST
 if($_SERVER['REQUEST_METHOD']!== 'POST'){
        http_response_code(405);
        //Saltar a catch y mostrar el mensaje de error
        throw new Exception("Método HTTP no permitido.");
    }
// recuperar lo que tenemos guardado en la sesion 
session_start();
// $_SESSION es un array que almacena datos especificos de un usuario 
$_SESSION=[];

//Elimina corectamente la cookie de sesión del navegador durante el proceso de cierre de sesión
if(ini_get("session.use_cookies")){ //Comprueba si la configuración del servidor tiene habilitado el uso de cookies para gestionar las sesiones.
    $params=session_get_cookie_params();//Obtiene un array con los parámetros de seguridad originales con los que se configuró la cookie
    
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    //session_name(): Obtiene el nombre de la cookie de sesión.
    //time () Establece una fecha de expiración en el pasado lo que obliga al navegador del usuario a borrar la cookie de inmediato.
    //($params["path"], $params["domain"], ...) aseguran que la orden de eliminación apunte exactamente a la cookie correcta aplicando sus restricciones originales.

}   

session_destroy();//Eliminar todos los datos asociados a la sesión actual en el servidor.

echo json_encode([
    "success" => true, 
])