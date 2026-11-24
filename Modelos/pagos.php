<?php

require_once __DIR__ .'/../Config/conexion.php';

class PagoCuota {
    private $conexion;
    public function __construct(){
        $conexionDB = new Conexion();
        $this -> conexion = $conexionDB->conectar();
    }

    public function obtenerPagos(){
        $sql = "SELECT * FROM pagos_cuotas ORDER BY fechaPago DESC, idPago DESC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute();
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPagoId($id){
        $sql =" SELECT * FROM pagos_cuotas WHERE idPago = :id";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':id' => $id]);
        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    public function guardar($datos){
        $sql = "INSERT INTO pagos_cuotas (idPrestamo, valorCuota, fechaPago)
                VALUES (:idPrestamo, :valorCuota, :fechaPago)";
        $consulta = $this->conexion->prepare($sql);
        return $consulta->execute([
            ':idPrestamo' => $datos['idPrestamo'],
            ':valorCuota' => $datos['valorCuota'],
            ':fechaPago' => $datos['fechaPago']
        ]);
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