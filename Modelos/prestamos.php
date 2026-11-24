<?php

require_once __DIR__ .'/../Config/conexion.php';

class Prestamo {
    private $conexion;

    public function __construct(){
        $conexionDB = new Conexion();
        $this->conexion = $conexionDB->conectar();
    }

    public function obtenerPrestamos(){
        $sql = "SELECT * FROM prestamos ORDER BY fechaPrestamo ASC";
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

    public function guardar($datos){
        $sql = "INSERT INTO prestamos (idAsociado, valorPrestamo, tasaInteres, numeroCuotas, fechaPrestamo)
        :idAsociado, :valorPrestamo, :tasaInteres, :numeroCuotas, :fechaPrestamo)";
        $consulta = $this->conexion->prepare($sql);
        return $consulta->execute([
            ':idAsociado' => $datos['idAsociado'],
            ':valorPrestamo' => $datos['valorPrestamo'],
            ':tasaInteres' => $datos['tasaInteres'],
            ':numeroCuotas' => $datos['numeroCuotas'],
            ':fechaPrestamo' =>$datos['fechaPrestamo']
        ]);
    }
}