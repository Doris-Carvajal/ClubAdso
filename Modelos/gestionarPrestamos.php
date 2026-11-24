<?php

session_start();

header("Content-Type: application/json; charset= UTF-8");

function leerDatos() {
    $datos = json_decode(file_get_contents("php://input"), true);
    if(json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        throw new Exception("Error al decodificar JSON: " . json_last_error_msg());
    }
    return $datos;
}

function validarPrestamos($datos){
    $valor= $datos['valorPrestamo'] ?? null;
    $Interes= $datos['tasaInteres'] ?? null;
    $Cuotas = $datos['numeroCuotas'] ?? null;
    $fecha = $datos['fechaPrestamo'] ?? null;
    $idAsociado = $datos['idAsociado'] ?? 1;

    if(
        !is_numeric($valorPrestamo) ||
        !is_numeric($tasaInteres) || 
        !is_numeric($numeroCuotas) ||
        !is_string($fechaPrestamo)

    ){
        http_response_code(400);
        throw new Exception("Ingresa un valor de préstamo, tasa de interés, número de cuotas y fecha válidos");
        
    }
    return [
        "valorPrestamo" => float($valor),
        "tasaInteres" => float($Interes),
        "numeroCuotas" => int ($Cuotas),
        "fechaPrestamo" => float ($fecha),
        "idAsociado" => int ($idAsociado)
    ];
}

function validarId($id){
    $idPrestamo=filter_var($id,FILTER_VALIDATE_INT,["options" =>["min_range" => 1]]);
    if ($idActividad === false){
        http_response_code(400);
        throw new Exception ("El identificador del prestamo no es valido ");
    }
    return $idPrestamo;
}

try{
    if(!isset($_SESSION["documento"],_SESSION["rol"])){
        http_response_code(401);
        throw new Exception("Inicia sesión para consultar los préstamos.");
    }

    $metodo = $_SERVER["REQUEST_METHOD"];
    if(!IN_ARRAY($metodo, ["GET", "POST", "PUT", "DELETE"], true)){
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
            $resultado =[
                "success" => true,
                "rol" => $_SESSION["rol"],
                "prestamos" => $prestamo->obtenerPrestamos()
            ];
            http_response_code(200);,
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
                    $datos=leerDatos();
                    $datos["idPrestamo"] = validarId($datos["idPrestamo"] ?? null);
                    $datos = array_merge($datos,validarPrestamos($datos));

                    if(!$prestamo->editar($datos)){
                        http_response_code(404);
                        throw new Exception ("No se encontro el prestamo");
                    }
                    $resultado=[
                        "success" => true,
                        "mensaje" => "Prestamo actualizado correctamente"
                    ];
                    http_response_code(200);
                    break;
                    case "DELETE";
                    $datos = leerDatos();
                    $idPrestamo = validarId($datos["idPrestamo"] ?? null);

                    if(!$prestamo->eliminar($idPrestamo)){
                        http_response_code(404);
                        throw new Exception ("No se encontro el préstamo");
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
}catch(Exception $e){
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}