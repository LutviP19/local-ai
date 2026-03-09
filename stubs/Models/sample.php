<?php 
/**
 *  @package Backend-PHP
 */

namespace App\Models;


use App\Core\Database\Model;
// use PDO; // Uncomment to build new PDO object


class MyModel extends Model
{
    public function __construct()
    {
        $pdo = null;
        // // Use a different db connection.
        // $driver = 'mysql'; $host = '127.0.0.1'; $port = 3306; $dbname = 'backend_php';
        // $pdo = new PDO(
        //             "{$driver}:host={$host};port={$port};dbname={$dbname}",
        //             $username ?? 'root',
        //             $password ?? '',
        //             $options ?? []
        //         );
        parent::__construct($pdo);

        // Set default table
        $this->table = '';
    }

    public function index(?array $request = [])
    {
        // $selectCols = $cols ?? '*';
        // $sql = 'SELECT '.$selectCols.' FROM '.$this->table.' WHERE id = ? LIMIT 1';
        // $result = Model::table($this->table)->execQuery($sql, [$id ?? 1], false, true, false);
        // // dd($result, true);

        $modelA = [
            'title' => $request['title'] ?? 'Testing model',
        ];

        $data = [
            'data' => $modelA,
            // 'errors' => $errors ?? [],
            'status' => $status ?? 201,
            // 'message' => $message ?? 'testing index',
        ];

        return $data;
    }

    public function store(?array $request = [])
    {
        // $errors = [
        //     'input_a' => 'This field is required.',
        // ];
        // $status = 400;
        // $message = 'Invalid input store.';

        $data = [
            'data' => $request ?? [],
            'errors' => $errors ?? [],
            'status' => $status ?? 201,
            'message' => $message ?? 'testing store',
        ];

        return $data;
    }

    public function edit(?array $request = [])
    {
        $data = [
            'data' => $request ?? [],
            'errors' => $errors ?? [],
            'status' => $status ?? 201,
            'message' => $message ?? 'testing edit',
        ];

        return $data;
    }

    public function update(?array $request = [])
    {
        $data = [
            'data' => $request ?? [],
            'errors' => $errors ?? [],
            'status' => $status ?? 201,
            'message' => $message ?? 'testing update',
        ];

        return $data;
    }

    public function destroy(?array $request = [])
    {
        $data = [
            'data' => $request ?? [],
            'errors' => $errors ?? [],
            'status' => $status ?? 201,
            'message' => $message ?? 'testing destroy',
        ];

        return $data;
    }
}

