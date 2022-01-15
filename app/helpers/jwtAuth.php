<?php

namespace App\Helpers;


use \Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use App\User;

class jwtAuth
{
    public $key;

    public function __construct()
    {
        $this->key = 'esto_es_una_clave_super_secreta-99887766';
    }

    public function singup($email, $password, $getToken=null)
    {
        //Buscar si existe el usuario con sus credenciales
        $user = User::where([
            'email'=>$email,
            'password'=>$password
        ])->first();
        //comprobar si son correctas
        $singup = false;
        if(is_object($user)){
            $singup= true;
        }
        //generar el token con los datos
        if($singup){
            $token = array(
                'sub' => $user->id,
                'email'=> $user->email,
                'name'=> $user->name,
                'surname'=>$user->surname,
                'description'=>$user->description,
                'image'=>$user->image,
                'iat'=> time(),
                'exp'=> time()+(7*24*60*60)
            );
            $jwt = JWT::encode($token, $this->key,'HS256');
            $decode = JWT::decode($jwt, $this->key, ['HS256']);

            
            //devolver los datos decodificados o el token, en funcion de un parametro
            if(is_null($getToken)){
                $data = $jwt;
            }else{
                $data = $decode;
            }

        }else{
            $data = array(
                'status'=>'error',
                'message'=>'login incorrecto'
            );
        }
        

        return $data;
    }

    public function checkToken($jwt, $getIdenty=false){

        $auth = false;
        
        try {
            $jwt = str_replace('"','',$jwt);
            $decoded = JWT::decode($jwt, $this->key, ['HS256']);
        } catch (\UnexpectedValueException $e) {
            $auth = false;
        }catch(\DomainException $e){
            $auth=false;
        }
         if(!empty($decoded) && is_object($decoded) && isset($decoded->sub)){
            $auth=true;
         }else{
             $auth = false;
         }

         if($getIdenty){
             return $decoded;
         }

         return $auth;
    }
}
