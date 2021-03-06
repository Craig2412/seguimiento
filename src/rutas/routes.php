<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app = new \Slim\App;

require __DIR__ . '/../dotenv/dotenvRun.php';
require __DIR__ . '/../jwtMiddleware/tuupola.php';
require __DIR__ . '/../class/auth.php';
require __DIR__ . '/../funciones/funciones.php';
require __DIR__ . '/../class/classRegistros.php';
require __DIR__ . '/../class/classPaginador.php';
require __DIR__ . '/../config/db.php';

$app->add(Tuupola());



//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//////////////////////////////* Usuario *////////////////////////////////////
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||

$app->get('/user/verification', function (Request $request, Response $response) {

    return "OK";
    
});


$app->group('/api/user/', function () use ($app) {
    
    $app->post('authenticate', function (Request $request, Response $response) {
        $body = json_decode($request->getBody());
    
        $sql = "SELECT `usuarios`.*
                FROM `usuarios`";
        $db = new DB();
        $resultado = $db->consultaAll('usuarios', $sql);
        
        
        $body=json_decode($body->body);
        
        foreach ($resultado as $key => $user) {
        if ($user['nick'] == $body->user && $user['pass'] == $body->pass) {
            $current_user = $user;
        }}
    
        if (!isset($current_user)) {
            echo json_encode("No user found");
        } else{
    
            $sql = "SELECT * FROM tokens
                 WHERE id_usuario_token  = ?";
    
            try {
                $db = new DB();
                $token_from_db = $db->consultaAll('usuarios', $sql, [$current_user['id_usuario']], 'objeto');
                
                $db = null;
                if ($token_from_db) {
                    return $response->withJson([
                    "Token" => $token_from_db->token,
                    "User_render" =>$current_user['id_rol'], 
                   // "Hidrologica" =>$current_user
                    ]);
                }    
                }catch (Exception $e) {
                $e->getMessage();
                }
    
            if (count($current_user) != 0 && !$token_from_db) {
    
    
                 $data = [
                    "user_login" => $current_user['nick'],
                    "user_id"    => $current_user['id_usuario'],
                    "user_estado"    => $current_user['id_estado'],
                    "user_municipio"=>$current_user['id_municipio'],
                    "user_parroquia"=>$current_user['id_parroquia'],
                    "user_rol"    => $current_user['id_rol']
                ];
    
                 try {
                    $token=Auth::SignIn($data);
                 } catch (Exception $e) {
                     echo json_encode($e);
                 }
    
                  $sql = "INSERT INTO tokens (id_usuario_token, token)
                      VALUES (?, ?)";
                  try {
                        $hoy = (date('Y-m-d', time()));
                        $db = new DB();
                        $db = $db->consultaAll('usuarios', $sql, [$current_user['id_usuario'], $token]);
                        
                        
                        return $response->withJson([
                        "Token" => $token,
                        "User_render" =>$current_user['id_rol']
                        ]);
     
                  } catch (PDOException $e) {
                      echo '{"error":{"text":' . $e->getMessage() . '}}';
                  }
             }
        }
    
    });

    $app->post('create', function (Request $request, Response $response) { 
        $scope=$request->getAttribute('jwt')["data"]->scope[0];
        if (userVerification($scope) !== false) {
            $datos = json_decode($request->getBody());
            $pass = generar_password_complejo(8);
                
            try {
                $sql = "INSERT INTO `usuarios`(`id_usuario`, `nick`, `email`, `pass`, `id_rol`, `id_acceso`, `id_estado`, id_municipio, id_parroquia) VALUES (null, 0, ?, ?, ?, ?, ?, ?, ?)";
                $db = new DB();
                $resultado = $db->consultaAll('usuarios', $sql, [$datos->email, $pass, $datos->id_rol, $datos->id_acceso, $datos->id_estado, $datos->id_municipio, $datos->id_parroquia]);
                if ($resultado) {
                    $sql = "UPDATE `usuarios` SET `nick`=? WHERE usuarios.id_usuario = ?";
                    $nick = $datos->nick."-".$resultado->insert_id;
                    $resultado2 = $db->consultaAll('usuarios', $sql, [$nick, $resultado->insert_id]);
                    $retorno = [
                        "id"=>$resultado->insert_id,
                        "nick"=>$nick,
                        "pass"=>$pass, 
                        "rol"=>$datos->id_rol
                    ];
                    return $response->withJson($retorno); 
                }
                //return $response->withJson($resultado);
                } 
            catch (MySQLDuplicateKeyException $e) {
                $e->getMessage();
            }
            catch (MySQLException $e) {
                $e->getMessage();
            }
            catch (Exception $e) {
                $e->getMessage();
            }
        } else {
            return $response->withStatus(401);
        }
    });

    $app->post('info/user', function (Request $request, Response $response) { 
        $body = json_decode($request->getBody());
        $nick = json_decode($body->body);
        
        try {
            $sql = "SELECT usuarios.id_usuario, usuarios.id_rol, usuarios.nick, usuarios.id_estado FROM usuarios WHERE usuarios.nick = ?";
            $db = new DB();
            $resultado = $db->consultaAll('usuarios', $sql, [$nick], 'objeto');
            $array = ["id" => $resultado->id_usuario, "scope" => $resultado->id_rol, "nick"=>$resultado->nick, "estado"=>$resultado->id_estado];
            return $response->withJson($array);          
            
            } 
        catch (MySQLDuplicateKeyException $e) {
            $e->getMessage();
        }
        catch (MySQLException $e) {
            $e->getMessage();
        }
        catch (Exception $e) {
            $e->getMessage();
        }
    });
});


