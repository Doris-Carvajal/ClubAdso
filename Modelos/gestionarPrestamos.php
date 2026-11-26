<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");

function leerDatos() {
    $datos = json_decode(file_get_contents("php://input"), true);
    if(json_last_error() !== JSON_ERROR_NONE || !is_array($datos)) {
        http_response_code(400);
        throw new Exception("Envía los datos del préstamo en formato JSON válido.");
    }
    return $datos;
}

function validarPrestamos($datos){
    $valor = $datos['valorPrestamo'] ?? null;
    $interes = $datos['tasaInteres'] ?? null;
    $cuotas = $datos['numeroCuotas'] ?? null;
    $fecha = $datos['fechaPrestamo'] ?? null;
    $idAsociado = filter_var($datos['idAsociado'] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);

    if(
        !is_numeric($valor) || (float)$valor <= 0 ||
        !is_numeric($interes) || (float)$interes < 0 ||
        filter_var($cuotas, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) === false ||
        !is_string($fecha) || trim($fecha) === "" ||
        $idAsociado === false
    ){
        http_response_code(400);
        throw new Exception("Ingresa los datos válidos del préstamo y selecciona un asociado.");
    }
    return [
        "valorPrestamo" => (float)$valor,
        "tasaInteres" => (float)$interes,
        "numeroCuotas" => (int)$cuotas,
        "fechaPrestamo" => trim($fecha),
        "idAsociado" => (int)$idAsociado
    ];
}

function validarId($id){
    $idPrestamo=filter_var($id,FILTER_VALIDATE_INT,["options" =>["min_range" => 1]]);
    if ($idPrestamo === false){
        http_response_code(400);
        throw new Exception ("El identificador del prestamo no es valido ");
    }
    return $idPrestamo;
}

try{
    if(!isset($_SESSION["documento"], $_SESSION["rol"])){
        http_response_code(401);
        throw new Exception("Inicia sesión para consultar los préstamos.");
    }

    $metodo = $_SERVER["REQUEST_METHOD"];
    if(!in_array($metodo, ["GET", "POST", "PUT", "DELETE"], true)){
        http_response_code(405);
        throw new Exception("Metodo HTTP no permitido");
    }
    if($metodo !== "GET" && $_SESSION["rol"] !=="admin"){
        http_response_code(403);
        throw new Exception("No tienes permisos para realizar esta acción");
    }

    require_once __DIR__ . "/prestamos.php";
    $prestamo = new Prestamo();

    switch($metodo){
        case "GET":
            $documentoAsociado = $_SESSION["rol"] === "admin" ? null : $_SESSION["documento"];
            $resultado =[
                "success" => true,
                "rol" => $_SESSION["rol"],
                "prestamos" => $prestamo->obtenerPrestamos($documentoAsociado),
                "asociados" => $_SESSION["rol"] === "admin" ? $prestamo->obtenerAsociados() : []
            ];
            http_response_code(200);
            break;
        case "POST":
            $datos = validarPrestamos(leerDatos());
            $prestamo->guardar($datos);
            $resultado =[
                "success" => true,
                "message" => "Préstamo creado exitosamente"
            ];
            http_response_code(201);
            break;
        case "PUT":
            $datos = leerDatos();
            $datos["idPrestamo"] = validarId($datos["idPrestamo"] ?? null);
            $datos = array_merge($datos,validarPrestamos($datos));

            if(!$prestamo->editar($datos)){
                http_response_code(404);
                throw new Exception ("No se encontró el préstamo");
            }
            $resultado =[
                "success" => true,
                "mensaje" => "Préstamo actualizado correctamente"
            ];
            http_response_code(200);
            break;
        case "DELETE":
            $datos = leerDatos();
            $idPrestamo = validarId($datos["idPrestamo"] ?? null);

            if(!$prestamo->eliminar($idPrestamo)){
                http_response_code(404);
                throw new Exception ("No se encontró el préstamo");
            }
            $resultado = [
                "success" => true,
                "mensaje" => "Préstamo eliminado correctamente"
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
}catch(DomainException $e){
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}catch(Exception $e){
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}