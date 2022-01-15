<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use App\Post;
use App\Helpers\jwtAuth;

class PostController extends Controller
{
    public function __construct()
    {
        $this->middleware('api.auth', ['except' => [
            'index',
            'show',
            'getImage',
            'getPostsByCategory',
            'getPostsByUser'
            ]]);
    }

    public function index()
    {
        $posts = Post::all()->load('category');

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'posts' => $posts
        ], 200);
    }

    public function show($id)
    {
        $post = Post::find($id)->load('category')
                               ->load('user');
        if (is_object($post)) {
            $data = [
                'code' => 200,
                'status' => 'success',
                'post' => $post
            ];
        } else {
            $data = [
                'code' => 404,
                'status' => 'success',
                'message' => 'la entradano existe'
            ];
        }
        return response()->json($data, $data['code']);
    }

    public function store(Request $request)
    {
        //recoger los datos del usuario por POST
        $json = $request->input('json', null);

        $params = json_decode($json);
        $params_array = json_decode($json, true);

        if (!empty($params_array)) {
            //comprobar si el usuario está identificado
            $token = $request->header('Authorization');
            $jwtAuth = new \JwtAuth();
            $checkToken = $jwtAuth->checkToken($token);
            //sacar usuario identificado
            $user = $checkToken = $jwtAuth->checkToken($token, true);


            //validar los datos
            $validate = Validator::make($params_array, [
                'title' => 'required',
                'content' => 'required',
                'category_id' => 'required',
                'image' => 'required'
            ]);


            if ($validate->fails()) {
                $data = [
                    'code' => 400,
                    'status' => 'error',
                    'message' => 'No se ha guardado el post, faltan datos',
                    'user' => $user,
                    'datos' => $params_array,
                    'validate fails' => $validate->fails()
                ];
            } else {
                $post = new Post();
                $post->user_id = $user->sub;
                $post->category_id = $params->category_id;
                $post->title = $params->title;
                $post->content = $params->content;
                $post->image = $params->image;
                $post->save();

                $data = [
                    'code' => 200,
                    'status' => 'success',
                    'post' => $post
                ];
            }

            //guardar el articulo
        } else {

            $data = [
                'code' => 400,
                'status' => 'error',
                'message' => 'Envia los datos correctamente'
            ];
        }


        //devolver la respuesta
        return response()->json($data, $data['code']);
    }


    public function update($id, Request $request)
    {
        //recoger los datos por post
        $json = $request->input('json', null);
        $params_array = json_decode($json, true);

        //datos para devolver
        $data = array(
            'code' => 400,
            'status' => 'error',
            'message' => 'datos enviados incorrectamente'
        );

        if (!empty($params_array)) {
            //validar datos
            $validate = Validator::make($params_array, [
                'title' => 'required',
                'content' => 'required',
                'category_id' => 'required'
            ]);

            if ($validate->fails()) {
                $data['errors'] = $validate->errors();
                return response()->json($data, $data['code']);
            }
            //eliminiar lo que no queremos actualizar
            unset($params_array['id']);
            unset($params_array['user_id']);
            unset($params_array['created_at']);
            unset($params_array['user']);

            //conseguir el usuario identificadod 
            $user = $this->getIdentity($request);

            //actualizar el registro en concreto
            $where = [
                'id' => $id,
                'user_id' => $user->sub
            ];
            $post = Post::updateOrCreate($where, $params_array);

            //devolver algo
            $data = array(
                'code' => 200,
                'status' => 'success',
                'post' => $post,
                'changes' => $params_array
            );
        }

        return response()->json($data, $data['code']);
    }

    public function destroy($id, Request $request)
    {
        //conseguir el usuario identificadod 
        $user = $this->getIdentity($request);

        //conseguir el registro
        $post = Post::where('id', $id)
            ->where('user_id', $user->sub)
            ->first();

        if (!empty($post)) {
            //borrarlo
            $post->delete();

            //devolver algo
            $data = array(
                'code' => 200,
                'status' => 'success',
                'message' => 'post borrado'
            );
        } else {
            $data = array(
                'code' => 400,
                'status' => 'error',
                'message' => 'el post no existe'
            );
        }
        return response()->json($data, $data['code']);
    }


    private function getIdentity($request)
    {
        $jwtAuth = new \JwtAuth();
        $token = $request->header('Authorization', null);
        $user = $jwtAuth->checkToken($token, true);

        return $user;
    }

    public function upload(Request $request)
    {
        //recoger la imagen de la peticion 
        $image = $request->file('file0');

        //validar la imagen
        $validate = Validator::make($request->all(), [
            'file0' => 'required|image|mimes:jpg,jpeg,png,gif'
        ]);

        //guardar la imagen
        if (!$image || $validate->fails()) {
            $data = array(
                'code' => 400,
                'status' => 'error',
                'message' => 'no hay imagen o no es válida'
            );
        } else {
            $image_name = time() . $image->getClientOriginalName();

            \Storage::disk('images')->put($image_name, \File::get($image));

            $data = array(
                'code' => 200,
                'status' => 'success',
                'message' => $image_name
            );
        }
        //devolver datos
        return response()->json($data, $data['code']);
    }


    public function getImage($filename)
    {
        //comprobar si existe el fichero
        $isset = \Storage::disk('images')->exists($filename);

        if ($isset) {
            //conseguir la imagen
            $file = \Storage::disk('images')->get($filename);
            //devolver la imagen
            return new Response($file, 200);
        }else {
            $data = [
                'code' => 404,
                'status'=>'error',
                'message'=>'la imagen no existe'
            ];
        }


        //mostrar error
        return response()->json($data, $data['code']);
    }

    public function getPostsByCategory($id){
        $posts = Post::where('category_id', $id)->get();

        return response()->json([
            'status ' => 'success',
            'posts'=> $posts
        ], 200);
    }


    public function getPostsByUser($id){
        $posts = Post::where('user_id', $id)->get();

        return response()->json([
            'status' =>'success',
            'posts'=>$posts   
        ], 200);
    }
}