//||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//////////////////////////////* GET *///////////////////////////////////////||
//||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||


//||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//||||||||||||||||||||||||||||||||||PAGINADOR|||||||||||||||||||||||||||||||||||||||||
//||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||


$app->get('/api/reportes/all[/{params:.*}]', function (Request $request, Response $response, $args) {

    /*
        ruta para obtener los reportes de el sistema
        1) existen tres parametros opcionales en la ruta, params[1] es el numero de paginacion, params[2] es el tipo de busqueda que vas a hacer si es el caso, params[3] es el id que se utiliza para formular la busqueda
        
    */

    $idEstado=$request->getAttribute('jwt')["data"]->user_estado;
    $scope=$request->getAttribute('jwt')["data"]->scope[0];
    $id=$request->getAttribute('jwt')["data"]->user_id;
    
    
    /*
    Obteniendo user_hidrologica del usuario para el primer render de la paginacion, se obtiene por el Token
    1) se optiene el token para asi poder verificar o dar la informacion en base a lo que presenta el usuario en su informacion personal
    2) en esta opcion se optione el user_hidrologica que es el id de la hidrologica del usuario
    3) es totalmente necesario para el funcionamiento de la ruta
    */

    if (!empty($args['params'])) { //validamos si la ruta tiene algun valor opcional en el url

        ;
        $params = EliminarBarrasURL($args['params']);
        
        $tipoConsulta = null;
        if (count($params)>2) {
            $params[2] = trim(rawurldecode($params[2]), ' ');
            //comprobamos que en el params[1] venga el valor de busqueda
            if ($params[1] === "busqueda") {
                $array = [];
                
                    // en caso contrario se pasa nada mas el primer parametro, y se crea un array con los valores duplicados para enviarlos a la consulta
                    $tipoConsulta = ExtraerConsultaParametro($params[1]);
                    for ($i=0; $i < count($tipoConsulta); $i++) {
                        $param = urldecode($params[2]);
                        $param = '%'.$params[2].'%';               
                        array_push($array, $param);
                    }
                    $params[2]=$array;
                    
                
            }else {
                $tipoConsulta = ExtraerConsultaParametro($params[1]);
                $params[2]=[ucfirst($params[2])];
                
            }
                
        }
        if ($tipoConsulta !== null) {
            $where = CondicionalMYSQL($idEstado, $tipoConsulta, $params[2], $scope);
        }else {
            $where = CondicionalMYSQL($idEstado, null, null, $scope);
        }

        $sql = "SELECT COUNT(mta.id_mta) as Paginas
                FROM `mta` 
                LEFT JOIN `datos_geograficos` ON `mta`.`id_datos_geograficos` = `datos_geograficos`.`id_datos_geograficos` 
                LEFT JOIN `datos_mta` ON `mta`.`id_datos_mta` = `datos_mta`.`id_datos_mta` 
                LEFT JOIN `municipios` ON `datos_geograficos`.`id_municipio` = `municipios`.`id_municipio` 
                LEFT JOIN `estados` ON `datos_geograficos`.`id_estado` = `estados`.`id_estado` 
                LEFT JOIN `parroquias` ON `datos_geograficos`.`id_parroquia` = `parroquias`.`id_parroquia`  
                LEFT JOIN `estatus` ON `mta`.`id_estatus` = `estatus`.`id_estatus`
                {$where}";
    
        
        if ($where !== "") {
            if ($tipoConsulta!==null) {
                if ($idEstado===25) {
                    $db = new DB();
                    $datos = array($db->consultaAll('mapa', $sql, $params[2])[0]['Paginas'],$params[0]);
                    
                    
                }else if($idEstado !== 25 && userVerification($scope) === false){
                    $db = new DB();
                    $param= [$id, ...$params[2]];
                    $datos = array($db->consultaAll('mapa', $sql, $param)[0]['Paginas'], $params[0]);
                    
                }else{
                    $db = new DB();
                    $param= [$idEstado, ...$params[2]];
                    $datos = array($db->consultaAll('mapa', $sql, $param)[0]['Paginas'], $params[0]);
                    
                }
                
            }else if(userVerification($scope) === false) {
                $db = new DB();
            
                $datos = array($db->consultaAll('mapa', $sql, [$id])[0]['Paginas'],$params[0]);
                
            } else{
                $db = New DB();
                $datos = array($db->consultaAll('mapa', $sql, [$idEstado])[0]['Paginas'],$params[0]); 
            }
        }else {
            $db = new DB();
            $datos = array($db->consultaAll('mapa',$sql)[0]['Paginas'], $params[0]);
            
        }
        
        if ($params[0] < 1 || $params[0] > ceil($datos[0] / 20)){
            return "La pagina solicitada no existe";
        }else{
            $paginador = New paginadorMesa($tipoConsulta!==null?$params[2]:null, $idEstado, $tipoConsulta);
            return json_encode($paginador->paginadorMesa($datos, $scope, $id));             
        }
    }else {

        $where = CondicionalMYSQL($idEstado, null, null, $scope);
        $sql = "SELECT mta.id_mta, estatus.estatus, datos_mta.*,
        datos_geograficos.*, estados.estado, municipios.municipio, 
        parroquias.parroquia, sector.sector, datos_servicio.*, 
        respuesta.respuesta, frecuencia_servicio.frecuencia_servicio, tipo_registro.tipo_registro,
        duracion_servicio.duracion_servicio
        FROM mta
            LEFT JOIN estatus ON mta.id_estatus = estatus.id_estatus 
            LEFT JOIN datos_mta ON mta.id_datos_mta = datos_mta.id_datos_mta 
            LEFT JOIN datos_geograficos ON mta.id_datos_geograficos = datos_geograficos.id_datos_geograficos 
            LEFT JOIN estados ON datos_geograficos.id_estado = estados.id_estado 
            LEFT JOIN municipios ON datos_geograficos.id_municipio = municipios.id_municipio 
            LEFT JOIN `tipo_registro` ON `mta`.`tipo_registro` = `tipo_registro`.`id_tipo_registro`
            LEFT JOIN parroquias ON datos_geograficos.id_parroquia = parroquias.id_parroquia 
            LEFT JOIN sector ON datos_geograficos.id_sector = sector.id_sector 
            LEFT JOIN datos_servicio ON mta.id_datos_servicio = datos_servicio.id_datos_servicio 
            LEFT JOIN respuesta ON datos_servicio.agua_potable = respuesta.id_respuesta 
            LEFT JOIN frecuencia_servicio ON datos_servicio.id_frecuencia_servicio = frecuencia_servicio.id_frecuencia_servicio 
            LEFT JOIN duracion_servicio ON datos_servicio.id_duracion_servicio = duracion_servicio.id_duracion_servicio {$where}";

        if ($where !== "") {
            if(userVerification($scope) === false) {
                $db = new DB();
                $datos = $db->consultaAll('mapa', $sql, [$id]);
            } else{
                $db = New DB();
                $datos = $db->consultaAll('mapa', $sql, [$idEstado]);                
            }
        }else {
            $db = new DB();
            $datos = $db->consultaAll('mapa',$sql);
            
        }

        $sql2 = "SELECT COUNT(consejo_comunal.id_consejo_comunal) AS Consejos, SUM(consejo_comunal.poblacion) AS Poblacion
        FROM `mta` 
            LEFT JOIN `datos_mta` ON `mta`.`id_datos_mta` = `datos_mta`.`id_datos_mta` 
            LEFT JOIN `consejo_comunal` ON `consejo_comunal`.`id_datos_mta` = `datos_mta`.`id_datos_mta`  
            WHERE mta.id_mta = ?";

        $sql3 = "SELECT COUNT(voceros.id_voceros) AS Voceros
        FROM `mta`
        LEFT JOIN `datos_mta` ON `mta`.`id_datos_mta` = `datos_mta`.`id_datos_mta` 
        LEFT JOIN `voceros` ON `voceros`.`id_datos_mta` = `datos_mta`.`id_datos_mta` 
        WHERE mta.id_mta = ?";

            
        
        for ($i=0; $i < count($datos); $i++) { 
            $ar = $db->consultaAll('mapa',$sql2, [$datos[$i]['id_mta']]);
            $er = $db->consultaAll('mapa',$sql3, [$datos[$i]['id_mta']]);
            $datos[$i]['poblacion']= $ar[0]['Poblacion'];
            $datos[$i]['consejos']= $ar[0]['Consejos'];
            $datos[$i]['voceros']= $er[0]['Voceros'];
            
        }

        
        return json_encode($datos);      
    }
        
});



