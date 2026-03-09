<?php
/**
 *  @package Backend-PHP
 */

namespace App\Controllers;

use App\Core\Http\{Request, Response};
use App\Core\Support\Session;
use App\Core\Database\QueryBuilder;
use App\Core\Validation\Validator;
use App\Controllers\Controller;


class MyController extends Controller
{

    public function __construct()
    {
        parent::__construct();
        
        // Allow insomnia, etc...
        $user_agent = trim($_SERVER['HTTP_USER_AGENT'] ?? '');
        $dev_agents = [
                        'insomnia',
                    ];
        $agentAllow = false;
        foreach ($dev_agents as $agent) { 
            if (str_contains(strtolower($user_agent), strtolower($agent))) {
                $agentAllow = true;
            }
        }
        // dd($agentAllow);

        // Handler reload manual
        if(!$agentAllow) {
            $ignore_uri = ['login', 'htmx'];
            if (request()->method() === 'GET' && ! in_array(request()->uri(), $ignore_uri) && !$this->__isHtmxRequest()) {
                response()->redirect('/htmx');
            }
        }
    }

    /**
     * Show the home page.
     *
     * @param App\Core\Http\Request $request
     * @param App\Core\Http\Response $response
     * @return void
     */
    public function index(Request $request, Response $response)
    {
        // $users = Model::table('users')->select(['*'])->get();
        // dd($users);
        // Session::set('users', generateUlid());
        $server = \in_array($_SERVER['SERVER_PORT'], config('app.ignore_port')) ? "OpenSwoole" : "PHP FPM";

        $this->view('index-htmx', ['server' => $server]);
    }

    public function login(Request $request, Response $response)
    {
        $this->view('login');
    }

    public function loginAuth(Request $request, Response $response)
    {
        $user = $request->username ?? '';
        $pass = $request->password ?? '';

        // Simulasi Cek Login
        if ($user === 'admin' && $pass === 'desa2026') {
            
            // 1. Kirim sinyal ke Alpine.js untuk munculkan Toast
            // Format: HX-Trigger: {"namaEvent": "isiData"}
            header('HX-Trigger: {"show-toast": "Login Berhasil! Mengalihkan..."}');

            // 2. Tunggu 1.5 detik (simulasi proses) lalu redirect
            // Catatan: Redirect HTMX dilakukan via header
            header('HX-Redirect: /htmx');
            
            exit();

        } else {
            // Jika gagal, kirim pesan error ke #error-area
            // Dan bisa juga trigger event khusus untuk suara 'tetot'
            header('HX-Trigger: {"play-error-sound": true}');
            echo "<i class='fas fa-exclamation-triangle mr-1'></i> Username atau Password salah!";
        }
    }

    public function dashboard(Request $request, Response $response)
    {
        $dataViews = [];
        $this->view('htmx.dashboard', $dataViews);
    }

    private function __isHtmxRequest() {
        return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
    }
}
