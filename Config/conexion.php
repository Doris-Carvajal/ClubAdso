<?php

class Conexion
{
    private $host ="localhost";
    private $db_name="mrclub_doriscarvajal";
    private $user= "root";
    private $contraseña="";

    public function conectar()
    {
        $dsn = "mysql:host={$this-> host};dbname={$this->db_name};charset=utf8mb4";
        
        return new PDO($dsn, $this->user, $this->contraseña,[
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}