//////////////////////////////// DESPLEGABLES ///////////////////////////////////////////

//////////////////////////////////////ESTADOS
$app->get('/api/desplegables/estados[/{id}]', function (Request $request, Response $response) {
    $id = $request->getAttribute('id');
    $db = New DB();
    if ($id) {
        $sql = "SELECT estados.id_estado, estados.estado
        FROM estados where estados.id_estado = ?";
        $estado= $db->consultaAll('mapa',$sql, [$id]);
        unset($estado[24]);
        return json_encode($estado);
    } else{
        $sql = "SELECT estados.id_estado, estados.estado
        FROM estados";
        $esta= $db->consultaAll('mapa',$sql);
        unset($esta[24]);
        return json_encode($esta);
    }
    
        
});
 
               
//////////////////////////////////////MUNICIPIOS
        $app->get('/api/desplegables/municipios/{id_estado}', function (Request $request, Response $response) {
            $id = $request->getAttribute('id_estado');
            
            
            $sql = "SELECT municipios.id_municipio, municipios.municipio, estados.id_estado
                FROM municipios 
                LEFT JOIN estados ON municipios.id_estado = estados.id_estadO
                WHERE municipios.id_estado = ?";
                $db = New DB();
            
            return json_encode($db->consultaAll('mapa',$sql,[$id]));
        });
        
        
        
