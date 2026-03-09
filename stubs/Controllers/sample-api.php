<?php
/**
 *  @package Backend-PHP
 */

namespace App\Controllers\Api\v1;


use App\Core\Http\{Request, Response};
use App\Controllers\Api\ApiController;


class MyApiController extends ApiController
{
    public function __construct()
    {
        parent::__construct();

        // // State DEV environment
        // $this->isDev = true;

        // $this->useMiddleware();
    }

    public function index(Request $request, Response $response)
    {
        // // Validate token and CSRF
        // $this->validateApiToken(true);


        // $user = Model::table('users')->select(['*'])->get();
        // $roles = Model::table('roles')->select(['id', 'slug', 'name'])->get();
        // $role = Role::getRoleById(3);
        // $userUlid = User::getUlid(3);
        // dd($role, true);
        
        // \App\Core\Support\Log::debug($request->all(), 'MyApiController.index.request');
        return endResponse(
            $this->getOutput(true, 200, [
                'info' => 'This index path',
                'request'=> $request->all(),
            ], 'MyApiController'), 
            200
        );
    }
}
