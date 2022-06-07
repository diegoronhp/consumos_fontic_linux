<?php 
//error_reporting(E_ALL);
error_reporting(0);

require '../model/bd/configs.php';
require_once('../vendor/php-excel-reader/excel_reader2.php');
require_once('../vendor/SpreadsheetReader.php');
require '../controller/ConsumoController.php';
require '/data/sites/www/consumos_fontic/views/envio_correos.php';

if((isset($_FILES["nombre_archivo"]))&&(isset($_POST["tipo_insercion"]))){
    //echo "DETECTO EL EVENTO POST DEL FORMULARIO EN EL QUE SE IMPORTA EL ARCHIVO DE CONSUMO"."<br>";
    $nombre_archivo = $_FILES["nombre_archivo"]["name"];
    $nombre_temporal = $_FILES["nombre_archivo"]["tmp_name"];
    $tipo_insercion = $_POST["tipo_insercion"];
    $tipo_archivo = 0; //ARCHIVO PLATAFORMA
    $tabla_bd = "archivos_tigo";
    $dir_raiz = RAIZ;
    //$targetPath = 'C:/wamp64/www/consumos_fontic/importados_tigo/';
    $targetPath = $dir_raiz.'importados_tigo/';
    //$targetPath_Rectif = 'C:/wamp64/www/consumos_fontic/importados_tigo_rectif/';
    $targetPath_Rectif = $dir_raiz.'importados_tigo_rectif/';
    //echo "nombre_archivo = ".$nombre_archivo."<br>";
    //echo "nombre_temporal = ".$nombre_temporal."<br>";
    //echo "tipo_insercion = ".$tipo_insercion."<br>";
    //echo "tipo_archivo = ".$tipo_archivo."<br>";
    //echo "tabla_bd = ".$tabla_bd."<br>";
    //echo "dir_raiz = ".$dir_raiz."<br>";
    //echo "targetPath = ".$targetPath."<br>";
    //echo "targetPath_Rectif = ".$targetPath_Rectif."<br>";
    //echo "ANTES DE LA INTERRUPCION"."<br>";
    sleep(5);
    //echo "DESPUES DE LA INTERRUPCION"."<br>";

    /*COMPROBACION DE LA EXTENSION DEL ARCHIVO IMPORTADO CON LA LIBRERIA*/
    $allowedFileType = ['application/vnd.ms-excel', 'text/csv', 'text/xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    if (in_array($_FILES["nombre_archivo"]["type"], $allowedFileType)){
        //echo "EL ARCHIVO **SI** CUMPLE CON LA EXTENSION REQUERIDA"."<br>";

        //SI EL CONTENIDO DEL ARCHIVO VA A SER INSERTADO POR PRIMERA VEZ
        if($tipo_insercion == 0){
            //echo "EL ARCHIVO VA A SER INSERTADO POR PRIMERA VEZ"."<br>";
            $existe = buscar_nombre_archivo_consumo($nombre_archivo,$tabla_bd);

            if($existe == false){
                //echo "EL ARCHIVO **NO** EXISTE EN LA BD, ENTONCES SE PUEDEN INSERTAR LOS REGISTROS NUEVOS"."<br>";
                $insertado = insertar_nombre_archivo_consumo_tigo($nombre_archivo,$tipo_archivo,$tipo_insercion);

                if($insertado){
                    //echo "EL NUEVO ARCHIVO **SI** FUE INSERTADO EN LA BD"."<br>";
                    $mensaje = "El archivo (".$nombre_archivo.") ha sido importado con exito. Las estadisticas de la importacion de este archivo son las siguientes: ";
                    $ruta_archivo = $targetPath.$nombre_archivo;
                    $movido = move_uploaded_file($nombre_temporal, $ruta_archivo);
                    //echo "EL ARCHIVO HA SIDO MOVIDO? ";
                    //echo $movido == true ? "TRUE"."<br>":"FALSE"."<br>";
                    $mensaje .= insertar_registros_consumos_archivo($targetPath,$nombre_archivo,$tipo_insercion);
                    enviar_correo_usuario($mensaje);
                }else{
                    //echo "EL NUEVO ARCHIVO **NO** FUE INSERTADO EN LA BD";
                    $mensaje = "El archivo (".$nombre_archivo.") no pudo ser insertado en la base de datos, por favor intente de nuevo";
                }

            }else{
                //echo "EL ARCHIVO **SI** EXISTE EN LA BD Y POR LO TANTO YA FUERON INSERTADOS REGISTROS, ENTONCES DEBO NOTIFICAR EL ERROR AL USUARIO"."<br>";
                $mensaje = "El archivo (".$nombre_archivo.") ya existe en la base de datos, por favor realice la importacion de un archivo nuevo";
            }
        }


        //SI EL CONTENIDO DEL ARCHIVO VA A SER RECTIFICADO DESPUES DE HABER SIDO INSERTADO PREVIAMENTE
        if($tipo_insercion == 1){
            //echo "EL ARCHIVO VA A SER RECTIFICADO DESPUES DE SU INSERCION PREVIA"."<br>";
            $existe = buscar_nombre_archivo_consumo_tigo_rectificar($nombre_archivo,$tipo_archivo);
            if($existe[0] == false){
                //echo "EL ARCHIVO **NO** EXISTE EN LA BD, POR LO TANTO NO HAN SIDO INSERTADOS REGISTROS PREVIAMENTE CON ESTE ARCHIVO, ENTONCES DEBO NOTIFICAR EL ERROR AL USUARIO"."<br>";
                $mensaje = "El archivo (".$nombre_archivo.") debe existir en la base de datos para poder rectificar los registros previamente cargados";
            }else{
                //echo "EL ARCHIVO **SI** EXISTE EN LA BD, POR LO TANTO PUEDO ELIMINAR LOS REGISTROS INSERTADOS PREVIAMENTE CON EL MISMO ARCHIVO"."<br>";
                $id_ini_datos = $existe[1];
                $id_fin_datos = $existe[2];
                $id_ini_voz = $existe[3];
                $id_fin_voz = $existe[4];
                $id_archivo = $existe[5];
                //echo "LOS REGISTROS POR ELIMINAR EN LA TABLA DE CONSUMOS DE DATOS ESTAN EN EL RANGO desde = ".$id_ini_datos." / hasta = ".$id_fin_datos."<br>";
                //echo "LOS REGISTROS POR ELIMINAR EN LA TABLA DE CONSUMOS DE VOZ ESTAN EN EL RANGO desde = ".$id_ini_voz." / hasta = ".$id_fin_voz."<br>";
                //echo "EL ID DEL ARCHIVO DE TIGO QUE VA A SER RECTIFICADO ES id_archivo = ".$id_archivo."<br>";
                $rectificacion = eliminar_registros_por_rectificar_archivo_tigo($id_ini_datos,$id_fin_datos,$id_ini_voz,$id_fin_voz,$id_archivo);
                $insertado = insertar_nombre_archivo_consumo_tigo_rectificado($nombre_archivo,$tipo_archivo,$tipo_insercion);

                if($insertado){
                    //echo "EL ARCHIVO RECTIFICADO **SI** FUE INSERTADO EN LA BD"."<br>";
                    $mensaje = "El archivo (".$nombre_archivo.") ha sido importado con exito para su rectificacion. Las estadisticas de la importacion de este archivo son las siguientes: ";
                    $mensaje .= $rectificacion;
                    $ruta_archivo = $targetPath_Rectif.$nombre_archivo;
                    move_uploaded_file($nombre_temporal, $ruta_archivo);
                    $mensaje .= insertar_registros_consumos_archivo($targetPath_Rectif,$nombre_archivo,$tipo_insercion);
                    enviar_correo_usuario($mensaje);
                }else{
                    //echo "EL ARCHIVO RECTIFICADO **NO** FUE INSERTADO EN LA BD";
                    $mensaje = "El archivo (".$nombre_archivo.") no pudo ser insertado en la base de datos, por favor intente de nuevo";
                }
            }
        }

    }else{
        //echo "EL ARCHIVO **NO** CUMPLE CON LA EXTENSION REQUERIDA"."<br>";
        $mensaje = "El archivo (".$nombre_archivo.") no tiene extension .csv, por favor realice la importacion con un archivo valido";
    }

    $respuesta = array("mensaje"=>$mensaje);
    echo json_encode($respuesta);
}

?>