/////////////////////////////////////PARROQUIAS
    $app->get('/api/desplegables/parroquias/{id_municipio}', function (Request $request, Response $response) {
        $id = $request->getAttribute('id_municipio');
        
        
        $sql = "SELECT parroquias.id_parroquia, parroquias.parroquia, municipios.id_municipio 
                FROM parroquias
                LEFT JOIN municipios ON parroquias.id_municipio = municipios.id_municipio 
                WHERE municipios.id_municipio = ?";
                    $db = New DB();
        
                    return json_encode($db->consultaAll('mapa',$sql,[$id]));
                    
                    
    });



    $app->get('/api/desplegables/pozos/{id_estado}', function (Request $request, Response $response) {
        $id = $request->getAttribute('id_estado');
                
        $sql = "SELECT `pozo`.*, `estados`.`estado`
        FROM `pozo` 
            LEFT JOIN `estados` ON `pozo`.`id_estado` = `estados`.`id_estado`
            WHERE estados.id_estado = ?";
                    $db = New DB();
        
             return json_encode($db->consultaAll('mapa',$sql,[$id]));
                    
                    
    });


    $app->get('/api/desplegables/brippas/{id_estado}', function (Request $request, Response $response) {
        $id = $request->getAttribute('id_estado');
                
        $sql = "SELECT `brippas`.*, `estados`.`estado`
        FROM `brippas` 
            LEFT JOIN `estados` ON `brippas`.`id_estado` = `estados`.`id_estado`
            WHERE estados.id_estado = ?";
                    $db = New DB();
        
             return json_encode($db->consultaAll('mapa',$sql,[$id]));
                    
                    
    });


    $app->get('/api/desplegables/sistemas', function (Request $request, Response $response) {
                
        $sql = "SELECT * FROM `sistemas`";
                    $db = New DB();
        
             return json_encode($db->consultaAll('mapa',$sql));
                
    });

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////                   


