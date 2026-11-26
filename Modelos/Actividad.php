<?php
require_once __DIR__ . '/../Config/conexion.php';
class Actividad {
    private $conexion;
    public function __construct() {
        $conexionDB = new Conexion();
        $this->conexion = $conexionDB->conectar();
    }
    public function obtenerActividades($documentoAsociado = null){
        $sql = "SELECT actividad.*,
                       COUNT(participacion.idParticipacion) AS numeroParticipantes,
                       CASE WHEN actividad.estado = 'Finalizada' THEN 1 ELSE 0 END AS finalizada,
                       CASE WHEN actividad.estado = 'En curso'
                                  AND actividad.fechaEvento >= CURRENT_DATE
                            THEN 1 ELSE 0 END AS permiteInscripcion,
                       CASE WHEN actividad.estado = 'En curso'
                                  AND actividad.fechaEvento > CURRENT_DATE
                            THEN 1 ELSE 0 END AS permiteRetiro";
        $parametros = [];

        if ($documentoAsociado !== null) {
            $sql .= ",
                    MAX(CASE WHEN asociado.documento = :documento THEN 1 ELSE 0 END) AS participa";
            $parametros[":documento"] = $documentoAsociado;
        } else {
            $sql .= ", 0 AS participa";
        }

        $sql .= " FROM actividades AS actividad
                  LEFT JOIN actividades_asociado AS participacion
                    ON participacion.idActividad = actividad.idActividad
                  LEFT JOIN asociados AS asociado
                    ON asociado.idAsociado = participacion.idAsociado
                  GROUP BY actividad.idActividad
                  ORDER BY actividad.fechaEvento ASC";

        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        $actividades = $consulta->fetchAll(PDO::FETCH_ASSOC);

        foreach ($actividades as &$actividad) {
            $total = (float)$actividad["totalRecaudado"];
            $numeroParticipantes = (int)$actividad["numeroParticipantes"];
            $actividad["participa"] = (bool)$actividad["participa"];
            $actividad["finalizada"] = (bool)$actividad["finalizada"];
            $actividad["permiteInscripcion"] = (bool)$actividad["permiteInscripcion"];
            $actividad["permiteRetiro"] = (bool)$actividad["permiteRetiro"];
            $actividad["fondoClub"] = null;
            $actividad["bolsaAsociados"] = null;
            $actividad["valorPorAsociado"] = null;

            if ($actividad["finalizada"]) {
                $actividad["fondoClub"] = round($total / 2, 2);
                $actividad["bolsaAsociados"] = round($total - $actividad["fondoClub"], 2);

                if ($numeroParticipantes > 0) {
                    $actividad["valorPorAsociado"] = round(
                        $actividad["bolsaAsociados"] / $numeroParticipantes,
                        2
                    );
                }
            }
        }
        unset($actividad);

        return $actividades;
    }

    public function inscribir($idActividad, $documentoAsociado) {
        $this->conexion->beginTransaction();

        try {
            $consultaActividad = $this->conexion->prepare(
                "SELECT estado = 'En curso' AND fechaEvento >= CURRENT_DATE AS permiteInscripcion
                 FROM actividades
                 WHERE idActividad = :idActividad
                 FOR UPDATE"
            );
            $consultaActividad->execute([":idActividad" => $idActividad]);
            $actividad = $consultaActividad->fetch(PDO::FETCH_ASSOC);

            if (!$actividad) {
                throw new DomainException("No se encontró la actividad.", 404);
            }
            if (!(bool)$actividad["permiteInscripcion"]) {
                throw new DomainException("La inscripción para esta actividad ya está cerrada.", 409);
            }

            $consultaAsociado = $this->conexion->prepare(
                "SELECT idAsociado FROM asociados WHERE documento = :documento"
            );
            $consultaAsociado->execute([":documento" => $documentoAsociado]);
            $idAsociado = $consultaAsociado->fetchColumn();

            if ($idAsociado === false) {
                throw new DomainException("No se encontró el asociado de la sesión.", 404);
            }

            $consultaParticipacion = $this->conexion->prepare(
                "SELECT 1 FROM actividades_asociado
                 WHERE idActividad = :idActividad AND idAsociado = :idAsociado"
            );
            $consultaParticipacion->execute([
                ":idActividad" => $idActividad,
                ":idAsociado" => $idAsociado
            ]);

            if ($consultaParticipacion->fetchColumn()) {
                throw new DomainException("Ya estás inscrito en esta actividad.", 409);
            }

            $consultaInsertar = $this->conexion->prepare(
                "INSERT INTO actividades_asociado (idAsociado, idActividad)
                 VALUES (:idAsociado, :idActividad)"
            );
            $consultaInsertar->execute([
                ":idAsociado" => $idAsociado,
                ":idActividad" => $idActividad
            ]);
            $this->conexion->commit();
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }
    }

