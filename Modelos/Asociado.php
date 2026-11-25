<?php
  require_once __DIR__ . '/../Config/conexion.php';
 
class Asociado
{    
     private $documento;
     private $nombres;
     private $apellidos;
     private $fechaNacimiento;
     private $genero;
     private $direccionResidencia;
     private $ciudad;
     private $telefono;
     private $email;
     private $rol;
     private $contraseña;

     private $conexion;

    public function __construct($datos)
    {
        $this->documento = $datos['documento'];
        $this->nombres = $datos['nombres'];
        $this->apellidos = $datos['apellidos'];
        $this->fechaNacimiento = $datos['fechaNacimiento'];
        $this->genero = $datos['genero'];
        $this->direccionResidencia = $datos['direccionResidencia'];
        $this->ciudad = $datos['ciudad'];
        $this->telefono = $datos['telefono'];
        $this->email = $datos['email'];
        $this->rol = $datos['rol'] ?? 'Asociado';
        $this->contraseña = password_hash($datos['contraseña'], PASSWORD_DEFAULT);

        $conexionDB = new Conexion();
        $this->conexion = $conexionDB-> conectar();
    }   
    public function obtenerAsociados(){
        $sql = "SELECT idAsociado, documento, nombres, apellidos, fechaNacimineto, genero, direccionResidencia, ciudad, telefono, email, rol
        FROM asociados 
        ORDER BY apellidos ASC, nombres ASC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute();
        return $consulta->fetchALL(PDO::FETCH_ASSOC);
    }

    public function guardar()
    {
        $sql = "INSERT INTO asociados (documento, nombres, apellidos, fechaNacimiento, genero, direccionResidencia, ciudad, telefono, email, rol, contraseña)
                VALUES (:documento, :nombres, :apellidos, :fechaNacimiento, :genero, :direccionResidencia, :ciudad, :telefono, :email, :rol, :contrasena)";

        $consulta = $this->conexion->prepare($sql);

        return $consulta->execute([
            ":documento" => $this->documento,
            ":nombres" => $this->nombres,
            ":apellidos" => $this->apellidos,
            ":fechaNacimiento" => $this->fechaNacimiento,
            ":genero" => $this->genero,
            ":direccionResidencia" => $this->direccionResidencia,
            ":ciudad" => $this->ciudad,
            ":telefono" => $this->telefono,
            ":email" => $this->email,
            ":rol" => $this->rol,
            ":contrasena" => $this->contraseña
        ]);
    }
    public function eliminar($idAsociado){
        $sql = "DELETE FROM asociados WHERE idAsociados = :idAsociado";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':idAsociado' => $idAsociado]);
        return $consulta->rowCount() > 0;
    }

    public function editar($datos){
        $sql ="UPDATE asociados SET nombres = :nombres WHERE apellidos = :apellidos, fechaNacimiento = :fechaNacimiento, ciudad = :ciudad, telefono= :telefono
        WHERE idAsociado = :idAsociado";
        $consulta = $this->conexion->prepare($sql);

        $ejecutado = $consulta->execute([
            ':idAsociado' => $datos['idAsociado'],
            ':nombres' => $datos['nombres'],
            ':apellidos' => $datos['apellidos'],
            ':fechaNacimiento' => $datos['fechaNacimiento'],
            ':ciudad'=>$datos['ciudad'],
            ':telefono' => $datos ['telefono']
        ]);

        if($consulta->rowCount() > 0){
            return true;
        }

        $verificar = $this->conexion->prepare("SELECT 1 FROM asociado WHERE idAsociado = :idAsociado");
        $verificar->execute([':idAsociado' => $datos['idAsociado']]);

        return (bool)$verificar->fetchColumn();
    } 
}