$app->get('/api/reportes/emp[/{params:.*}]', function (Request $request, Response $response, $args) {
    $params = EliminarBarrasURL($args['params']);   
    /*
    0 - Tipo de formulario ($Tabla a consultar)
    1 - Estado
    2 - Municipio
    3 - Parroquia     */    
    $db = New DB();
    /**/
    $TablaConsultar = [
        'produccion',//0 
        'rehabilitacion_pozo',//1 
        'fugas',//2 
        'tomas_ilegales',//3 
        'reparaciones_brippas',//4
        'afectaciones',//5 
        'operatividad_abastecimiento',//6
        'pozo',//7
        'brippas',//8
        'sistemas'//9
    ];
    
    if (!empty($params[0]) && is_numeric($params[0])) {
        if (($params[0] >= 0) && ($params[0] <= 6)) {
            $params[0] = $params[0] + 0;
            $valorSQL = $TablaConsultar[$params[0]];
        }else {
            return 'TABLA A CONSULTAR NO VALIDA';
        }
    }else {
        return 'PARAMETROS DE BUSQUEDA NO VALIDOS';
    }
    

    if (count($params) === 2) {
        
        if (($params[0] === 1) || ($params[0] === 5) || ($params[0] === 4)) {
            switch ($params[0]) {
                case 1:
                    $valorSQL2 = $TablaConsultar[7];
                    break;

                case 5:
                    $valorSQL2 = $TablaConsultar[9];
                    break;

                case 4:
                    $valorSQL2 = $TablaConsultar[8];
                    break;
                
                default:
                $valorSQL2 = null;
                    break;
            }
            $sql = "SELECT $valorSQL.*, `estados`.`estado`, $valorSQL2.nombre
                    FROM $valorSQL 
                        LEFT JOIN $valorSQL2 ON $valorSQL.id_$valorSQL2 = $valorSQL2.id
                        LEFT JOIN `estados` ON $valorSQL2.id_estado = `estados`.`id_estado`
                        WHERE $valorSQL2.id_estado = ?";
        }else {

            $sql = "SELECT $valorSQL.*, `estados`.`estado`
                    FROM $valorSQL 
                        LEFT JOIN `estados` ON $valorSQL.`id_estado` = `estados`.`id_estado`
                        WHERE $valorSQL.id_estado = ?";
        
            $reporte= $db->consultaAll('mapa',$sql, [$params[1]]);
            return json_encode($reporte);
        }
        
    }elseif (count($params) === 3) {

        
    }elseif (count($params) === 4) {
        
    }else {
        return 'FALTAN O SOBRAN INSERTAR PARAMETROS PARA SOLICITAR EL REPORTE';
    }
    
        
});

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$app->get('/api/reportes/fecha[/{params:.*}]', function (Request $request, Response $response, $args) {
    $params = EliminarBarrasURL($args['params']);
    /*params = 
    0 - Tipo de formulario
    1 - Fecha de Inicio
    2 - Fecha Final
        ///////////////
    3 - Si el reporte es por estados aca va el id del estado solicitado
    */
    $TablaConsultar = [
    'produccion',//0 
    'rehabilitacion_pozo',//1 
    'fugas',//2 
    'tomas_ilegales',//3 
    'reparaciones_brippas',//4
    'afectaciones',//5 
    'operatividad_abastecimiento',//6
    'pozo',//7
    'brippas',//8
    'sistemas'//9
];


    
    $db = New DB();

    

    if (!empty($params[0]) && is_numeric($params[0])) {
        if (($params[0] >= 0) && ($params[0] <= 6)) {
            $params[0] = $params[0] + 0;
        }else {
            return 'TABLA A CONSULTAR NO VALIDA';
        }
    }else {
        return 'PARAMETROS DE BUSQUEDA NO VALIDOS';
    }
    

    if (count($params) === 3) {

        if ( ($params[0] === 1) || ($params[0] === 5) || ($params[0] === 4)) {
            $valorSQL = $TablaConsultar[$params[0]];
        
            switch ($params[0]) {
                case 1:
                    $valorSQL2 = $TablaConsultar[7];
                    break;

                case 5:
                    $valorSQL2 = $TablaConsultar[9];
                    break;

                case 4:
                    $valorSQL2 = $TablaConsultar[8];
                    break;
                
                default:
                $valorSQL2 = null;
                    break;
            }

            $sql = "SELECT $valorSQL.*, `reporte`.fecha , $valorSQL2.nombre, estados.estado
            FROM $valorSQL
                LEFT JOIN reporte ON $valorSQL.id = `reporte`.`id`  
                LEFT JOIN $valorSQL2 ON $valorSQL.id_$valorSQL2 = $valorSQL2.id
                LEFT JOIN estados ON $valorSQL2.id_estado = estados.id_estado
                WHERE reporte.fecha BETWEEN ? AND ?";

                $reporte= $db->consultaAll('mapa',$sql, [$params[1], 
                                                         $params[2]]);
                return json_encode($reporte);
                            
        }else {

            $valorSQL = $TablaConsultar[$params[0]];

            $sql = "SELECT $valorSQL.*, `reporte`.fecha, estados.estado
            FROM $valorSQL
                LEFT JOIN `reporte` ON $valorSQL.id = `reporte`.`id`                 
                LEFT JOIN `estados` ON $valorSQL.id_estado = estados.id_estado 
                WHERE reporte.fecha BETWEEN ? AND ?";

                $reporte= $db->consultaAll('mapa',$sql, [$params[1], 
                                                         $params[2]]);
                return json_encode($reporte);
        }
      

    }elseif (count($params) === 4) {
        
        if ( ($params[0] === 1) || ($params[0] === 5) || ($params[0] === 4)) {
            $valorSQL = $TablaConsultar[$params[0]];

            switch ($params[0]) {
                case 1:
                    $valorSQL2 = $TablaConsultar[7];
                    break;

                case 5:
                    $valorSQL2 = $TablaConsultar[9];
                    break;

                case 4:
                    $valorSQL2 = $TablaConsultar[8];
                    break;
                
                default:
                $valorSQL2 = null;
                    break;
            }

            $sql = "SELECT $valorSQL.*, `reporte`.fecha , $valorSQL2.nombre, estados.estado
            FROM $valorSQL
                LEFT JOIN reporte ON $valorSQL.id = `reporte`.`id`  
                LEFT JOIN $valorSQL2 ON $valorSQL.id_$valorSQL2 = $valorSQL2.id
                LEFT JOIN estados ON $valorSQL2.id_estado = estados.id_estado
                WHERE $valorSQL2.id_estado = ? AND reporte.fecha BETWEEN ? AND ?";

            $reporte= $db->consultaAll('mapa',$sql, [$params[3],
                                                     $params[1], 
                                                     $params[2]]);
            return json_encode($reporte);

        }else{

        $valorSQL = $TablaConsultar[$params[0]];

        $sql = "SELECT $valorSQL.*, `reporte`.fecha, estados.estado
        FROM $valorSQL
            LEFT JOIN `reporte` ON $valorSQL.id = `reporte`.`id`                 
            LEFT JOIN `estados` ON $valorSQL.id_estado = estados.id_estado 
            WHERE $valorSQL.id_estado = ? AND reporte.fecha BETWEEN ? AND ?";

            $reporte= $db->consultaAll('mapa',$sql, [$params[3],
                                                     $params[1], 
                                                     $params[2]]);
            return json_encode($reporte);
        }
        

    }else{
        return 'FALTAN INSERTAR PARAMETROS PARA SOLICITAR EL REPORTE';
}     
});
////////////////////////////////////////////////////FIN/////////////////////////////////////////////////////// 





