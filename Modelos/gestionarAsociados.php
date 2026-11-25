<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");

function leerDatosAsociado() {
    $datos = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($datos)) {
        http_response_code(400);
        throw new Exception("Envía los datos del asociado en formato JSON válido.");
    }
    return $datos;
}

function validarIdAsociado($id) {
    $idAsociado = filter_var($id, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    if ($idAsociado === false) {
        http_response_code(400);
        throw new Exception("El identificador del asociado no es válido.");
    }
    return $idAsociado;
}

function validarDatosAsociado($datos) {
    $campos = [
        "documento", "nombres", "apellidos", "fechaNacimiento", "genero",
        "direccionResidencia", "ciudad", "telefono", "email", "rol"
    ];

    foreach ($campos as $campo) {
        if (!isset($datos[$campo]) || !is_string($datos[$campo]) || trim($datos[$campo]) === "") {
            http_response_code(400);
            throw new Exception("El campo {$campo} es obligatorio.");
        }
        $datos[$campo] = trim($datos[$campo]);
    }

    if (!preg_match('/^\d{1,19}$/', $datos["documento"])) {
        http_response_code(400);
        throw new Exception("Ingresa un documento válido.");
    }
    if (strlen($datos["nombres"]) > 50 || strlen($datos["apellidos"]) > 50 ||
        strlen($datos["ciudad"]) > 50 || strlen($datos["direccionResidencia"]) > 100 ||
        strlen($datos["telefono"]) > 20) {
        http_response_code(400);
        throw new Exception("Uno o más campos superan la longitud permitida.");
    }
    if (!filter_var($datos["email"], FILTER_VALIDATE_EMAIL) || strlen($datos["email"]) > 100) {
        http_response_code(400);
        throw new Exception("Ingresa un correo electrónico válido.");
    }
    if (!in_array($datos["genero"], ["Masculino", "Femenino", "Otro"], true) ||
        !in_array($datos["rol"], ["Admin", "Asociado"], true)) {
        http_response_code(400);
        throw new Exception("El género o rol seleccionado no es válido.");
    }
    $fecha = DateTime::createFromFormat("Y-m-d", $datos["fechaNacimiento"]);
    if (!$fecha || $fecha->format("Y-m-d") !== $datos["fechaNacimiento"]) {
        http_response_code(400);
        throw new Exception("Ingresa una fecha de nacimiento válida.");
    }
    if (isset($datos["contraseña"]) && !is_string($datos["contraseña"])) {
        http_response_code(400);
        throw new Exception("La contraseña no es válida.");
    }

    return $datos;
}

try {
    if (!isset($_SESSION["documento"], $_SESSION["rol"])) {
        http_response_code(401);
        throw new Exception("Inicia sesión para consultar los asociados.");
    }

    if ($_SESSION["rol"] !== "admin") {
        http_response_code(403);
        throw new Exception("No tienes permisos para gestionar los asociados.");
    }

    $metodo = $_SERVER["REQUEST_METHOD"];
    if (!in_array($metodo, ["GET", "PUT", "DELETE"], true)) {
        http_response_code(405);
        throw new Exception("Método HTTP no permitido.");
    }

    require_once __DIR__ . "/../Config/conexion.php";
    $conexion = (new Conexion())->conectar();

    if ($metodo === "GET") {
        $consulta = $conexion->query(
            "SELECT idAsociado, documento, nombres, apellidos, fechaNacimiento, genero,
                    direccionResidencia, ciudad, telefono, email, rol
             FROM asociados
             ORDER BY apellidos ASC, nombres ASC"
        );
        echo json_encode([
            "success" => true,
            "rol" => $_SESSION["rol"],
            "asociados" => $consulta->fetchAll(PDO::FETCH_ASSOC)
        ]);
    } elseif ($metodo === "PUT") {
        $datos = leerDatosAsociado();
        $datos["idAsociado"] = validarIdAsociado($datos["idAsociado"] ?? null);
        $datos = validarDatosAsociado($datos);

        $actualizaciones = "documento = :documento, nombres = :nombres, apellidos = :apellidos,
            fechaNacimiento = :fechaNacimiento, genero = :genero, direccionResidencia = :direccionResidencia,
            ciudad = :ciudad, telefono = :telefono, email = :email, rol = :rol";
        $parametros = [
            ":idAsociado" => $datos["idAsociado"],
            ":documento" => $datos["documento"],
            ":nombres" => $datos["nombres"],
            ":apellidos" => $datos["apellidos"],
            ":fechaNacimiento" => $datos["fechaNacimiento"],
            ":genero" => $datos["genero"],
            ":direccionResidencia" => $datos["direccionResidencia"],
            ":ciudad" => $datos["ciudad"],
            ":telefono" => $datos["telefono"],
            ":email" => $datos["email"],
            ":rol" => $datos["rol"]
        ];

        if (isset($datos["contraseña"]) && $datos["contraseña"] !== "") {
            $actualizaciones .= ", contraseña = :contrasena";
            $parametros[":contrasena"] = password_hash($datos["contraseña"], PASSWORD_DEFAULT);
        }

        $consulta = $conexion->prepare(
            "UPDATE asociados SET {$actualizaciones} WHERE idAsociado = :idAsociado"
        );
        $consulta->execute($parametros);

        if ($consulta->rowCount() === 0) {
            $verificar = $conexion->prepare("SELECT 1 FROM asociados WHERE idAsociado = :idAsociado");
            $verificar->execute([":idAsociado" => $datos["idAsociado"]]);
            if (!$verificar->fetchColumn()) {
                http_response_code(404);
                throw new Exception("No se encontró el asociado.");
            }
        }

        echo json_encode(["success" => true, "mensaje" => "Asociado actualizado correctamente."]);
    } else {
        $datos = leerDatosAsociado();
        $idAsociado = validarIdAsociado($datos["idAsociado"] ?? null);
        $consulta = $conexion->prepare("DELETE FROM asociados WHERE idAsociado = :idAsociado");
        $consulta->execute([":idAsociado" => $idAsociado]);

        if ($consulta->rowCount() === 0) {
            http_response_code(404);
            throw new Exception("No se encontró el asociado.");
        }

        echo json_encode(["success" => true, "mensaje" => "Asociado eliminado correctamente."]);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code($e->getCode() === "23000" ? 409 : 500);
    echo json_encode([
        "success" => false,
        "message" => $e->getCode() === "23000"
            ? "No se puede completar la operación: revisa duplicados o registros relacionados."
            : "No se pudo completar la operación de asociados."
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
