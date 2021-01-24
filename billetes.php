<?php

require 'vendor/autoload.php';

$arrFinal = array();
$arrVuelos = array();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        buscaVuelos();
        break;
    case 'POST':
        compraBillete();
        break;
    case 'DELETE':
        borraBillete();
        break;
    case 'PUT':
        modificaBillete();
        break;
}


function buscaVuelos() {
    $cliente = new MongoDB\Client("mongodb://localhost:27017");

    $colección = $cliente->adat_vuelosAmpliacion->vuelos;

    $query = array();
    $count = 0;

    if (isset($_GET['destino'])) {
        $query['destino'] = $_GET['destino'];
    }

    if(isset($_GET['origen'])) {
        $query['origen'] = $_GET['origen'];
    }

    if(isset($_GET['fecha'])) {
        $query['fecha'] = $_GET['fecha'];
    }

    $cursor = $colección->find($query);

    foreach ($cursor as $entry) {
        $arrVuelos[] = $entry;
        $count++;
    }

    if($cursor) {
        if($count > 0) {
            $arrFinal['estado'] = true;
            $arrFinal['encontrados'] = $count;
            $arrFinal['vuelos'] = $arrVuelos;
        } else {
            $arrFinal['estado'] = true;
            $arrFinal['encontrados'] = 0;
        }
    } else {
        $arrFinal['estado'] = false;
        $arrFinal['mensaje'] = "Ha ocurrido un error y no se puede realizar la consulta";
    }

    if($count > 0) {
        $arrFinal['estado'] = true;
        $arrFinal['encontrados'] = $count;
        $arrFinal['vuelos'] = $arrVuelos;
    } else {
        $arrFinal['estado'] = true;
        $arrFinal['encontrados'] = 0;
    }

    $mensajeJSON = json_encode($arrFinal, JSON_PRETTY_PRINT);

    echo "<pre>";
    echo $mensajeJSON;
	echo "</pre>";

}

function compraBillete() {
    $cliente = new MongoDB\Client("mongodb://localhost:27017");

    $coleccion = $cliente->adat_vuelosAmpliacion->vuelos;

    $codigo = array();
    $newPasajero = array();

    $codigo['codigo'] = $_POST['codigo'];
    $miCodigo = $codigo['codigo'];
    $newPasajero['asiento'] = obtenerAsiento($miCodigo);
    $newPasajero['dni'] = $_POST['dni'];
    $newPasajero['apellido'] = $_POST['apellido'];
    $newPasajero['nombre'] = $_POST['nombre'];
    $newPasajero['dniPagador'] = $_POST['dniPagador'];
    $newPasajero['tarjeta'] = $_POST['tarjeta'];
    $newPasajero['codigoVenta'] = generaCodigoVenta();

    $result = $coleccion->updateOne($codigo, array('$push' => array('vendidos' => $newPasajero)));
    
    if($result) {
        $origen;
        $destino;
        $fecha;
        $hora;

        $coleccion->updateOne($codigo, array('$set' => array('plazas_disponibles' => restaAsiento($miCodigo))));

        $result = $coleccion->find($codigo);

        foreach($result as $element) {
            $origen = $element['origen'];
            $destino = $element['destino'];
            $fecha = $element['fecha'];
            $hora = $element['hora'];
        }

        $arrFinal['estado'] = true;
        $arrFinal['codigo'] = $codigo['codigo'];
        $arrFinal['origen'] = $origen;
        $arrFinal['destino'] = $destino;
        $arrFinal['fecha'] = $fecha;
        $arrFinal['hora'] = $hora;
        $arrFinal['asiento'] = $newPasajero['asiento'];
        $arrFinal['dni'] = $newPasajero['dni'];
        $arrFinal['apellido'] = $newPasajero['apellido'];
        $arrFinal['nombre'] = $newPasajero['nombre'];
        $arrFinal['dniPagador'] = $newPasajero['dniPagador'];
        $arrFinal['tarjeta'] = $newPasajero['tarjeta'];
        $arrFinal['codigoVenta'] = $newPasajero['codigoVenta'];

    } else {
        $arrFinal['estado'] = false;
        $arrFinal['mensaje'] = 'No se ha podido realizar la compra';
    }

    $mensajeJSON = json_encode($arrFinal, JSON_PRETTY_PRINT);

    echo "<pre>";
    echo $mensajeJSON;
	echo "</pre>";
}