    public function retirar($idActividad, $documentoAsociado) {
        $this->conexion->beginTransaction();

        try {
            $consultaActividad = $this->conexion->prepare(
                "SELECT estado = 'En curso' AND fechaEvento > CURRENT_DATE AS permiteRetiro
                 FROM actividades
                 WHERE idActividad = :idActividad
                 FOR UPDATE"
            );
            $consultaActividad->execute([":idActividad" => $idActividad]);
            $actividad = $consultaActividad->fetch(PDO::FETCH_ASSOC);

            if (!$actividad) {
                throw new DomainException("No se encontró la actividad.", 404);
            }
            if (!(bool)$actividad["permiteRetiro"]) {
                throw new DomainException("Solo puedes retirarte antes de la fecha de la actividad.", 409);
            }

            $consultaEliminar = $this->conexion->prepare(
                "DELETE participacion FROM actividades_asociado AS participacion
                 INNER JOIN asociados AS asociado
                    ON asociado.idAsociado = participacion.idAsociado
                 WHERE participacion.idActividad = :idActividad
                   AND asociado.documento = :documento"
            );
            $consultaEliminar->execute([
                ":idActividad" => $idActividad,
                ":documento" => $documentoAsociado
            ]);

            if ($consultaEliminar->rowCount() === 0) {
                throw new DomainException("No estás inscrito en esta actividad.", 409);
            }

            $this->conexion->commit();
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }
    }

    public function obtenerActividadPorId($id) {
        $sql = "SELECT * FROM actividades WHERE idActividad = :id";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':id' => $id]);
        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    public function guardar($datos) {
        $sql = "INSERT INTO actividades (nombreActividad, fechaEvento, municipio, totalRecaudado)
                VALUES (:nombreActividad, :fechaEvento, :municipio, :totalRecaudado)";
        $consulta = $this->conexion->prepare($sql);
        return $consulta->execute([
            ':nombreActividad' => $datos['nombreActividad'],
            ':fechaEvento' => $datos['fechaEvento'],
            ':municipio' => $datos['municipio'],
            ':totalRecaudado' => $datos['totalRecaudado']
        ]);
    }

    public function eliminar($idActividad) {
        $sql = "DELETE FROM actividades WHERE idActividad = :idActividad";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':idActividad' => $idActividad]);
        return $consulta->rowCount() > 0; // Retorna true si se elimino alguna fila
    }

    public function editar($datos) {
        $this->conexion->beginTransaction();

        try {
            $consultaActual = $this->conexion->prepare(
                "SELECT estado, totalRecaudado
                 FROM actividades
                 WHERE idActividad = :idActividad
                 FOR UPDATE"
            );
            $consultaActual->execute([':idActividad' => $datos['idActividad']]);
            $actual = $consultaActual->fetch(PDO::FETCH_ASSOC);

            if (!$actual) {
                $this->conexion->commit();
                return false;
            }

            if (
                $actual['estado'] === 'Finalizada' &&
                (
                    $datos['estado'] !== 'Finalizada' ||
                    number_format((float)$actual['totalRecaudado'], 2, '.', '') !==
                        number_format((float)$datos['totalRecaudado'], 2, '.', '')
                )
            ) {
                throw new DomainException(
                    "Una actividad finalizada no puede reabrirse ni cambiar su recaudo.",
                    409
                );
            }

            $consulta = $this->conexion->prepare(
                "UPDATE actividades
                 SET nombreActividad = :nombreActividad,
                     fechaEvento = :fechaEvento,
                     municipio = :municipio,
                     totalRecaudado = :totalRecaudado,
                     estado = :estado
                 WHERE idActividad = :idActividad"
            );
            $consulta->execute([
                ':idActividad' => $datos['idActividad'],
                ':nombreActividad' => $datos['nombreActividad'],
                ':fechaEvento' => $datos['fechaEvento'],
                ':municipio' => $datos['municipio'],
                ':totalRecaudado' => $datos['totalRecaudado'],
                ':estado' => $datos['estado']
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
    
}