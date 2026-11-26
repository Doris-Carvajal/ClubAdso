<?php

session_start();

header("Content-Type: application/json;charset=UTF-8");

function leerDatos() {
    $datos = json_decode(file_get_contents("php://input"), true);
    if(json_last_error() !== JSON_ERROR_NONE || !is_array($datos)) {
        http_response_code(400);
        throw new Exception("Envía los datos del pago en formato JSON válido.");
    }
    return $datos;
}

function validarPago($datos){

    $idPrestamo = $datos['idPrestamo'] ?? null;
    $valorCuota = $datos['valorCuota'] ?? null;
    $fechaPago = $datos['fechaPago'] ?? null;

    $idPrestamoValido = filter_var($idPrestamo, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);

    if(!is_numeric($valorCuota) || (float)$valorCuota <= 0 || !is_string($fechaPago) || trim($fechaPago) === "" || $idPrestamoValido === false) {
        http_response_code(400);
        throw new Exception("Ingresa un valor de cuota, fecha de pago y préstamo válidos");
    }
    return[
        "valorCuota" => (float)$valorCuota,
        "fechaPago" => trim($fechaPago),
        "idPrestamo" => (int)$idPrestamo
    ];
}

function validarId($id){
    $idPago = filter_var($id, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    if ($idPago === false){
        http_response_code(400);
        throw new Exception ("El identificador del pago no es válido");
    }
    return $idPago;
}

try{
    if (!isset($_SESSION["documento"],$_SESSION["rol"])) {
        http_response_code(401);
        throw new Exception("Inicia sesión para consultar los pagos.");
    }

    $metodo = $_SERVER["REQUEST_METHOD"];
    if (!in_array($metodo, ["GET", "POST", "PUT", "DELETE"],true)){
        http_response_code(405);
        throw new Exception("Metodo HTTP no permitido");
    }

    if(!in_array($metodo, ["GET", "POST"], true) && $_SESSION["rol"] !== "admin"){
        http_response_code(403);
        throw new Exception("No tienes permisos para realizar esta acción");
    }

    require_once __DIR__ . "/pagos.php";
    require_once __DIR__ . "/prestamos.php";
    $pagoCuota = new PagoCuota();
    $esAdministrador = $_SESSION["rol"] === "admin";
    $documentoAsociado = $esAdministrador ? null : $_SESSION["documento"];

    switch($metodo){
        case "GET":
            $resultado = [
                "success" => true,
                "rol" => $_SESSION["rol"],
                "pagos" => $pagoCuota->obtenerPagos($documentoAsociado),
                "prestamos" => (new Prestamo())->obtenerPrestamos($documentoAsociado)
            ];
            http_response_code(200);
            break;
        case "POST":
            $datos = validarPago(leerDatos());
            if(!$pagoCuota->guardar($datos, $documentoAsociado)){
                http_response_code(500);
                throw new Exception("No fue posible guardar el pago.");
            }
            $resultado = [
                "success" => true,
                "message" => "Pago registrado exitosamente"
            ];
            http_response_code(201);
            break;
        case "PUT":
            $datos = leerDatos();
            $datos["idPago"] = validarId($datos["idPago"] ?? null);
            $datos = array_merge($datos, validarPago($datos));

            if(!$pagoCuota->editar($datos)){
                http_response_code(404);
                throw new Exception("No se encontró el pago");
            }
            $resultado = [
                "success" => true,
                "mensaje" => "Pago actualizado correctamente"
            ];
            http_response_code(200);
            break;
        case "DELETE":
            $datos = leerDatos();
            $idPago = validarId($datos["idPago"] ?? null);

            if(!$pagoCuota->eliminar($idPago)){
                http_response_code(404);
                throw new Exception("No se encontró el pago");
            }
            $resultado = [
                "success" => true,
                "mensaje" => "Pago eliminado correctamente"
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
}catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}