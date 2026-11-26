<?php

session_start();

header("Content-Type: application/json; charset=utf-8");

function leerDatos() {
    $datos = json_decode(file_get_contents("php://input"), true);
    if(json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        throw new Exception("Error al decodificar JSON: " . json_last_error_msg());
    }
    return $datos;
}

function validarActividad($datos){

    $nombre = $datos['nombreActividad'] ?? null;
    $fecha = $datos['fechaEvento'] ?? null;
    $municipio = $datos['municipio'] ?? null;

    if(!is_string($nombre)|| trim($nombre) ==="" || strlen($nombre) > 100 || !is_string($municipio) || trim($municipio) === "" || strlen($municipio) > 50) {
        http_response_code(400);
        throw new Exception("Ingresa un nombre y municipio válidos");   
    }
    return[
        "nombreActividad" => trim($nombre),
        "fechaEvento" => $fecha,
        "municipio" => trim($municipio)
    ];
}

function validarTotalRecaudado($total){
    if ($total === null || $total === "") {
        return 0;
    }
    if (!is_numeric($total) || (float)$total < 0) {
        http_response_code(400);
        throw new Exception("Ingresa un total recaudado válido.");
    }
    return (float)$total;
}

function validarEstadoActividad($estado){
    if (!in_array($estado, ["En curso", "Finalizada"], true)) {
        http_response_code(400);
        throw new Exception("Selecciona un estado válido para la actividad.");
    }
    return $estado;
}


function validarId($id){
    $idActividad=filter_var($id,FILTER_VALIDATE_INT,["options" =>["min_range" => 1]]);
    if ($idActividad === false){
        http_response_code(400);
        throw new Exception ("El identificador de la actividad no es válido");
    }
    return $idActividad;
}

try{
    if (!isset($_SESSION["documento"],$_SESSION["rol"])) {
        http_response_code(401);
        throw new Exception("Inicia sesión para consultar las actividades.");
    }

    $metodo = $_SERVER["REQUEST_METHOD"];
    if (!in_array($metodo, ["GET", "POST", "PUT", "DELETE"], true)){
        http_response_code(405);
        throw new Exception("Metodo HTTP no permitido");
    }

    $datos = in_array($metodo, ["POST", "PUT", "DELETE"], true) ? leerDatos() : [];
    $esAdministrador = $_SESSION["rol"] === "admin";
    $accionParticipacion = (
        $metodo === "POST" && ($datos["accion"] ?? null) === "participar"
    ) || (
        $metodo === "DELETE" && ($datos["accion"] ?? null) === "retirarse"
    );

    if (
        (!$esAdministrador && $metodo !== "GET" && !$accionParticipacion) ||
        ($esAdministrador && $accionParticipacion)
    ) {
        http_response_code(403);
        throw new Exception("No tienes permisos para realizar esta acción");
    }

    require_once __DIR__ . "/Actividad.php";
    $actividad = new Actividad();

    switch($metodo){
        case "GET":
            $resultado= [
                "success" => true,
                "rol" => $_SESSION["rol"],
                "actividades" => $actividad->obtenerActividades(
                    $esAdministrador ? null : $_SESSION["documento"]
                )
            ];
            http_response_code(200);
            break;
        case "POST":
            if (($datos["accion"] ?? null) === "participar") {
                $idActividad = validarId($datos["idActividad"] ?? null);
                $actividad->inscribir($idActividad, $_SESSION["documento"]);
                $resultado = [
                    "success" => true,
                    "message" => "Te inscribiste en la actividad."
                ];
                http_response_code(201);
                break;
            }

            $datos = validarActividad($datos);
            $datos["totalRecaudado"] = 0;
            $actividad->guardar($datos);
            $resultado = [
                "success" => true,
                "message" => "Actividad creada exitosamente"
            ];
            http_response_code(201);
            break;
        case "PUT":
            $datos["idActividad"]=validarId($datos["idActividad"] ?? null);
            $datos = array_merge($datos,validarActividad($datos));
            $datos["totalRecaudado"] = validarTotalRecaudado($datos["totalRecaudado"] ?? null);
            $datos["estado"] = validarEstadoActividad($datos["estado"] ?? null);
            
            if(!$actividad->editar($datos)){
                http_response_code(404);
                throw new Exception("No se escontró la actividad");
            }
            $resultado = [
                "success" => true,
                "mensaje" => "Actividad actualizada correctamente"
            ];
            http_response_code(200);
            break;
        case "DELETE":
            $idActividad = validarId($datos["idActividad"] ?? null);

            if (($datos["accion"] ?? null) === "retirarse") {
                $actividad->retirar($idActividad, $_SESSION["documento"]);
                $resultado = [
                    "success" => true,
                    "message" => "Te retiraste de la actividad."
                ];
                http_response_code(200);
                break;
            }

            if(!$actividad->eliminar($idActividad)){
                http_response_code(404);
                throw new Exception("No se escontró la actividad");
            }
            $resultado = [
                "success" => true,
                "mensaje" => "Actividad eliminada correctamente"
            ];
            http_response_code(200);
            break;
            
    }
    echo json_encode($resultado);
    
}catch(PDOException $e){
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage()

    ]);
}catch(DomainException $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}