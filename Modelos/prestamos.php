<?php

require_once __DIR__ .'/../Config/conexion.php';

class Prestamo {
    private $conexion;

    public function __construct(){
        $conexionDB = new Conexion();
        $this->conexion = $conexionDB->conectar();
    }

    public function obtenerPrestamos($documentoAsociado = null){
        $sql = "SELECT prestamo.*,
                       COALESCE(pagos.cuotasPagadas, 0) AS cuotasPagadas
                FROM prestamos AS prestamo
                LEFT JOIN (
                    SELECT idPrestamo, COUNT(*) AS cuotasPagadas
                    FROM pagos_cuotas
                    GROUP BY idPrestamo
                ) AS pagos ON pagos.idPrestamo = prestamo.idPrestamo";
        $parametros = [];

        if ($documentoAsociado !== null) {
            $sql .= " INNER JOIN asociados AS asociado
                      ON asociado.idAsociado = prestamo.idAsociado
                      WHERE asociado.documento = :documento";
            $parametros[":documento"] = $documentoAsociado;
        }

        $sql .= " ORDER BY prestamo.fechaPrestamo ASC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerAsociados(){
        $sql = "SELECT idAsociado, documento, nombres, apellidos
                FROM asociados
                ORDER BY apellidos ASC, nombres ASC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute();
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPrestamoPorId($id){
        $sql = "SELECT * FROM prestamos WHERE idPrestamo = :id";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':id' => $id]);
        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    private function bloquearAsociado($idAsociado){
        $consulta = $this->conexion->prepare(
            "SELECT idAsociado FROM asociados WHERE idAsociado = :idAsociado FOR UPDATE"
        );
        $consulta->execute([":idAsociado" => $idAsociado]);
        if (!$consulta->fetchColumn()) {
            throw new DomainException("El asociado seleccionado ya no está registrado.", 400);
        }
    }

    private function obtenerResumenPrestamosVigentes($idAsociado){
        $sql = "SELECT COUNT(*) AS cantidad, COALESCE(SUM(prestamo.valorPrestamo), 0) AS monto
                FROM prestamos AS prestamo
                LEFT JOIN (
                    SELECT idPrestamo, COUNT(*) AS cuotasPagadas
                    FROM pagos_cuotas
                    GROUP BY idPrestamo
                ) AS pagos ON pagos.idPrestamo = prestamo.idPrestamo
                WHERE prestamo.idAsociado = :idAsociado
                  AND COALESCE(pagos.cuotasPagadas, 0) < prestamo.numeroCuotas";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([":idAsociado" => $idAsociado]);
        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    private function validarLimitesPrestamos($resumen, $esNuevo = false, $montoNuevoCentavos = 0){
        $cantidad = (int) $resumen["cantidad"] + ($esNuevo ? 1 : 0);
        if ($cantidad > 3) {
            throw new DomainException("El asociado no puede tener más de 3 préstamos vigentes.", 409);
        }

        $montoCentavos = (int) round((float) $resumen["monto"] * 100) + $montoNuevoCentavos;
        if ($montoCentavos > 8000000000) {
            throw new DomainException("La suma de los préstamos vigentes no puede superar $80.000.000.", 409);
        }
    }

    public function guardar($datos){
        $this->conexion->beginTransaction();
        try {
            $this->bloquearAsociado($datos["idAsociado"]);
            $resumen = $this->obtenerResumenPrestamosVigentes($datos["idAsociado"]);
            $this->validarLimitesPrestamos(
                $resumen,
                true,
                (int) round($datos["valorPrestamo"] * 100)
            );

            $sql = "INSERT INTO prestamos (idAsociado, valorPrestamo, tasaInteres, numeroCuotas, fechaPrestamo)
                    VALUES (:idAsociado, :valorPrestamo, :tasaInteres, :numeroCuotas, :fechaPrestamo)";
            $consulta = $this->conexion->prepare($sql);
            $consulta->execute([
                ":idAsociado" => $datos["idAsociado"],
                ":valorPrestamo" => $datos["valorPrestamo"],
                ":tasaInteres" => $datos["tasaInteres"],
                ":numeroCuotas" => $datos["numeroCuotas"],
                ":fechaPrestamo" => $datos["fechaPrestamo"]
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

    public function eliminar($idPrestamo){
        $sql = "DELETE FROM prestamos WHERE idPrestamo = :idPrestamo";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':idPrestamo' => $idPrestamo]);
        return $consulta->rowCount() > 0;
    }

    public function editar($datos){
        $buscarAsociado = $this->conexion->prepare(
            "SELECT idAsociado FROM prestamos WHERE idPrestamo = :idPrestamo"
        );
        $buscarAsociado->execute([":idPrestamo" => $datos["idPrestamo"]]);
        $idAsociado = $buscarAsociado->fetchColumn();
        if ($idAsociado === false) {
            return false;
        }

        $this->conexion->beginTransaction();
        try {
            $this->bloquearAsociado($idAsociado);
            $buscarPrestamo = $this->conexion->prepare(
                "SELECT 1 FROM prestamos WHERE idPrestamo = :idPrestamo AND idAsociado = :idAsociado FOR UPDATE"
            );
            $buscarPrestamo->execute([
                ":idPrestamo" => $datos["idPrestamo"],
                ":idAsociado" => $idAsociado
            ]);
            if (!$buscarPrestamo->fetchColumn()) {
                $this->conexion->rollBack();
                return false;
            }

            $sql = "UPDATE prestamos
                    SET valorPrestamo = :valorPrestamo, tasaInteres = :tasaInteres,
                        numeroCuotas = :numeroCuotas, fechaPrestamo = :fechaPrestamo
                    WHERE idPrestamo = :idPrestamo";
            $consulta = $this->conexion->prepare($sql);
            $consulta->execute([
                ":idPrestamo" => $datos["idPrestamo"],
                ":valorPrestamo" => $datos["valorPrestamo"],
                ":tasaInteres" => $datos["tasaInteres"],
                ":numeroCuotas" => $datos["numeroCuotas"],
                ":fechaPrestamo" => $datos["fechaPrestamo"]
            ]);

            $resumen = $this->obtenerResumenPrestamosVigentes($idAsociado);
            $this->validarLimitesPrestamos($resumen);
            $this->conexion->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }
    }
}