//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
///////////////////////////////* POST *//////////////////////////////////////
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||

$app->post('/api/formulaios/reportes', function (Request $request, Response $response) {
    $body = json_decode($request->getBody());
    $TablaConsultar = ['produccion','rehabilitacion_pozo','fugas','tomas_ilegales','reparaciones_brippas','afectaciones','operatividad_abastecimiento','pozo','brippas','sistemas'];
    $tablasInsertar=[
    ['`metros_cubicos`', '`id_estado`', '`id_reporte`'],
    ['`lps`', '`id_pozo`', '`id_reporte`'],
    ['`nombre_aduccion`', '`id_estado`', '`id_municipio`', '`id_parroquia`', '`sector`' , '`cantidad_fugas_reparadas`','`id_reporte`'],
    ['`nombre_aduccion`', '`id_estado`', '`id_municipio`', '`id_parroquia`', '`sector`' , '`cantidad_tomas_eliminadas`', '`lps`', '`id_reporte`'],
    ['`averias_levantadas_ap`', '`averias_levantadas_ap`', '`averias_levantadas_as`', '`averias_corregidas_as`', '`id_brippas`' , '`id_reporte`'],
    ['`id_estado`', '`cantidad`', '`horas_sin_servicio`', '`equipos_danados`', '`id_infraestructura`' , '`id_sistema`', '`id_reporte`'],
    ['`id_estado`', '`porcentaje_operatividad`', '`porcentaje_abastecimiento`', '`observacion`', '`id_reporte`'],
    ['`nombre`', '`operatividad`', '`lps`', '`id_estado`', '`id_municipio`', '`id_parroquia`', '`sector`', '`poblacion`'],
    ['`nombre`', '`id_estado`', '`id_municipio`', '`id_parroquia`', '`sector`', '`cantidad_integrantes`', '`dotacion`', '`formacion`'],
    ['`nombre`', '`cantidad_pp`', '`cantidad_eb`', '`cantidad_pozo`']

    ];


    
    
    if (!empty($body->{'valores_insertar'})) {
        if (end($body->{'valores_insertar'}) === "reporte") {
            if (count($tablasInsertar[$body->{'tipo_formulario'}]) !== (count($body->{'valores_insertar'}) - 2 )) {
                return 'LOS VALORES NO COINCIDEN CON EL TIPO DE FORMULARIO1';                
            }
            
        }else{
            if (count($tablasInsertar[$body->{'tipo_formulario'}]) !== (count($body->{'valores_insertar'}))) {
                return 'LOS VALORES NO COINCIDEN CON EL TIPO DE FORMULARIO';
            }
        }        
    }else{
        return 'NO HAY VALORES PARA INSERTAR';
    }

    
    if (isset($body->{'tipo_formulario'})) {
        
        
        if (($body->{'tipo_formulario'} >= 0) AND ($body->{'tipo_formulario'} <=6)) {
            $sqlreporte = "INSERT INTO `reporte` (`id`, `ubicacion_reporte`, `fecha`) VALUES (NULL, ?, ?)";
            $sqlFormulario = generarSqlRegistro($tablasInsertar[$body->{'tipo_formulario'}], $body->{'tipo_formulario'}, $TablaConsultar[$body->{'tipo_formulario'}]);

            $values = array_slice($body->{'valores_insertar'},2,-1);
            $db = new DB();

            $stmt = $db->consultaAll('mapa', $sqlreporte, [$body->{'valores_insertar'}[0], $body->{'valores_insertar'}[1]]);
            
            if ($stmt) {
                array_push($values,$stmt->{'insert_id'});
                $stmt2 = $db->consultaAll('mapa', $sqlFormulario, $values);
                if ($stmt2) {
                $db = null;
                return 'REGISTRO EXITOSO, REPORTE NUMERO: '.$stmt->{'insert_id'};
            } else {
                return 'ERROR EN EL REGISTRO DEL REPORTE';
            }
            }

        }elseif(($body->{'tipo_formulario'} >= 7) AND ($body->{'tipo_formulario'} <=9)){
            $sqlFormulario = generarSqlRegistro($tablasInsertar[$body->{'tipo_formulario'}], $body->{'tipo_formulario'}, $TablaConsultar[$body->{'tipo_formulario'}]);
            $db = new DB();
            $stmt = $db->consultaAll('mapa', $sqlFormulario, $body->{'valores_insertar'});
            if ($stmt) {
                return 'REGISTRO EXITOSO, '.strtoupper($TablaConsultar[$body->{'tipo_formulario'}]).' NUMERO: '.$stmt->{'insert_id'};
            }else {
                return 'ERROR EN EL REGISTRO DEL REPORTE';
            }

            
            
        }else {
            return 'EL VALOR DEL TIPO DE FORMULARIO NO ES VALIDO';
        }
    }else {

        return 'TIPO DE FORMULARIO NO ENVIADO';
    }
   

});
