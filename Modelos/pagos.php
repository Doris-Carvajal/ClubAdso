<?php

require_once __DIR__ .'/../Config/conexion.php';

class PagoCuota {
    private $conexion;
    public function __construct(){
        $conexionDB = new Conexion();
        $this -> conexion = $conexionDB->conectar();
    }

    public function obtenerPagos($documentoAsociado = null){
        $sql = "SELECT pago.*
                FROM pagos_cuotas AS pago";
        $parametros = [];

        if ($documentoAsociado !== null) {
            $sql .= " INNER JOIN prestamos AS prestamo
                        ON prestamo.idPrestamo = pago.idPrestamo
                      INNER JOIN asociados AS asociado
                        ON asociado.idAsociado = prestamo.idAsociado
                      WHERE asociado.documento = :documento";
            $parametros[":documento"] = $documentoAsociado;
        }

        $sql .= " ORDER BY pago.fechaPago DESC, pago.idPago DESC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPagoId($id){
        $sql =" SELECT * FROM pagos_cuotas WHERE idPago = :id";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':id' => $id]);
        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    public function guardar($datos, $documentoAsociado = null){
        $this->conexion->beginTransaction();
        try {
            $prestamo = $this->conexion->prepare(
                "SELECT prestamo.numeroCuotas, asociado.documento
                 FROM prestamos AS prestamo
                 INNER JOIN asociados AS asociado
                    ON asociado.idAsociado = prestamo.idAsociado
                 WHERE prestamo.idPrestamo = :idPrestamo
                 FOR UPDATE"
            );
            $prestamo->execute([":idPrestamo" => $datos["idPrestamo"]]);
            $datosPrestamo = $prestamo->fetch(PDO::FETCH_ASSOC);

            if (!$datosPrestamo) {
                throw new DomainException("No se encontró el préstamo seleccionado.", 404);
            }
            if ($documentoAsociado !== null &&
                (string) $datosPrestamo["documento"] !== (string) $documentoAsociado) {
                throw new DomainException("Solo puedes registrar pagos para tus propios préstamos.", 403);
            }

            $cantidadPagos = $this->conexion->prepare(
                "SELECT COUNT(*) FROM pagos_cuotas WHERE idPrestamo = :idPrestamo"
            );
            $cantidadPagos->execute([":idPrestamo" => $datos["idPrestamo"]]);
            if ((int) $cantidadPagos->fetchColumn() >= (int) $datosPrestamo["numeroCuotas"]) {
                throw new DomainException("Este préstamo ya tiene registradas todas sus cuotas.", 409);
            }

            $sql = "INSERT INTO pagos_cuotas (idPrestamo, valorCuota, fechaPago)
                    VALUES (:idPrestamo, :valorCuota, :fechaPago)";
            $consulta = $this->conexion->prepare($sql);
            $consulta->execute([
                ":idPrestamo" => $datos["idPrestamo"],
                ":valorCuota" => $datos["valorCuota"],
                ":fechaPago" => $datos["fechaPago"]
            ]);

            $this->conexion->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }
    }

    public function eliminar($idPago){
        $sql = "DELETE FROM pagos_cuotas WHERE idPago = :idPago";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':idPago' => $idPago]);
        return $consulta->rowCount() > 0;
    }

    public function editar($datos){
        $sql = "UPDATE pagos_cuotas
                SET idPrestamo = :idPrestamo, valorCuota = :valorCuota, fechaPago = :fechaPago
                WHERE idPago = :idPago";
        $consulta = $this->conexion->prepare($sql);
        $ejecutado = $consulta->execute([
            ':idPago' => $datos['idPago'],
            ':idPrestamo' => $datos['idPrestamo'],
            ':valorCuota' => $datos['valorCuota'],
            ':fechaPago' => $datos['fechaPago']
        ]);

        if ($consulta->rowCount()>0){
            return true;
        }

        $verificar = $this->conexion->prepare("SELECT 1 FROM pagos_cuotas WHERE idPago = :idPago");
        $verificar->execute([':idPago' => $datos['idPago']]);

        return (bool)$verificar->fetchColumn();
    }
}                                                      