function borraBillete() {
    $cliente = new MongoDB\Client("mongodb://localhost:27017");

    $coleccion = $cliente->adat_vuelosAmpliacion->vuelos;

    $oldPasajero = array();

    $oldPasajero['codigo'] = $_POST['codigo'];
    $oldPasajero['dni'] = $_POST['dni'];
    $oldPasajero['codigoVenta'] = $_POST['codigoVenta'];
    $miCodigo = $oldPasajero['codigo'];

    devolverAsiento($miCodigo);
    $coleccion->updateOne(['codigo' => $oldPasajero['codigo']], array('$pull' => array('vendidos' => array('dni' => $oldPasajero['dni'], 'codigoVenta' => $oldPasajero['codigoVenta']))));
    $coleccion->updateOne(['codigo' => $miCodigo], array('$set' => array('plazas_disponibles' => sumaAsiento($miCodigo)))); 
}

function modificaBillete() {
    $cliente = new MongoDB\Client("mongodb://localhost:27017");

    $coleccion = $cliente->adat_vuelosAmpliacion->vuelos;

    $codigo = $_POST['codigo'];
    $codigoVenta = $_POST['codigoVenta'];
    $dni = $_POST['dni'];
    $apellido = $_POST['apellido'];
    $nombre = $_POST['nombre'];

    $coleccion->updateOne(['codigo' => $codigo, 'vendidos.codigoVenta' => $codigoVenta], array('$set' => array('vendidos.$.dni' => $dni, 'vendidos.$.apellido' => $apellido, 'vendidos.$.nombre' => $nombre)));
}

function obtenerAsiento($code) {
    $cliente = new MongoDB\Client("mongodb://localhost:27017");

    $coleccion = $cliente->adat_vuelosAmpliacion->vuelos;

    $result = $coleccion->findOne(['codigo' => $code]);

    $asientos = iterator_to_array($result['asientos_disponibles']);

    $miAsiento = array_shift($asientos);

    $coleccion->updateOne(['codigo' => $code], array('$pop' => array('asientos_disponibles' => -1)));

    return $miAsiento;
}

function devolverAsiento($code) {
    $cliente = new MongoDB\Client("mongodb://localhost:27017");

    $coleccion = $cliente->adat_vuelosAmpliacion->vuelos;

    $oldPasajero['codigoVenta'] = $_POST['codigoVenta'];
    $miCodigoVenta = $oldPasajero['codigoVenta'];
    var_dump($miCodigoVenta);

    $result = $coleccion->findOne(['codigo' => $code]);
    $asiento;
    $arrayVendidos = iterator_to_array($result['vendidos']);

    foreach($arrayVendidos as $entry) {
        $value = (array)$entry;
        var_dump($value);
        if($value['codigoVenta'] == $miCodigoVenta) {
            $asiento = $value['asiento'];
            var_dump($asiento);
        }
    }
    $coleccion->updateOne(['codigo' => $code], array('$push' => array('asientos_disponibles' => $asiento)));
}

function generaCodigoVenta() {
    $permitted_chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $chain = substr(str_shuffle($permitted_chars), 0, 10);
    return $chain;
}

function restaAsiento($code) {
    $cliente = new MongoDB\Client("mongodb://localhost:27017");

    $coleccion = $cliente->adat_vuelosAmpliacion->vuelos;

    $result = $coleccion->find(['codigo' => $code]);
    $plzsDisp = '';

    foreach ($result as $entry) {
        $plzsDisp = $entry['plazas_disponibles'];
    }

    $num = intval($plzsDisp) - 1;

    return $num;
}

function sumaAsiento($code) {
    $cliente = new MongoDB\Client("mongodb://localhost:27017");

    $coleccion = $cliente->adat_vuelosAmpliacion->vuelos;

    $result = $coleccion->find(['codigo' => $code]);
    $plzsDisp = '';

    foreach($result as $entry) {
        $plzsDisp = $entry['plazas_disponibles'];
    }

    $num = intval($plzsDisp) + 1;

    return $num;
}

?>